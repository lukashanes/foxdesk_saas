<?php
/**
 * Copy local attachment files to an off-server R2 backup prefix.
 *
 * This is a backup job, not a migration. It does not change attachment metadata
 * and it does not delete local files.
 *
 * Usage:
 *   php bin/backup-attachments-to-r2.php --dry-run --json
 *   php bin/backup-attachments-to-r2.php --tenant-id=13 --limit=100
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
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/storage-functions.php';

$opts = getopt('', ['tenant-id:', 'limit:', 'dry-run', 'json']);
$tenant_id = (int) ($opts['tenant-id'] ?? 0);
$limit = max(1, (int) ($opts['limit'] ?? 500));
$dry_run = array_key_exists('dry-run', $opts);
$json = array_key_exists('json', $opts);

$result = [
    'ok' => false,
    'dry_run' => $dry_run,
    'tenant_id' => $tenant_id > 0 ? $tenant_id : null,
    'limit' => $limit,
    'backup_prefix' => 'backups/attachments/' . gmdate('Y-m-d'),
    'checked' => 0,
    'uploaded' => 0,
    'skipped' => 0,
    'missing' => 0,
    'errors' => [],
];

try {
    storage_r2_assert_configured();

    $params = [];
    $where = "WHERE COALESCE(storage_driver, 'local') <> 'r2'";
    if ($tenant_id > 0) {
        $where .= " AND tenant_id = ?";
        $params[] = $tenant_id;
    }
    $params[] = $limit;

    $rows = db_fetch_all(
        "SELECT * FROM attachments {$where} ORDER BY id ASC LIMIT ?",
        $params
    );

    foreach ($rows as $attachment) {
        $result['checked']++;
        $row_tenant_id = max(1, (int) ($attachment['tenant_id'] ?? $tenant_id ?: 1));
        $relative_path = function_exists('attachment_storage_relative_path')
            ? attachment_storage_relative_path($attachment)
            : '';
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        if ($relative_path === '') {
            $result['skipped']++;
            continue;
        }

        $absolute_path = BASE_PATH . '/' . $relative_path;
        if (!is_file($absolute_path)) {
            $result['missing']++;
            continue;
        }

        $key = $result['backup_prefix'] . '/tenants/' . $row_tenant_id . '/' . $relative_path;
        if ($dry_run) {
            $result['skipped']++;
            continue;
        }

        $body = file_get_contents($absolute_path);
        if ($body === false) {
            $result['errors'][] = 'Unable to read attachment #' . (int) ($attachment['id'] ?? 0);
            continue;
        }

        $mime = trim((string) ($attachment['mime_type'] ?? ''));
        storage_r2_request('PUT', $key, $body, $mime !== '' ? $mime : 'application/octet-stream');
        $result['uploaded']++;
    }

    $result['ok'] = empty($result['errors']);
} catch (Throwable $e) {
    $result['errors'][] = $e->getMessage();
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 2);
}

echo '[attachment-backup] status=' . (!empty($result['ok']) ? 'ok' : 'failed')
    . ' checked=' . $result['checked']
    . ' uploaded=' . $result['uploaded']
    . ' skipped=' . $result['skipped']
    . ' missing=' . $result['missing']
    . ' prefix=' . $result['backup_prefix'] . PHP_EOL;

if (empty($result['ok'])) {
    fwrite(STDERR, '[attachment-backup] ERROR: ' . implode('; ', $result['errors']) . PHP_EOL);
    exit(2);
}

exit(0);
