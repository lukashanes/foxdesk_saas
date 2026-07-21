<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/ticket-detail-source.php';

require_once $root . '/includes/ticket-crud-functions.php';
require_once $root . '/includes/modules/tickets/ticket-list-filters.php';

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        fwrite(STDERR, 'Unable to read ' . $path . PHP_EOL);
        exit(1);
    }
    return $contents;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$normalized = normalize_ticket_tags(" #Urgent, urgent; Paid\nwaiting  client, , #Needs review ");
$assert($normalized === 'Urgent, Paid, waiting client, Needs review', 'Tag normalization must trim, strip #, collapse spaces and dedupe case-insensitively.');

$array_normalized = normalize_ticket_tags(['Bug', ' bug ', '#Ops', 'ops', 'Needs  review'], true);
$assert($array_normalized === ['Bug', 'Ops', 'Needs review'], 'Array tag normalization must match string normalization.');
$assert(get_ticket_tags_array('Alpha, alpha, Beta') === ['Alpha', 'Beta'], 'Ticket tag array helper must return normalized unique tags.');
$assert(normalize_ticket_tags(' , ; ') === '', 'Empty tag input must normalize to an empty string.');

$filter_tags = ticket_list_normalize_tag_filters(['#Urgent', 'urgent', 'paid support', '']);
$assert($filter_tags === ['Urgent', 'paid support'], 'Ticket list tag filters must normalize arrays consistently.');

$state = ticket_list_filter_state_from_request([
    'tags' => ' #Urgent, urgent, paid ',
    'sort' => 'tags',
], [], false, ['tags_supported' => true, 'archive_supported' => true]);
$assert($state['filters']['tags'] === ['Urgent', 'paid'], 'Supported tag filters must enter ticket query filters.');
$assert($state['sort'] === 'tags', 'Tag sort must be allowed when tag support exists.');

$unsupported_state = ticket_list_filter_state_from_request([
    'tags' => 'urgent',
    'sort' => 'tags',
], [], false, ['tags_supported' => false, 'archive_supported' => true]);
$assert(!isset($unsupported_state['filters']['tags']), 'Unsupported tag filters must not enter ticket query filters.');
$assert($unsupported_state['sort'] === 'newest', 'Tag sort must fall back when tags are unsupported.');

$ticket_handler = $read('includes/api/ticket-handler.php');
$router = $read('includes/api/router.php');
$detail_js = ticket_detail_browser_source($root);
$ticket_detail = $read('pages/ticket-detail.php');
$sidebar = $read('includes/components/ticket-detail-sidebar.php');
$new_ticket = new_ticket_composed_source($root);
$bulk_actions = $read('includes/modules/tickets/ticket-bulk-actions.php');

$assert(str_contains($router, "'get-tags' => 'api_get_tags'"), 'API router must expose get-tags.');
$assert(str_contains($router, "'update-tags' => 'api_update_tags'"), 'API router must expose update-tags.');
$assert(str_contains($ticket_handler, 'function api_get_tags'), 'Ticket API must define tag listing.');
$assert(str_contains($ticket_handler, 'function api_update_tags'), 'Ticket API must define tag updates.');
$assert(str_contains($ticket_handler, 'build_ticket_visibility_filters_for_user'), 'Tag autocomplete must respect ticket visibility.');
$assert(str_contains($ticket_handler, 'normalize_ticket_tags($row[\'tags\'], true)'), 'Tag autocomplete must use the shared tag normalizer.');
$assert(str_contains($ticket_handler, 'require_csrf_token(true)'), 'Tag update API must require CSRF.');
$assert(str_contains($ticket_handler, '!can_see_ticket($ticket, $user) || !can_edit_ticket($ticket, $user)'), 'Tag update API must enforce view and edit permissions.');
$assert(str_contains($ticket_handler, 'update_ticket_with_history($ticket_id, $update_data, $user[\'id\'])'), 'Tag updates must go through ticket history.');
$assert(str_contains($ticket_handler, 'log_activity($ticket_id, $user[\'id\'], \'ticket_edited\', \'Tags updated\')'), 'Tag updates must leave activity evidence.');

$assert(str_contains($detail_js, "fetch('index.php?page=api&action=get-tags')"), 'Ticket detail JS must fetch tag suggestions.');
$assert(str_contains($detail_js, "fetch('index.php?page=api&action=update-tags'"), 'Ticket detail JS must save tags through the API.');
$assert(str_contains($detail_js, "formData.append('csrf_token', csrfToken)"), 'Ticket detail JS must submit CSRF for tag updates.');
$assert(str_contains($detail_js, 'encodeURIComponent(tag)'), 'Ticket detail JS must URL-encode tag filter links.');
$assert(str_contains($ticket_detail, "'filterUrlBase' => url('tickets'"), 'Ticket detail must provide tag filter base URL.');
$assert(str_contains($sidebar, 'id="sidebar-tags-edit-btn"'), 'Ticket sidebar must expose tag edit action.');
$assert(str_contains($sidebar, 'ticket-tag-pill'), 'Ticket sidebar must render tag filter pills.');
$assert(str_contains($new_ticket, "fetch('index.php?page=api&action=get-tags')"), 'New ticket page must reuse tag suggestions.');
$assert(str_contains($new_ticket, "'tag_chips[]'"), 'New ticket page must submit chip-selected tags.');

$assert(str_contains($bulk_actions, "'replace'"), 'Bulk tag update must support replace mode.');
$assert(str_contains($bulk_actions, "'append'"), 'Bulk tag update must support append mode.');
$assert(str_contains($bulk_actions, "'clear'"), 'Bulk tag update must support clear mode.');
$assert(str_contains($bulk_actions, "normalize_ticket_tags((\$ticket_item['tags'] ?? '') . ', ' . \$tags_input)"), 'Bulk append must reuse shared tag normalization.');

echo "Ticket tags contract OK\n";
