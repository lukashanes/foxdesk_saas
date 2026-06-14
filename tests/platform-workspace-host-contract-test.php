<?php

$root = dirname(__DIR__);

define('BASE_PATH', $root);
define('APP_HOST', 'app.foxdesk.net');
define('PLATFORM_HOST', 'platform.foxdesk.net');
define('APP_MARKETING_HOST', 'foxdesk.net');
define('APP_URL', 'https://app.foxdesk.net');
define('PLATFORM_URL', 'https://platform.foxdesk.net');
define('APP_MARKETING_URL', 'https://foxdesk.net');

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

$_SERVER['HTTP_HOST'] = 'foxdesk.net';
$assert(foxdesk_is_marketing_host(), 'foxdesk.net must be detected as the public marketing host.');
$assert(foxdesk_session_cookie_name() === 'FOXDESKPUBLIC', 'Marketing host must use the public session cookie.');

$caddy = file_get_contents($root . '/docker/caddy/Caddyfile');
$compose = file_get_contents($root . '/docker-compose.prod.yml');
$index = file_get_contents($root . '/index.php');
$functions = file_get_contents($root . '/includes/functions.php');
$login = file_get_contents($root . '/pages/login.php');
$tenant = file_get_contents($root . '/includes/tenant-functions.php');

$assert($caddy !== false && str_contains($caddy, '{$PLATFORM_HOST}'), 'Caddy must route PLATFORM_HOST.');
$assert($compose !== false && str_contains($compose, 'PLATFORM_HOST:'), 'Production compose must pass PLATFORM_HOST to Caddy.');
$assert($index !== false && str_contains($index, 'foxdesk_is_platform_host()'), 'Index router must check platform host.');
$assert($index !== false && str_contains($index, 'foxdesk_is_marketing_host()'), 'Index router must guard public marketing host.');
$assert($functions !== false && str_contains($functions, 'foxdesk_platform_url'), 'URL helper must support platform host links.');
$assert($functions !== false && str_contains($functions, 'foxdesk_marketing_url'), 'URL helper must support public marketing host links.');
$assert($login !== false && str_contains($login, 'platform_login_rejected'), 'Login must reject workspace users on the platform host.');
$assert($login !== false && str_contains($login, 'foxdesk_remember_cookie_name()'), 'Login remember-me restore must use the host-specific remember cookie.');
$assert($login !== false && !str_contains($login, "!empty(\$_COOKIE['foxdesk_remember'])"), 'Login must not check the legacy hard-coded remember cookie.');
$assert($tenant !== false && str_contains($tenant, 'platform_login_rejected=1'), 'Platform admin guard must not redirect non-platform users back to platform.');
$assert($tenant !== false && str_contains($tenant, 'foxdesk_platform_url'), 'Platform admin guard must send non-platform-host attempts to the platform login.');

require_once $root . '/includes/functions.php';

$_SERVER['HTTP_HOST'] = 'foxdesk.net';
$assert(url('login') === 'https://app.foxdesk.net/index.php?page=login', 'Marketing login link must go to app.foxdesk.net.');
$assert(url('signup') === 'https://app.foxdesk.net/index.php?page=signup', 'Marketing signup link must go to app.foxdesk.net.');
$assert(url('platform') === 'https://platform.foxdesk.net/index.php?page=platform', 'Marketing platform link must go to platform.foxdesk.net.');

$_SERVER['HTTP_HOST'] = 'app.foxdesk.net';
$assert(url('cloud') === 'https://foxdesk.net/index.php?page=cloud', 'Workspace cloud link must go to foxdesk.net.');
$assert(url('platform') === 'https://platform.foxdesk.net/index.php?page=platform', 'Workspace platform link must go to platform.foxdesk.net.');

$_SERVER['HTTP_HOST'] = 'platform.foxdesk.net';
$assert(url('work') === 'https://app.foxdesk.net/index.php?page=work', 'Platform workspace links must go to app.foxdesk.net.');

echo "Platform/workspace host contract OK\n";
