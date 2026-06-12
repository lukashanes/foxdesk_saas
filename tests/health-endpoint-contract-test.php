<?php

$root = dirname(__DIR__);

$index = file_get_contents($root . '/index.php');
$health = file_get_contents($root . '/includes/health-functions.php');
$production_smoke = file_get_contents($root . '/tests/smoke/production-smoke.js');
$local_smoke = file_get_contents($root . '/tests/smoke/local-smoke.js');

if ($index === false || $health === false || $production_smoke === false || $local_smoke === false) {
    fwrite(STDERR, "Unable to read health endpoint files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($index, "require_once BASE_PATH . '/includes/health-functions.php'"), 'Health endpoint must use the shared health helper.');
$assert(str_contains($index, 'foxdesk_health_status()'), 'Health endpoint must render foxdesk_health_status().');
$assert(str_contains($index, 'http_response_code(503)'), 'Health endpoint must return 503 when checks fail.');

$assert(str_contains($health, 'function foxdesk_health_status'), 'Shared health status helper is missing.');
$assert(str_contains($health, 'db_fetch_one("SELECT 1")'), 'Health helper must check database connectivity.');
$assert(str_contains($health, 'health_directory_check'), 'Health helper must check writable directories.');
$assert(str_contains($health, "'uploads'"), 'Health helper must report upload storage state.');
$assert(str_contains($health, "'storage_driver'"), 'Health helper must report storage driver state.');
$assert(str_contains($health, "'storage_r2'"), 'Health helper must report R2 storage state when R2 is configured.');
$assert(str_contains($health, 'storage_r2_assert_configured()'), 'Health helper must validate R2 configuration.');
$assert(str_contains($health, 'storage_r2_healthcheck(1, false)'), 'Health helper must support the R2 write/read/delete roundtrip.');
$assert(str_contains($health, 'FOXDESK_HEALTH_STORAGE_MUTATION'), 'R2 write/read/delete health checks must be opt-in.');
$assert(str_contains($health, "version_compare(PHP_VERSION, '8.1.0'"), 'Health helper must enforce the PHP runtime baseline.');
$assert(str_contains($health, "'checks' => \$checks"), 'Health JSON must include machine-readable checks.');

$assert(str_contains($production_smoke, 'page=health'), 'Production smoke must include health endpoint.');
$assert(str_contains($local_smoke, 'page=health'), 'Local smoke must include health endpoint.');

echo "Health endpoint contract OK\n";
