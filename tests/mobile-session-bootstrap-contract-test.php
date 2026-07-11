<?php

$root = dirname(__DIR__);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer fdm_at_test';
$_SESSION = ['browser_context_must_not_leak' => true];

require_once $root . '/includes/session-bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(foxdesk_request_uses_bearer_auth(), 'Bearer authorization must be detected before session bootstrap.');
$assert(foxdesk_bootstrap_session(false), 'Bearer request bootstrap must initialize an ephemeral request context.');
$assert(session_status() === PHP_SESSION_NONE, 'Bearer requests must not start a persistent PHP session.');
$assert($_SESSION === [], 'Bearer requests must not inherit browser session state.');

unset($_SERVER['HTTP_AUTHORIZATION']);
$_SERVER['REQUEST_URI'] = '/api/mobile/v1/login';
$_SESSION = ['browser_context_must_not_leak' => true];
$assert(foxdesk_request_is_native_mobile_api(), 'Native mobile login and refresh paths must be detected without a bearer header.');
$assert(foxdesk_bootstrap_session(false), 'Native mobile login must initialize an ephemeral request context.');
$assert(session_status() === PHP_SESSION_NONE, 'Native mobile login and refresh must not start a persistent PHP session.');
$assert($_SESSION === [], 'Native mobile paths must not inherit browser session state.');

echo "Mobile bearer session bootstrap contract OK\n";
