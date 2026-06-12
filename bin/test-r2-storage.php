<?php
/**
 * CLI entrypoint: test Cloudflare R2 storage roundtrip.
 *
 * Usage:
 *   php bin/test-r2-storage.php
 *   php bin/test-r2-storage.php --json
 *   php bin/test-r2-storage.php --keep
 *   php bin/test-r2-storage.php --tenant-id=13
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
require_once BASE_PATH . '/includes/storage-functions.php';

$opts = getopt('', ['json', 'keep', 'tenant-id:']);
$json = array_key_exists('json', $opts);
$keep = array_key_exists('keep', $opts);
$tenant_id = max(1, (int) ($opts['tenant-id'] ?? 1));

$result = [
    'ok' => false,
    'driver' => storage_driver(),
    'bucket' => storage_r2_bucket(),
    'endpoint_configured' => storage_r2_endpoint() !== '',
    'access_key_configured' => storage_r2_access_key() !== '',
    'secret_key_configured' => storage_r2_secret_key() !== '',
    'object_key' => null,
    'stored_object_key' => null,
    'tenant_id' => $tenant_id,
    'tenant_prefixed' => false,
    'deleted' => false,
    'stored_object_deleted' => false,
    'error' => null,
];

try {
    $health = storage_r2_healthcheck($tenant_id, $keep);
    $result['object_key'] = $health['object_key'] ?? null;
    $result['tenant_prefixed'] = (bool) ($health['tenant_prefixed'] ?? false);
    $result['deleted'] = (bool) ($health['deleted'] ?? false);
    if (empty($health['ok'])) {
        throw new RuntimeException((string) ($health['error'] ?? 'R2 health check failed.'));
    }

    $payload = 'FoxDesk R2 test ' . gmdate('c') . ' ' . bin2hex(random_bytes(8));
    $tmp_path = tempnam(sys_get_temp_dir(), 'foxdesk-r2-');
    if ($tmp_path === false) {
        throw new RuntimeException('Unable to create temporary test file.');
    }
    file_put_contents($tmp_path, $payload);
    $storage = storage_store_file($tmp_path, 'uploads/r2-storage-function-test.txt', 'text/plain; charset=UTF-8', $tenant_id);
    $stored_key = (string) ($storage['key'] ?? '');
    $result['stored_object_key'] = $stored_key;

    if (($storage['driver'] ?? '') !== 'r2') {
        throw new RuntimeException('storage_store_file did not use R2.');
    }
    if (!str_starts_with($stored_key, 'tenants/' . $tenant_id . '/')) {
        throw new RuntimeException('storage_store_file object key is not tenant-prefixed.');
    }

    $stored_read = storage_read_object([
        'storage_driver' => 'r2',
        'storage_key' => $stored_key,
    ]);
    if (!hash_equals($payload, (string) $stored_read)) {
        throw new RuntimeException('storage_read_object content did not match uploaded content.');
    }

    if (!$keep) {
        storage_delete_object([
            'storage_driver' => 'r2',
            'storage_key' => $stored_key,
        ]);
        $result['stored_object_deleted'] = true;
    }

    $result['ok'] = true;
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($result['ok'] ? 0 : 2);
}

echo '[r2-storage] driver=' . $result['driver']
    . ' bucket=' . ($result['bucket'] ?: '-')
    . ' status=' . ($result['ok'] ? 'ok' : 'failed')
    . ' tenant=' . $result['tenant_id']
    . ' object=' . ($result['object_key'] ?: '-') . PHP_EOL;

if (!$result['ok']) {
    fwrite(STDERR, '[r2-storage] ERROR: ' . ($result['error'] ?: 'Unknown error') . PHP_EOL);
    exit(2);
}

exit(0);
