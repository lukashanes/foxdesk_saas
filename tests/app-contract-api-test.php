<?php

$root = dirname(__DIR__);

$router = file_get_contents($root . '/includes/api/router.php');
$handler = file_get_contents($root . '/includes/api/app-handler.php');
$bootstrap = file_get_contents($root . '/includes/modules/bootstrap.php');
$appContract = file_get_contents($root . '/includes/modules/app/app-contract.php');
$billingReview = file_get_contents($root . '/includes/modules/reports/billing-review.php');
$notificationFunctions = file_get_contents($root . '/includes/notification-functions.php');

if ($router === false || $handler === false || $bootstrap === false || $appContract === false || $billingReview === false || $notificationFunctions === false) {
    fwrite(STDERR, "Unable to read app contract API files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

foreach ([
    "'app-shell' => 'api_app_shell'",
    "'app-home' => 'api_app_home'",
    "'app-ticket-list' => 'api_app_ticket_list'",
    "'app-ticket-detail' => 'api_app_ticket_detail'",
    "'app-ticket-actions' => 'api_app_ticket_actions'",
    "'app-attachment-metadata' => 'api_app_attachment_metadata'",
    "'app-ticket-timer' => 'api_app_ticket_timer'",
    "'app-ticket-timer-action' => 'api_app_ticket_timer_action'",
    "'app-client-overview' => 'api_app_client_overview'",
    "'app-reporting-review' => 'api_app_reporting_review'",
    "'app-notifications' => 'api_app_notifications'",
    "'app-notifications-summary' => 'api_app_notifications_summary'",
    "'app-notification-read-state' => 'api_app_notification_read_state'",
    "'app-tenant-state' => 'api_app_tenant_state'",
] as $route) {
    $assert(str_contains($router, $route), 'Missing app contract route: ' . $route);
}

foreach ([
    'function api_app_contract_envelope',
    'function api_app_contract_success',
    "'data' => \$data",
    "'errors' => []",
    'app_contract_schema_version()',
    'function api_app_require_write_auth',
    'empty($GLOBALS[\'is_mobile_token_auth\'])',
    'function api_app_require_timer_functions',
    'function api_app_ticket_list()',
    'ticket_list_view_apply_filters',
    'app_contract_ticket_filters_from_request($_GET, $user, $limit, $offset)',
	    "array_map('app_contract_ticket_list_item', get_tickets(\$filters))",
	    'function api_app_client_overview()',
	    'client_overview($organization_id, $view)',
	    'app_contract_client_overview_payload($overview, $view)',
	    'function api_app_reporting_review()',
    'billing_review_payload($filters, $user, $limit, $offset)',
    'function api_app_attachment_metadata()',
    'app_contract_attachment_payload($attachment)',
    'function api_app_ticket_timer()',
    'function api_app_ticket_timer_action()',
    'function api_app_notifications()',
    'function api_app_notifications_summary()',
    'get_user_notifications((int) $user[\'id\'], $limit, 0, true)',
    'function api_app_notification_read_state()',
    'mark_notification_unread',
    'function api_app_tenant_state()',
    'billing_workspace_access_state($tenant)',
    'billing_tenant_billing_action_state($tenant, $access_state)',
    'billing_tenant_usage($tenant_id)',
] as $needle) {
    $assert(str_contains($handler, $needle), 'App handler missing contract behavior: ' . $needle);
}

$assert(str_contains($handler, "'app_shell' => \$app_shell"), 'app-shell must preserve legacy top-level payload.');
$assert(str_contains($handler, "'home' => \$home"), 'app-home must preserve legacy top-level payload.');
$assert(str_contains($handler, "'actions' => app_contract_ticket_actions(\$ticket, \$user)"), 'ticket detail must expose the shared action model.');
$assert(str_contains($bootstrap, "/app/app-contract.php"), 'Bootstrap must load app contract read models.');

foreach ([
    'function app_contract_schema_version',
    'function app_contract_frozen_response_keys',
    'function app_contract_plain_text',
    'function app_contract_text_excerpt',
    'function app_contract_ticket_payload',
    "'worked_minutes'",
    "'worked_label'",
    'function app_contract_ticket_list_item',
    'function app_contract_ticket_filters_from_request',
    'build_ticket_visibility_filters_for_user',
    'function app_contract_attachment_payload',
    'function app_contract_attachment_can_preview',
    "'storage_driver'",
    "'preview_url'",
    "'can_preview'",
    'function app_contract_ticket_actions',
    'ticket_detail_primary_actions',
	    'unset($action[\'onclick\']',
	    'function app_contract_client_contact',
	    'function app_contract_client_overview_payload',
	    "\$organization['contact_email'] ?? \$organization['email']",
	    "'minutes_label'",
	    "'billable_amount_label'",
    'function app_contract_notification_summary_item',
    "'ticket_hash'",
    'function app_contract_tenant_payload',
    "'billing_override_reason'",
] as $needle) {
    $assert(str_contains($appContract, $needle), 'App contract module missing behavior: ' . $needle);
}

foreach ([
    't.hash AS ticket_hash',
    'LEFT JOIN tickets t ON t.id = n.ticket_id',
] as $needle) {
    $assert(str_contains($notificationFunctions, $needle), 'Notification read model missing native ticket hash behavior: ' . $needle);
}

require_once $root . '/includes/modules/app/app-contract.php';
$plainText = app_contract_plain_text('<p>First sentence.</p><ul><li>Second item</li><li>Third item</li></ul>');
$assert($plainText === "First sentence.\nSecond item\nThird item", 'Native ticket text must preserve readable block spacing.');
$excerpt = app_contract_text_excerpt('<p>First sentence.</p><p>Second sentence.</p>', 200);
$assert($excerpt === 'First sentence. Second sentence.', 'Native ticket previews must collapse blocks into readable spaces.');

foreach ([
    'function billing_review_filters_from_request',
    'function billing_review_payload',
    'function billing_review_entry_api_payload',
    'WHERE t.tenant_id = ?',
    'billing_review_adjustment_actions()',
    'billing_review_bulk_adjustment_actions()',
    "'pagination' => ['limit' => \$limit, 'offset' => \$offset, 'has_more' => \$has_more]",
] as $needle) {
    $assert(str_contains($billingReview, $needle), 'Billing review API read model missing behavior: ' . $needle);
}

echo "App contract API OK\n";
