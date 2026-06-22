<?php
/**
 * Stripe webhook lifecycle smoke test.
 *
 * Creates a temporary workspace and exercises the same webhook handler used by
 * pages/stripe-webhook.php. It does not call Stripe and does not create a
 * payment; it verifies local state transitions for completion, duplicate event
 * handling, failed payment, recovery, and cancellation.
 *
 * Usage:
 *   php bin/test-stripe-webhook-lifecycle.php --json
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
$stamp = gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
$event_prefix = 'evt_foxdesk_smoke_' . strtolower(str_replace('-', '_', $stamp));
$customer_id = 'cus_foxdesk_smoke_' . strtolower(str_replace('-', '', $stamp));
$subscription_id = 'sub_foxdesk_smoke_' . strtolower(str_replace('-', '', $stamp));
$tenant_id = 0;
$event_ids = [];

$result = [
    'ok' => true,
    'status' => 'stripe_webhook_lifecycle_smoke',
    'tenant_id' => null,
    'checks' => [],
    'events' => [],
    'cleanup' => [
        'stripe_events_cleaned' => false,
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

$event = static function (string $id, string $type, array $object): array {
    return [
        'id' => $id,
        'object' => 'event',
        'type' => $type,
        'data' => [
            'object' => $object,
        ],
    ];
};

$record_event = static function (string $name, array $handled) use (&$result): void {
    $result['events'][$name] = [
        'handled' => (bool) ($handled['handled'] ?? false),
        'duplicate' => (bool) ($handled['duplicate'] ?? false),
        'tenant_id' => isset($handled['tenant_id']) ? (int) $handled['tenant_id'] : null,
        'type' => (string) ($handled['type'] ?? ''),
    ];
};

try {
    ensure_tenant_baseline();
    billing_ensure_stripe_events_table();

    $tenant_id = (int) db_insert('tenants', [
        'uuid' => tenant_generate_uuid(),
        'name' => 'FoxDesk Stripe webhook smoke ' . $stamp,
        'slug' => 'stripe-webhook-smoke-' . strtolower($stamp),
        'plan' => billing_plan_code(),
        'status' => 'trialing',
        'subscription_status' => 'trialing',
        'billing_email' => 'webhook-smoke+' . strtolower($stamp) . '@foxdesk.net',
        'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $result['tenant_id'] = $tenant_id;

    $checkout_event_id = $event_prefix . '_checkout_completed';
    $event_ids[] = $checkout_event_id;
    $checkout = billing_handle_webhook_event($event($checkout_event_id, 'checkout.session.completed', [
        'id' => 'cs_foxdesk_smoke_' . strtolower(str_replace('-', '', $stamp)),
        'mode' => 'subscription',
        'customer' => $customer_id,
        'subscription' => $subscription_id,
        'client_reference_id' => (string) $tenant_id,
        'metadata' => [
            'tenant_id' => (string) $tenant_id,
            'tenant_slug' => 'stripe-webhook-smoke-' . strtolower($stamp),
            'plan' => billing_plan_code(),
        ],
        'automatic_tax' => [
            'enabled' => true,
            'status' => 'complete',
        ],
        'tax_id_collection' => [
            'enabled' => true,
        ],
        'customer_details' => [
            'email' => 'billing@example.test',
            'tax_ids' => [
                [
                    'type' => 'eu_vat',
                    'value' => 'CZ12345678',
                ],
            ],
        ],
    ]));
    $record_event('checkout.session.completed', $checkout);
    $tenant = billing_get_tenant($tenant_id);
    $check('checkout_completed_handled', !empty($checkout['handled']), 'Checkout completion event must be handled.');
    $check('checkout_sets_active', ($tenant['status'] ?? '') === 'active' && ($tenant['subscription_status'] ?? '') === 'active', 'Checkout completion must activate the workspace.');
    $check('checkout_saves_customer', ($tenant['stripe_customer_id'] ?? '') === $customer_id, 'Checkout completion must save Stripe customer id.');
    $check('checkout_saves_subscription', ($tenant['stripe_subscription_id'] ?? '') === $subscription_id, 'Checkout completion must save Stripe subscription id.');

    $duplicate = billing_handle_webhook_event($event($checkout_event_id, 'checkout.session.completed', [
        'customer' => $customer_id,
        'subscription' => $subscription_id,
        'client_reference_id' => (string) $tenant_id,
    ]));
    $record_event('duplicate_checkout.session.completed', $duplicate);
    $check('duplicate_checkout_guarded', !empty($duplicate['duplicate']), 'Duplicate Checkout completion event must be guarded.');

    $failed_event_id = $event_prefix . '_invoice_payment_failed';
    $event_ids[] = $failed_event_id;
    $failed = billing_handle_webhook_event($event($failed_event_id, 'invoice.payment_failed', [
        'id' => 'in_foxdesk_smoke_failed_' . strtolower(str_replace('-', '', $stamp)),
        'customer' => $customer_id,
        'subscription' => $subscription_id,
        'metadata' => [
            'tenant_id' => (string) $tenant_id,
        ],
    ]));
    $record_event('invoice.payment_failed', $failed);
    $tenant = billing_get_tenant($tenant_id);
    $first_past_due_at = (string) ($tenant['suspended_at'] ?? '');
    $check('failed_payment_handled', !empty($failed['handled']), 'Payment failure event must be handled.');
    $check('failed_payment_marks_past_due', ($tenant['status'] ?? '') === 'past_due' && ($tenant['subscription_status'] ?? '') === 'past_due', 'Payment failure must mark workspace past_due.');
    $check('failed_payment_sets_grace_clock', $first_past_due_at !== '', 'Payment failure must set the past-due grace clock.');

    $failed_repeat_event_id = $event_prefix . '_invoice_payment_failed_repeat';
    $event_ids[] = $failed_repeat_event_id;
    $failed_repeat = billing_handle_webhook_event($event($failed_repeat_event_id, 'invoice.payment_failed', [
        'id' => 'in_foxdesk_smoke_failed_repeat_' . strtolower(str_replace('-', '', $stamp)),
        'customer' => $customer_id,
        'subscription' => $subscription_id,
    ]));
    $record_event('invoice.payment_failed.repeat', $failed_repeat);
    $tenant = billing_get_tenant($tenant_id);
    $check('failed_payment_preserves_grace_clock', (string) ($tenant['suspended_at'] ?? '') === $first_past_due_at, 'Repeated payment failures must preserve the original past-due clock.');

    $paid_event_id = $event_prefix . '_invoice_paid';
    $event_ids[] = $paid_event_id;
    $paid = billing_handle_webhook_event($event($paid_event_id, 'invoice.paid', [
        'id' => 'in_foxdesk_smoke_paid_' . strtolower(str_replace('-', '', $stamp)),
        'customer' => $customer_id,
        'subscription' => $subscription_id,
    ]));
    $record_event('invoice.paid', $paid);
    $tenant = billing_get_tenant($tenant_id);
    $check('paid_invoice_handled', !empty($paid['handled']), 'Paid invoice event must be handled.');
    $check('paid_invoice_reactivates', ($tenant['status'] ?? '') === 'active' && ($tenant['subscription_status'] ?? '') === 'active', 'Paid invoice must reactivate the workspace.');
    $check('paid_invoice_clears_grace_clock', trim((string) ($tenant['suspended_at'] ?? '')) === '', 'Paid invoice must clear the past-due grace clock.');

    $cancel_event_id = $event_prefix . '_subscription_deleted';
    $event_ids[] = $cancel_event_id;
    $canceled = billing_handle_webhook_event($event($cancel_event_id, 'customer.subscription.deleted', [
        'id' => $subscription_id,
        'customer' => $customer_id,
        'status' => 'canceled',
        'metadata' => [
            'tenant_id' => (string) $tenant_id,
        ],
    ]));
    $record_event('customer.subscription.deleted', $canceled);
    $tenant = billing_get_tenant($tenant_id);
    $check('subscription_deleted_handled', !empty($canceled['handled']), 'Subscription deleted event must be handled.');
    $check('subscription_deleted_cancels', ($tenant['status'] ?? '') === 'canceled' && ($tenant['subscription_status'] ?? '') === 'canceled', 'Subscription deleted must cancel the workspace.');
    $check('subscription_deleted_blocks_access_clock', trim((string) ($tenant['blocked_at'] ?? '')) !== '', 'Cancellation must set blocked_at for access checks.');
} catch (Throwable $e) {
    $fail($e->getMessage());
} finally {
    if ($event_ids) {
        try {
            $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
            db_query("DELETE FROM billing_stripe_events WHERE event_id IN ({$placeholders})", $event_ids);
            $result['cleanup']['stripe_events_cleaned'] = true;
        } catch (Throwable $e) {
            $result['warnings'][] = 'Stripe event cleanup warning: ' . $e->getMessage();
        }
    } else {
        $result['cleanup']['stripe_events_cleaned'] = true;
    }

    if ($tenant_id > 0 && !$keep_db) {
        try {
            db_delete('tenants', 'id = ?', [$tenant_id]);
            $result['cleanup']['db_cleaned'] = true;
        } catch (Throwable $e) {
            $result['warnings'][] = 'Tenant cleanup warning: ' . $e->getMessage();
        }
    } elseif ($tenant_id > 0) {
        $result['warnings'][] = '--keep-db used; temporary tenant was left in the database.';
    } else {
        $result['cleanup']['db_cleaned'] = true;
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
    echo ($result['ok'] ? 'Stripe webhook lifecycle smoke OK' : 'Stripe webhook lifecycle smoke FAILED') . PHP_EOL;
    foreach ($result['errors'] as $error) {
        echo '- ' . $error . PHP_EOL;
    }
}

exit($result['ok'] ? 0 : 1);
