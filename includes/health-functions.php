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
