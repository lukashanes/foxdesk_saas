<?php

$root = dirname(__DIR__);

define('BASE_PATH', $root);
define('APP_HOST', 'app.foxdesk.net');
define('PLATFORM_HOST', 'platform.foxdesk.net');
define('APP_URL', 'https://app.foxdesk.net');
define('PLATFORM_URL', 'https://platform.foxdesk.net');

require_once $root . '/includes/session-bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$_SERVER['HTTP_HOST'] = 'platform.foxdesk.net';
$assert(foxdesk_is_platform_host(), 'platform.foxdesk.net must be detected as the platform host.');
$assert(foxdesk_session_cookie_name() === 'FOXDESKPLATFORM', 'Platform host must use the platform session cookie.');

$_SERVER['HTTP_HOST'] = 'app.foxdesk.net';
$assert(foxdesk_is_workspace_host(), 'app.foxdesk.net must be detected as the workspace host.');
$assert(!foxdesk_is_platform_host(), 'app.foxdesk.net must not be detected as the platform host.');
$assert(foxdesk_session_cookie_name() === 'FOXDESKWORKSPACE', 'Workspace host must use the workspace session cookie.');

$caddy = file_get_contents($root . '/docker/caddy/Caddyfile');
$compose = file_get_contents($root . '/docker-compose.prod.yml');
$index = file_get_contents($root . '/index.php');
$functions = file_get_contents($root . '/includes/functions.php');
$login = file_get_contents($root . '/pages/login.php');

$assert($caddy !== false && str_contains($caddy, '{$PLATFORM_HOST}'), 'Caddy must route PLATFORM_HOST.');
$assert($compose !== false && str_contains($compose, 'PLATFORM_HOST:'), 'Production compose must pass PLATFORM_HOST to Caddy.');
$assert($index !== false && str_contains($index, 'foxdesk_is_platform_host()'), 'Index router must check platform host.');
$assert($functions !== false && str_contains($functions, 'foxdesk_platform_url'), 'URL helper must support platform host links.');
$assert($login !== false && str_contains($login, 'platform_login_rejected'), 'Login must reject workspace users on the platform host.');

echo "Platform/workspace host contract OK\n";
