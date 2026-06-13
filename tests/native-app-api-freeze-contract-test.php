<?php

$root = dirname(__DIR__);

$contract = file_get_contents($root . '/includes/modules/app/app-contract.php');
$handler = file_get_contents($root . '/includes/api/app-handler.php');
$router = file_get_contents($root . '/includes/api/router.php');
$docs = file_get_contents($root . '/docs/NATIVE_APP_API.md');

if ($contract === false || $handler === false || $router === false || $docs === false) {
    fwrite(STDERR, "Unable to read native app API freeze files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($contract, 'function app_contract_schema_version'), 'Native API schema version helper is missing.');
$assert(str_contains($contract, 'function app_contract_frozen_response_keys'), 'Frozen response key registry is missing.');

foreach ([
    'app_shell',
    'app_home',
    'ticket_detail',
    'add_comment',
    'attachment_metadata',
    'ticket_timer',
    'timer_action',
    'notifications',
    'notification_read_state',
    'mobile_session',
] as $resource) {
    $assert(str_contains($contract, "'" . $resource . "'"), "Frozen key registry missing {$resource}.");
}

foreach ([
    "'app-shell' => 'api_app_shell'",
    "'app-home' => 'api_app_home'",
    "'app-ticket-detail' => 'api_app_ticket_detail'",
    "'app-add-comment' => 'api_app_add_comment'",
    "'app-attachment-metadata' => 'api_app_attachment_metadata'",
    "'app-ticket-timer' => 'api_app_ticket_timer'",
    "'app-ticket-timer-action' => 'api_app_ticket_timer_action'",
    "'app-notifications' => 'api_app_notifications'",
    "'app-notification-read-state' => 'api_app_notification_read_state'",
    "'mobile-refresh' => 'api_mobile_refresh'",
    "'mobile-logout' => 'api_mobile_logout'",
] as $route) {
    $assert(str_contains($router, $route), "Native API route missing: {$route}");
}

$assert(str_contains($handler, 'api_app_contract_success'), 'App API must use the shared response envelope.');
$assert(str_contains($handler, 'api_app_require_write_auth()'), 'App write endpoints must use shared write auth.');
$assert(str_contains($handler, 'empty($GLOBALS[\'is_mobile_token_auth\'])'), 'Mobile Bearer auth must stay independent from browser CSRF.');
$assert(str_contains($handler, 'app_contract_attachment_payload'), 'Attachment metadata must use the shared attachment contract.');
$assert(str_contains($handler, 'mark_notification_unread'), 'Native notifications must support unread state for one notification.');

foreach ([
    'Response Envelope',
    'Mobile access tokens are independent from browser cookies',
    'App Shell',
    'App Home',
    'Ticket Detail',
    'Add Comment',
    'Attachment Metadata',
    'Timer Action',
    'Notification Read State',
] as $section) {
    $assert(str_contains($docs, $section), "Native API docs missing section: {$section}");
}

echo "Native app API freeze contract OK\n";
