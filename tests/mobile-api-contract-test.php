<?php

$root = dirname(__DIR__);

$schema = file_get_contents($root . '/includes/schema.sql');
$router = file_get_contents($root . '/includes/api/router.php');
$auth = file_get_contents($root . '/includes/auth.php');
$handler = file_get_contents($root . '/includes/api/mobile-handler.php');
$tenant = file_get_contents($root . '/includes/tenant-functions.php');

if ($schema === false || $router === false || $auth === false || $handler === false || $tenant === false) {
    fwrite(STDERR, "Unable to read mobile API files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS mobile_auth_challenges'), 'Mobile 2FA challenge table is missing.');
$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS mobile_sessions'), 'Mobile sessions table is missing.');
$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS mobile_devices'), 'Mobile devices table is missing.');
$assert(str_contains($tenant, "'mobile_auth_challenges'"), 'Mobile challenge table must be tenant-owned.');
$assert(str_contains($tenant, "'mobile_sessions'"), 'Mobile sessions table must be tenant-owned.');
$assert(str_contains($tenant, "'mobile_devices'"), 'Mobile devices table must be tenant-owned.');

$assert(str_contains($auth, 'function bearer_token_from_request'), 'Shared Bearer token parser is missing.');
$assert(str_contains($auth, 'function authenticate_mobile_session'), 'Mobile session authenticator is missing.');
$assert(str_contains($auth, "'mobile_session_id'"), 'Mobile authenticator must expose the session id.');

$assert(str_contains($router, "require_once __DIR__ . '/mobile-handler.php'"), 'API router must load mobile handler.');
$assert(str_contains($router, "'mobile-login'"), 'mobile-login route is missing.');
$assert(str_contains($router, "'mobile-verify-2fa'"), 'mobile-verify-2fa route is missing.');
$assert(str_contains($router, "'mobile-refresh'"), 'mobile-refresh route is missing.');
$assert(str_contains($router, "'mobile-register-device'"), 'mobile-register-device route is missing.');
$assert(str_contains($router, "'app-ticket-detail'"), 'app-ticket-detail route is missing.');
$assert(str_contains($router, "'app-add-comment'"), 'app-add-comment route is missing.');
$assert(str_contains($router, "'app-attachment-metadata'"), 'app-attachment-metadata route is missing.');
$assert(str_contains($router, "'app-ticket-timer-action'"), 'app-ticket-timer-action route is missing.');
$assert(str_contains($router, "'app-notification-read-state'"), 'app-notification-read-state route is missing.');
$assert(str_contains($router, 'authenticate_mobile_session()'), 'Router must authenticate mobile Bearer tokens.');
$assert(str_contains($router, "'mobile-login',"), 'mobile-login must be public before auth.');
$mobileBranch = "if (\$mobile_user) {\n                    \$GLOBALS['is_mobile_token_auth'] = true;\n                }";
$assert(str_contains($router, $mobileBranch), 'Mobile Bearer auth must not be flagged as API-token auth.');
$assert(str_contains($auth, "'app-tenant-state' => 'work:read'"), 'API tokens need a read scope for app tenant state.');

$assert(str_contains($handler, 'function api_mobile_login'), 'Mobile login handler is missing.');
$assert(str_contains($handler, 'function api_mobile_verify_2fa'), 'Mobile 2FA verification handler is missing.');
$assert(str_contains($handler, 'function api_mobile_refresh'), 'Mobile refresh handler is missing.');
$assert(str_contains($handler, 'function api_mobile_logout'), 'Mobile logout handler is missing.');
$assert(str_contains($handler, 'function api_mobile_register_device'), 'Mobile device registration handler is missing.');
$assert(str_contains($handler, "'fdm_at_'"), 'Mobile access token prefix is missing.');
$assert(str_contains($handler, "'fdm_rt_'"), 'Mobile refresh token prefix is missing.');
$assert(str_contains($handler, 'verify_backup_code'), 'Mobile 2FA must accept backup codes.');
$assert(str_contains($handler, 'app_shell_payload'), 'Mobile auth response should include app shell data.');
$assert(str_contains($handler, 'app_feed_payload'), 'Mobile auth response should include app home data.');

$appHandler = file_get_contents($root . '/includes/api/app-handler.php');
$assert($appHandler !== false, 'Unable to read app handler.');
$assert(str_contains($appHandler, 'function api_app_ticket_detail'), 'Native ticket detail handler is missing.');
$assert(str_contains($appHandler, 'function api_app_add_comment'), 'Native add comment handler is missing.');
$assert(str_contains($appHandler, 'api_app_resolve_ticket'), 'Native ticket endpoints must share access checks.');
$assert(str_contains($appHandler, 'can_see_ticket'), 'Native ticket endpoints must enforce ticket access.');
$assert(str_contains($appHandler, 'function api_app_require_write_auth'), 'Native write endpoints must share app write auth.');
$assert(str_contains($appHandler, 'require_csrf_token(true)'), 'Native write endpoints must keep CSRF for cookie sessions.');
$assert(str_contains($appHandler, 'empty($GLOBALS[\'is_mobile_token_auth\'])'), 'Mobile Bearer writes must not require browser CSRF.');
$assert(str_contains($appHandler, 'function api_app_attachment_metadata'), 'Native attachment metadata endpoint is missing.');
$assert(str_contains($appHandler, 'function api_app_ticket_timer_action'), 'Native timer action endpoint is missing.');
$assert(str_contains($appHandler, 'function api_app_notification_read_state'), 'Native notification read-state endpoint is missing.');

$nativeDocs = file_get_contents($root . '/docs/NATIVE_APP_API.md');
$assert($nativeDocs !== false, 'Native API docs are missing.');
$assert(str_contains($nativeDocs, 'Status: frozen for the first iOS and Android beta.'), 'Native API docs must declare the beta freeze.');
$assert(str_contains($nativeDocs, 'Authorization: Bearer fdm_at_'), 'Native API docs must document mobile Bearer auth.');
$assert(str_contains($nativeDocs, 'app-attachment-metadata'), 'Native API docs must document attachment metadata.');
$assert(str_contains($nativeDocs, 'app-tenant-state'), 'Native API docs must document tenant state.');
$assert(str_contains($nativeDocs, 'app-ticket-timer-action'), 'Native API docs must document timer actions.');
$assert(str_contains($nativeDocs, 'app-notification-read-state'), 'Native API docs must document notification read state.');

echo "Mobile API contract OK\n";
