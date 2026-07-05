<?php
/**
 * Sync Cloudflare Email Routing rules for friendly workspace aliases.
 *
 * Usage:
 *   php bin/sync-cloudflare-email-routes.php --dry-run --json
 *   php bin/sync-cloudflare-email-routes.php --tenant-id=3 --json
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must run in CLI mode.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/tenant-functions.php';

$opts = getopt('', ['dry-run', 'tenant-id:', 'json']);
$dry_run = array_key_exists('dry-run', $opts);
$json = array_key_exists('json', $opts);
$tenant_id = (int) ($opts['tenant-id'] ?? 0);

try {
    $result = cloudflare_email_routing_sync_workspace_aliases($dry_run, $tenant_id);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'status' => 'failed',
        'message' => $e->getMessage(),
    ];
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 2);
}

echo '[cloudflare-email-routes] ' . (!empty($result['ok']) ? 'ok' : 'failed') . PHP_EOL;
echo 'dry_run=' . (!empty($result['dry_run']) ? 'yes' : 'no') . PHP_EOL;
echo 'count=' . (int) ($result['count'] ?? 0) . PHP_EOL;
echo 'failed=' . (int) ($result['failed'] ?? 0) . PHP_EOL;

foreach (($result['results'] ?? []) as $item) {
    $line = sprintf(
        '- tenant=%s address=%s status=%s',
        (string) ($item['tenant_slug'] ?? $item['tenant_id'] ?? ''),
        (string) ($item['address'] ?? ''),
        (string) ($item['status'] ?? 'unknown')
    );
    if (!empty($item['message'])) {
        $line .= ' message=' . $item['message'];
    }
    echo $line . PHP_EOL;
}

exit(!empty($result['ok']) ? 0 : 2);
