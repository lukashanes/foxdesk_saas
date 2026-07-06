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
    'ticket_list',
    'ticket_detail',
    'ticket_actions',
    'ticket_create_options',
    'update_ticket',
    'create_ticket',
    'add_comment',
    'attachment_metadata',
    'ticket_timer',
    'timer_action',
    'client_overview',
    'reporting_review',
    'notifications',
    'notification_read_state',
    'mobile_session',
    'upload',
] as $resource) {
    $assert(str_contains($contract, "'" . $resource . "'"), "Frozen key registry missing {$resource}.");
}

foreach ([
    "'app-shell' => 'api_app_shell'",
    "'app-home' => 'api_app_home'",
    "'app-ticket-list' => 'api_app_ticket_list'",
    "'app-ticket-detail' => 'api_app_ticket_detail'",
    "'app-ticket-actions' => 'api_app_ticket_actions'",
    "'app-ticket-create-options' => 'api_app_ticket_create_options'",
    "'app-update-ticket' => 'api_app_update_ticket'",
    "'app-create-ticket' => 'api_app_create_ticket'",
    "'app-add-comment' => 'api_app_add_comment'",
    "'app-add-comment-with-time' => 'api_app_add_comment_with_time'",
    "'app-attachment-metadata' => 'api_app_attachment_metadata'",
    "'app-ticket-timer' => 'api_app_ticket_timer'",
    "'app-ticket-timer-action' => 'api_app_ticket_timer_action'",
    "'app-client-overview' => 'api_app_client_overview'",
    "'app-reporting-review' => 'api_app_reporting_review'",
    "'app-notifications' => 'api_app_notifications'",
    "'app-notification-read-state' => 'api_app_notification_read_state'",
    "'mobile-refresh' => 'api_mobile_refresh'",
    "'mobile-logout' => 'api_mobile_logout'",
    "'upload' => 'api_upload'",
    "'global-search' => 'api_global_search'",
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
    'Ticket List',
    'Ticket Detail',
    'Ticket Actions',
    'Ticket Create Options',
    'Update Ticket',
    'Create Ticket',
    'Add Comment',
    'Add Comment With Time',
    'Upload Attachment Or Editor Image',
    'Attachment Metadata',
    'Client Overview',
    'Global Search',
    'Timer Action',
    'Push Notification Payload',
    'Notification Read State',
    'First iOS Beta Boundary',
] as $section) {
    $assert(str_contains($docs, $section), "Native API docs missing section: {$section}");
}

$assert(str_contains($docs, '"ticket_id": 123'), 'Native API docs must keep ticket_id in APNs examples.');
$assert(str_contains($docs, 'APNS_TEAM_ID'), 'Native API docs must keep APNs credential documentation.');

echo "Native app API freeze contract OK\n";
