<?php
/**
 * Health checks for uptime monitoring and deployment smoke tests.
 */

function health_absolute_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return BASE_PATH;
    }

    if (preg_match('#^([A-Za-z]:)?[\\\\/]#', $path)) {
        return $path;
    }

    return rtrim(BASE_PATH, '/\\') . DIRECTORY_SEPARATOR . $path;
}

function health_directory_check(string $label, string $path): array
{
    $absolute = health_absolute_path($path);

    return [
        'name' => $label,
        'path' => $path,
        'exists' => is_dir($absolute),
        'writable' => is_dir($absolute) && is_writable($absolute),
    ];
}

function health_env_bool(string $name, bool $default = false): bool
{
    if (defined($name)) {
        $value = constant($name);
    } else {
        $value = getenv($name);
    }

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function foxdesk_health_status(): array
{
    $checks = [];
    $status = 'ok';

    try {
        db_fetch_one("SELECT 1");
        $checks['db'] = ['ok' => true];
    } catch (Throwable $e) {
        $checks['db'] = ['ok' => false];
        $status = 'error';
    }

    $upload_check = health_directory_check('uploads', defined('UPLOAD_DIR') ? (string) UPLOAD_DIR : 'uploads/');
    $checks['uploads'] = $upload_check;
    if (empty($upload_check['exists']) || empty($upload_check['writable'])) {
        $status = 'error';
    }

    $storage_driver = function_exists('storage_driver') ? storage_driver() : strtolower((string) (defined('STORAGE_DRIVER') ? STORAGE_DRIVER : 'local'));
    $checks['storage_driver'] = $storage_driver;
    if ($storage_driver === 'local') {
        $storage_check = health_directory_check('storage', 'storage');
        $checks['storage'] = $storage_check;
        if (empty($storage_check['exists']) || empty($storage_check['writable'])) {
            $status = 'error';
        }
    } elseif ($storage_driver === 'r2') {
        $r2_check = [
            'name' => 'r2',
            'configured' => false,
            'roundtrip' => 'skipped',
            'ok' => false,
            'bucket' => function_exists('storage_r2_bucket') ? storage_r2_bucket() : '',
            'endpoint_configured' => function_exists('storage_r2_endpoint') && storage_r2_endpoint() !== '',
            'access_key_configured' => function_exists('storage_r2_access_key') && storage_r2_access_key() !== '',
            'secret_key_configured' => function_exists('storage_r2_secret_key') && storage_r2_secret_key() !== '',
        ];

        try {
            if (!function_exists('storage_r2_assert_configured')) {
                throw new RuntimeException('R2 storage helper is not loaded.');
            }
            storage_r2_assert_configured();
            $r2_check['configured'] = true;
            $r2_check['ok'] = true;

            if (health_env_bool('FOXDESK_HEALTH_STORAGE_MUTATION', false) && function_exists('storage_r2_healthcheck')) {
                $roundtrip = storage_r2_healthcheck(1, false);
                $r2_check['roundtrip'] = !empty($roundtrip['ok']) ? 'passed' : 'failed';
                $r2_check['object_key'] = $roundtrip['object_key'] ?? null;
                $r2_check['tenant_prefixed'] = (bool) ($roundtrip['tenant_prefixed'] ?? false);
                $r2_check['deleted'] = (bool) ($roundtrip['deleted'] ?? false);
                $r2_check['ok'] = !empty($roundtrip['ok']);
                if (empty($roundtrip['ok'])) {
                    $r2_check['error'] = $roundtrip['error'] ?? 'R2 roundtrip failed.';
                }
            }
        } catch (Throwable $e) {
            $r2_check['error'] = $e->getMessage();
        }

        $checks['storage_r2'] = $r2_check;
        if (empty($r2_check['ok'])) {
            $status = 'error';
        }
    } else {
        $checks['storage_unknown'] = [
            'name' => 'storage_driver',
            'ok' => false,
            'driver' => $storage_driver,
        ];
        $status = 'error';
    }

    $checks['php'] = [
        'version' => PHP_VERSION,
        'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
    ];
    if (empty($checks['php']['ok'])) {
        $status = 'error';
    }

    return [
        'status' => $status,
        'version' => defined('APP_VERSION') ? APP_VERSION : '',
        'host' => $_SERVER['HTTP_HOST'] ?? '',
        'mode' => function_exists('foxdesk_is_platform_host') && foxdesk_is_platform_host() ? 'platform' : 'workspace',
        'db' => !empty($checks['db']['ok']),
        'checks' => $checks,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
}
