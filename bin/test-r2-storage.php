<?php
/**
 * CLI entrypoint: test Cloudflare R2 storage roundtrip.
 *
 * Usage:
 *   php bin/test-r2-storage.php
 *   php bin/test-r2-storage.php --json
 *   php bin/test-r2-storage.php --keep
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

$opts = getopt('', ['json', 'keep']);
$json = array_key_exists('json', $opts);
$keep = array_key_exists('keep', $opts);

$result = [
    'ok' => false,
    'driver' => storage_driver(),
    'bucket' => storage_r2_bucket(),
    'endpoint_configured' => storage_r2_endpoint() !== '',
    'access_key_configured' => storage_r2_access_key() !== '',
    'secret_key_configured' => storage_r2_secret_key() !== '',
    'object_key' => null,
    'deleted' => false,
    'error' => null,
];

try {
    storage_r2_assert_configured();

    $payload = 'FoxDesk R2 test ' . gmdate('c') . ' ' . bin2hex(random_bytes(8));
    $object_key = 'healthchecks/r2-test-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
    $result['object_key'] = $object_key;

    storage_r2_request('PUT', $object_key, $payload, 'text/plain; charset=UTF-8');
    $read = storage_r2_request('GET', $object_key)['body'];

    if (!hash_equals($payload, $read)) {
        throw new RuntimeException('R2 read content did not match uploaded content.');
    }

    if (!$keep) {
        storage_r2_request('DELETE', $object_key);
        $result['deleted'] = true;
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
    . ' object=' . ($result['object_key'] ?: '-') . PHP_EOL;

if (!$result['ok']) {
    fwrite(STDERR, '[r2-storage] ERROR: ' . ($result['error'] ?: 'Unknown error') . PHP_EOL);
    exit(2);
}

exit(0);
