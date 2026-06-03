<?php
/**
 * CLI entrypoint: report metered billing usage to Stripe.
 *
 * Usage:
 *   php bin/report-billing-usage.php
 *   php bin/report-billing-usage.php --dry-run
 *   php bin/report-billing-usage.php --json
 *   php bin/report-billing-usage.php --tenant-id=1 --period=2026-06-01 --dry-run --json
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

$opts = getopt('', ['dry-run', 'json', 'tenant-id:', 'period:']);
$dry_run = array_key_exists('dry-run', $opts);
$json = array_key_exists('json', $opts);
$tenant_id = isset($opts['tenant-id']) ? (int) $opts['tenant-id'] : 0;
$period_key = isset($opts['period']) ? (string) $opts['period'] : null;

if ($period_key !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_key)) {
    $result = [
        'ok' => false,
        'status' => 'invalid_options',
        'message' => 'Use --period in YYYY-MM-DD format.',
    ];
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        fwrite(STDERR, '[billing-usage] ERROR: ' . $result['message'] . PHP_EOL);
    }
    exit(2);
}

if (!billing_enabled() && !$dry_run) {
    $result = [
        'ok' => true,
        'status' => 'disabled',
        'message' => 'Billing is not enabled.',
    ];
} else {
    if ($tenant_id > 0) {
        $tenant_result = billing_report_storage_usage_for_tenant($tenant_id, $period_key, $dry_run);
        $result = [
            'ok' => true,
            'reported' => 0,
            'dry_run' => 0,
            'skipped' => 0,
            'failed' => 0,
            'tenants' => [$tenant_result],
        ];
        $status = (string) ($tenant_result['status'] ?? 'skipped');
        if (array_key_exists($status, $result)) {
            $result[$status]++;
        }
        if ($status === 'failed') {
            $result['ok'] = false;
        }
    } else {
        $result = billing_report_storage_usage_all($dry_run, $period_key);
    }
    $result['status'] = $dry_run ? 'dry_run' : 'completed';
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 2);
}

echo '[billing-usage] status=' . ($result['status'] ?? 'unknown')
    . ' reported=' . (int) ($result['reported'] ?? 0)
    . ' dry_run=' . (int) ($result['dry_run'] ?? 0)
    . ' skipped=' . (int) ($result['skipped'] ?? 0)
    . ' failed=' . (int) ($result['failed'] ?? 0) . PHP_EOL;

exit(!empty($result['ok']) ? 0 : 2);
