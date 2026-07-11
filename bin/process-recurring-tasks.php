<?php
/**
 * CLI entrypoint: process due recurring tasks.
 *
 * Usage:
 *   php bin/process-recurring-tasks.php
 *   php bin/process-recurring-tasks.php --json
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
require_once BASE_PATH . '/includes/cli-functions.php';

$opts = getopt('', ['json']);
$json = array_key_exists('json', $opts);

cli_scheduler_log('scheduler', 'info', 'Recurring tasks processing started');

try {
    $processed = process_recurring_tasks();
} catch (Throwable $e) {
    if ($json) {
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        fwrite(STDERR, '[recurring] ERROR: ' . $e->getMessage() . PHP_EOL);
    }
    cli_scheduler_log('scheduler', 'error', 'Recurring tasks processing failed', [
        'error' => $e->getMessage(),
    ]);
    exit(2);
}

if ($json) {
    echo json_encode([
        'ok' => true,
        'processed' => (int) $processed,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

echo '[recurring] processed=' . (int) $processed . PHP_EOL;

cli_scheduler_log('scheduler', 'info', 'Recurring tasks processing completed', [
    'processed' => (int) $processed,
]);
