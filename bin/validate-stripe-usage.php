<?php
/**
 * CLI entrypoint: validate Stripe metered storage usage reporting.
 *
 * This command is intentionally test-mode friendly. It runs a dry-run first,
 * can optionally send one real meter event to Stripe, and checks the configured
 * Billing Meter by event name without printing secrets.
 *
 * Usage:
 *   php bin/validate-stripe-usage.php --json
 *   php bin/validate-stripe-usage.php --tenant-id=1 --live --json
 *   php bin/validate-stripe-usage.php --tenant-id=1 --live --allow-live-key --json
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
    'tenant-id:',
    'period:',
    'live',
    'allow-live-key',
    'skip-stripe-check',
]);

$json = array_key_exists('json', $opts);
$live = array_key_exists('live', $opts);
$allow_live_key = array_key_exists('allow-live-key', $opts);
$skip_stripe_check = array_key_exists('skip-stripe-check', $opts);
$tenant_id = isset($opts['tenant-id']) ? (int) $opts['tenant-id'] : 0;
$period_key = isset($opts['period']) ? (string) $opts['period'] : billing_usage_period_key();

$result = [
    'ok' => true,
    'status' => $live ? 'live_validation' : 'dry_run_validation',
    'period_key' => $period_key,
    'tenant_id' => $tenant_id > 0 ? $tenant_id : null,
    'config' => null,
    'meter' => null,
    'dry_run' => null,
    'live_report' => null,
    'stripe_verification' => null,
    'errors' => [],
    'warnings' => [],
];

$fail = static function (string $message) use (&$result): void {
    $result['ok'] = false;
    $result['errors'][] = $message;
};

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_key)) {
    $fail('Use --period in YYYY-MM-DD format.');
}

if ($live && $tenant_id <= 0) {
    $fail('Live validation requires --tenant-id so only one test customer is touched.');
}

$key_mode = billing_stripe_secret_key_mode();
$needs_stripe = $live || (!$skip_stripe_check && $key_mode !== 'missing');
$config = billing_usage_reporting_config_status($needs_stripe);
$result['config'] = $config;
$result['warnings'] = array_merge($result['warnings'], $config['warnings']);

if ($key_mode === 'live' && !$allow_live_key) {
    $fail('Refusing to run with a live Stripe key. Use --allow-live-key only during an intentional production validation.');
}

if (!$config['ok'] && $needs_stripe) {
    foreach ($config['errors'] as $error) {
        $fail($error);
    }
}

$meter = null;
if ($result['ok'] && !$skip_stripe_check && $key_mode !== 'missing') {
    try {
        $meter = billing_find_meter_by_event_name(billing_storage_meter_event_name());
        if (!$meter) {
            $fail('No active Stripe Billing Meter found for event name: ' . billing_storage_meter_event_name());
        } else {
            $result['meter'] = [
                'id' => $meter['id'] ?? null,
                'event_name' => $meter['event_name'] ?? null,
                'status' => $meter['status'] ?? null,
                'livemode' => $meter['livemode'] ?? null,
                'customer_payload_key' => $meter['customer_mapping']['event_payload_key'] ?? null,
                'value_payload_key' => $meter['value_settings']['event_payload_key'] ?? null,
            ];
        }
    } catch (Throwable $e) {
        $fail('Stripe meter check failed: ' . $e->getMessage());
    }
}

if ($result['ok'] || !$live) {
    $result['dry_run'] = $tenant_id > 0
        ? billing_report_storage_usage_for_tenant($tenant_id, $period_key, true)
        : billing_report_storage_usage_all(true, $period_key);
}

if ($result['ok'] && $live) {
    $result['live_report'] = billing_report_storage_usage_for_tenant($tenant_id, $period_key, false);
    $live_report_status = (string) ($result['live_report']['status'] ?? '');
    if (!in_array($live_report_status, ['reported', 'skipped'], true)
        || (($result['live_report']['reason'] ?? '') !== 'already_reported' && $live_report_status === 'skipped')
    ) {
        $fail('Live meter event was not reported. Status: ' . ($result['live_report']['status'] ?? 'unknown'));
    }

    $tenant = billing_get_tenant($tenant_id);
    $customer_id = trim((string) ($tenant['stripe_customer_id'] ?? ''));
    $subscription_id = trim((string) ($tenant['stripe_subscription_id'] ?? ''));

    $result['stripe_verification'] = [
        'meter_summary' => null,
        'invoice_preview' => null,
        'notes' => [
            'Stripe meter events are processed asynchronously; summaries and invoice previews can lag after a fresh event.',
        ],
    ];

    if ($meter && $customer_id !== '') {
        $start_time = (int) strtotime($period_key . ' 00:00:00 UTC');
        $end_time = $start_time + 86400;

        try {
            $summary = billing_meter_event_summaries((string) $meter['id'], $customer_id, $start_time, $end_time);
            $result['stripe_verification']['meter_summary'] = [
                'object' => $summary['object'] ?? null,
                'count' => count($summary['data'] ?? []),
                'has_more' => $summary['has_more'] ?? false,
            ];
        } catch (Throwable $e) {
            $result['warnings'][] = 'Meter summary check failed: ' . $e->getMessage();
        }

        try {
            $invoice = billing_invoice_preview_for_customer($customer_id, $subscription_id ?: null);
            $result['stripe_verification']['invoice_preview'] = [
                'object' => $invoice['object'] ?? null,
                'currency' => $invoice['currency'] ?? null,
                'amount_due' => $invoice['amount_due'] ?? null,
                'total' => $invoice['total'] ?? null,
                'line_count' => count($invoice['lines']['data'] ?? []),
            ];
        } catch (Throwable $e) {
            $result['warnings'][] = 'Upcoming invoice preview check failed: ' . $e->getMessage();
        }
    }
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($result['ok'] ? 0 : 2);
}

echo '[stripe-usage-validation] status=' . $result['status']
    . ' ok=' . (int) $result['ok']
    . ' period=' . $result['period_key'] . PHP_EOL;

if (!empty($result['dry_run'])) {
    echo '[stripe-usage-validation] dry_run=' . json_encode($result['dry_run'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
if (!empty($result['live_report'])) {
    echo '[stripe-usage-validation] live_report=' . json_encode($result['live_report'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
foreach ($result['warnings'] as $warning) {
    fwrite(STDERR, '[stripe-usage-validation] WARNING: ' . $warning . PHP_EOL);
}
foreach ($result['errors'] as $error) {
    fwrite(STDERR, '[stripe-usage-validation] ERROR: ' . $error . PHP_EOL);
}

exit($result['ok'] ? 0 : 2);
