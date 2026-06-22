<?php
/**
 * Production-safe Stripe Billing smoke test.
 *
 * Creates a temporary workspace, opens a Checkout Session, verifies the
 * customer-facing billing settings, creates a Portal Session, then expires the
 * Checkout Session and removes the temporary Stripe customer/workspace.
 *
 * It never completes a payment and never prints full Checkout or Portal URLs.
 *
 * Usage:
 *   php bin/test-stripe-billing-flow.php --json
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must run in CLI mode.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));

if (!file_exists(BASE_PATH . '/config.php')) {
    fwrite(STDERR, "Missing config.php. Install/configure the app first.\n");
    exit(1);
}

require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/tenant-functions.php';
require_once BASE_PATH . '/includes/functions.php';

$opts = getopt('', [
    'json',
    'keep-db',
]);

$json = array_key_exists('json', $opts);
$keep_db = array_key_exists('keep-db', $opts);

$result = [
    'ok' => true,
    'status' => 'stripe_billing_flow_smoke',
    'key_mode' => billing_stripe_secret_key_mode(),
    'tenant_id' => null,
    'checkout_session_id' => null,
    'checkout_host' => null,
    'portal_host' => null,
    'stripe_customer_id' => null,
    'checks' => [],
    'cleanup' => [
        'checkout_session_expired' => false,
        'stripe_customer_deleted' => false,
        'db_cleaned' => false,
    ],
    'warnings' => [],
    'errors' => [],
];

$fail = static function (string $message) use (&$result): void {
    $result['ok'] = false;
    $result['errors'][] = $message;
};

$check = static function (string $name, bool $condition, string $message) use (&$result, $fail): void {
    $result['checks'][$name] = $condition;
    if (!$condition) {
        $fail($message);
    }
};

$tenant_id = 0;
$checkout_session_id = '';
$stripe_customer_id = '';

try {
    $config = billing_usage_reporting_config_status(true);
    $result['config'] = [
        'billing_enabled' => (bool) ($config['billing_enabled'] ?? false),
        'stripe_tax_enabled' => (bool) ($config['stripe_tax_enabled'] ?? false),
        'tax_id_collection_enabled' => (bool) ($config['tax_id_collection_enabled'] ?? false),
        'has_storage_price' => (bool) ($config['has_storage_price'] ?? false),
        'key_mode' => (string) ($config['key_mode'] ?? 'unknown'),
    ];
    foreach ((array) ($config['warnings'] ?? []) as $warning) {
        $result['warnings'][] = $warning;
    }
    foreach ((array) ($config['errors'] ?? []) as $error) {
        $fail($error);
    }

    $base_price_id = billing_plan_price_id();
    $storage_price_id = billing_storage_overage_price_id();
    $check('billing_enabled', billing_enabled(), 'Billing must be enabled.');
    $check('base_price_configured', $base_price_id !== '', 'STRIPE_PRICE_CLOUD_BASE is not configured.');
    $check('storage_price_configured', $storage_price_id !== '', 'STRIPE_PRICE_STORAGE_OVERAGE is not configured.');

    if (!$result['ok']) {
        throw new RuntimeException('Billing configuration is not ready for a Stripe flow smoke.');
    }

    ensure_tenant_baseline();
    $stamp = gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
    $slug = 'stripe-billing-smoke-' . strtolower($stamp);
    $billing_email = 'billing-smoke+' . strtolower($stamp) . '@foxdesk.net';

    $tenant_id = (int) db_insert('tenants', [
        'uuid' => tenant_generate_uuid(),
        'name' => 'FoxDesk Stripe billing smoke ' . $stamp,
        'slug' => $slug,
        'plan' => billing_plan_code(),
        'status' => 'trialing',
        'subscription_status' => 'trialing',
        'billing_email' => $billing_email,
        'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+' . billing_trial_days() . ' days')),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $result['tenant_id'] = $tenant_id;

    $checkout_url = billing_create_checkout_session($tenant_id);
    $checkout_host = (string) parse_url($checkout_url, PHP_URL_HOST);
    $result['checkout_host'] = $checkout_host;
    $check('checkout_host', $checkout_host === 'checkout.stripe.com', 'Checkout must be hosted by checkout.stripe.com.');

    if (!preg_match('/(cs_(?:test|live)_[A-Za-z0-9]+)/', $checkout_url, $matches)) {
        throw new RuntimeException('Could not extract Checkout Session id from Stripe URL.');
    }
    $checkout_session_id = (string) $matches[1];
    $result['checkout_session_id'] = $checkout_session_id;

    $tenant_after_checkout = billing_get_tenant($tenant_id);
    $stripe_customer_id = trim((string) ($tenant_after_checkout['stripe_customer_id'] ?? ''));
    $result['stripe_customer_id'] = $stripe_customer_id;
    $check('customer_saved_on_tenant', $stripe_customer_id !== '', 'Checkout must save a Stripe customer id on the tenant.');

    $session = billing_stripe_request('GET', 'checkout/sessions/' . rawurlencode($checkout_session_id));
    $line_items = billing_stripe_request('GET', 'checkout/sessions/' . rawurlencode($checkout_session_id) . '/line_items', [
        'limit' => '10',
    ]);

    $check('session_mode_subscription', (string) ($session['mode'] ?? '') === 'subscription', 'Checkout Session must use subscription mode.');
    $check('client_reference_is_tenant', (string) ($session['client_reference_id'] ?? '') === (string) $tenant_id, 'Checkout client_reference_id must match tenant id.');
    $check('session_customer_matches_tenant', (string) ($session['customer'] ?? '') === $stripe_customer_id, 'Checkout customer must match the tenant Stripe customer.');
    $check('session_open', (string) ($session['status'] ?? '') === 'open', 'Checkout Session should be open before cleanup.');

    $auto_tax_enabled = (bool) ($session['automatic_tax']['enabled'] ?? false);
    $tax_expected = billing_stripe_tax_enabled();
    $check('automatic_tax_matches_config', $auto_tax_enabled === $tax_expected, 'Checkout automatic tax setting must match STRIPE_TAX_ENABLED.');

    $tax_id_enabled = (bool) ($session['tax_id_collection']['enabled'] ?? false);
    $tax_id_expected = billing_tax_id_collection_enabled();
    $check('tax_id_collection_matches_config', $tax_id_enabled === $tax_id_expected, 'Checkout tax ID collection must match STRIPE_TAX_ID_COLLECTION_ENABLED.');

    $address_collection = (string) ($session['billing_address_collection'] ?? '');
    $check(
        'billing_address_required_for_tax_ids',
        !$tax_id_expected || $address_collection === 'required',
        'Checkout must require billing address when tax ID collection is enabled.'
    );

    $line_price_ids = [];
    foreach ((array) ($line_items['data'] ?? []) as $line_item) {
        $line_price_id = (string) ($line_item['price']['id'] ?? '');
        if ($line_price_id !== '') {
            $line_price_ids[] = $line_price_id;
        }
    }
    $check('line_items_include_base_price', in_array($base_price_id, $line_price_ids, true), 'Checkout line items must include the base subscription price.');
    $check('line_items_include_storage_price', $storage_price_id === '' || in_array($storage_price_id, $line_price_ids, true), 'Checkout line items must include the storage overage price.');

    $success_url = (string) ($session['success_url'] ?? '');
    $cancel_url = (string) ($session['cancel_url'] ?? '');
    $check('success_url_returns_to_billing', str_contains($success_url, 'page=billing') && str_contains($success_url, 'checkout=success'), 'Checkout success URL must return to billing with success state.');
    $check('cancel_url_returns_to_billing', str_contains($cancel_url, 'page=billing') && str_contains($cancel_url, 'checkout=cancelled'), 'Checkout cancel URL must return to billing with cancelled state.');

    $portal_url = billing_create_portal_session($tenant_id);
    $portal_host = (string) parse_url($portal_url, PHP_URL_HOST);
    $result['portal_host'] = $portal_host;
    $check('portal_host', $portal_host === 'billing.stripe.com', 'Customer Portal must be hosted by billing.stripe.com.');
} catch (Throwable $e) {
    $fail($e->getMessage());
} finally {
    if ($tenant_id > 0 && $stripe_customer_id === '') {
        try {
            $tenant_for_cleanup = billing_get_tenant($tenant_id);
            $stripe_customer_id = trim((string) ($tenant_for_cleanup['stripe_customer_id'] ?? ''));
            if ($stripe_customer_id !== '') {
                $result['stripe_customer_id'] = $stripe_customer_id;
            }
        } catch (Throwable $e) {
            $result['warnings'][] = 'Stripe customer lookup cleanup warning: ' . $e->getMessage();
        }
    }

    if ($checkout_session_id !== '') {
        try {
            $expired = billing_stripe_request('POST', 'checkout/sessions/' . rawurlencode($checkout_session_id) . '/expire');
            $result['cleanup']['checkout_session_expired'] = in_array((string) ($expired['status'] ?? ''), ['expired', 'complete'], true);
        } catch (Throwable $e) {
            $result['warnings'][] = 'Checkout cleanup warning: ' . $e->getMessage();
        }
    }

    if ($stripe_customer_id !== '') {
        try {
            $deleted = billing_stripe_request('DELETE', 'customers/' . rawurlencode($stripe_customer_id));
            $result['cleanup']['stripe_customer_deleted'] = (bool) ($deleted['deleted'] ?? false);
        } catch (Throwable $e) {
            $result['warnings'][] = 'Stripe customer cleanup warning: ' . $e->getMessage();
        }
    }

    if ($tenant_id > 0 && !$keep_db) {
        try {
            db_delete('tenants', 'id = ?', [$tenant_id]);
            $result['cleanup']['db_cleaned'] = true;
        } catch (Throwable $e) {
            $result['warnings'][] = 'Database cleanup warning: ' . $e->getMessage();
        }
    } elseif ($tenant_id > 0) {
        $result['cleanup']['db_cleaned'] = false;
        $result['warnings'][] = '--keep-db used; temporary tenant was left in the database.';
    }
}

foreach ($result['cleanup'] as $cleanup_ok) {
    if ($cleanup_ok !== true && !$keep_db) {
        $result['ok'] = false;
    }
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo ($result['ok'] ? 'Stripe billing flow smoke OK' : 'Stripe billing flow smoke FAILED') . PHP_EOL;
    foreach ($result['errors'] as $error) {
        echo '- ' . $error . PHP_EOL;
    }
}

exit($result['ok'] ? 0 : 1);
