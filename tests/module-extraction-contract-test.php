<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/ticket-list-source.php';
require_once __DIR__ . '/support/ticket-detail-source.php';
$functions = file_get_contents($root . '/includes/functions.php');
$helper = file_get_contents($root . '/includes/components/ticket-detail-surface.php');
$sidebar = file_get_contents($root . '/includes/components/ticket-detail-sidebar.php');
$composer = file_get_contents($root . '/includes/components/ticket-detail-composer.php');
$modals = file_get_contents($root . '/includes/components/ticket-detail-modals.php');
$detailContext = file_get_contents($root . '/includes/modules/tickets/ticket-detail-context.php');
$readModel = file_get_contents($root . '/includes/modules/tickets/ticket-detail-read-model.php');
$shareState = file_get_contents($root . '/includes/modules/tickets/ticket-share-state.php');
$ticketBulkActions = file_get_contents($root . '/includes/modules/tickets/ticket-bulk-actions.php');
$ticketListFilters = file_get_contents($root . '/includes/modules/tickets/ticket-list-filters.php');
$ticketRowViewModel = file_get_contents($root . '/includes/modules/tickets/ticket-row-view-model.php');
$ticketListAssets = file_get_contents($root . '/includes/components/ticket-list-assets.php');
$ticketListJs = file_get_contents($root . '/assets/js/ticket-list.js');
$ticketListDueDateJs = file_get_contents($root . '/assets/js/ticket-list-due-date.js');
$ticketListTimeJs = file_get_contents($root . '/assets/js/ticket-list-time.js');
$ticketDetailJs = ticket_detail_browser_source($root);
$bootstrap = file_get_contents($root . '/includes/modules/bootstrap.php');
$ticketPage = file_get_contents($root . '/pages/ticket-detail.php');
$ticketListPage = ticket_list_route_source($root);
$ticketListController = ticket_list_controller_source($root);
$ticketListView = ticket_list_view_source($root);
$ticketListBoard = ticket_list_board_source($root);
$ticketListTable = ticket_list_table_source($root);
$ticketListSurface = ticket_list_surface_source($root);
$clientPage = file_get_contents($root . '/pages/client.php');
$theme = file_get_contents($root . '/theme.css');
$header = file_get_contents($root . '/includes/header.php');
$headerJs = file_get_contents($root . '/assets/js/app-header.js');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($functions !== false, 'includes/functions.php must be readable.');
$assert($helper !== false, 'ticket detail surface helper must be readable.');
$assert($sidebar !== false, 'ticket detail sidebar component must be readable.');
$assert($composer !== false, 'ticket detail composer component must be readable.');
$assert($modals !== false, 'ticket detail modals component must be readable.');
$assert($detailContext !== false, 'ticket detail context module must be readable.');
$assert($readModel !== false, 'ticket detail read model must be readable.');
$assert($shareState !== false, 'ticket share state module must be readable.');
$assert($ticketBulkActions !== false, 'ticket bulk actions module must be readable.');
$assert($ticketListFilters !== false, 'ticket list filters module must be readable.');
$assert($ticketRowViewModel !== false, 'ticket row view model module must be readable.');
$assert($ticketListAssets !== false, 'ticket list assets component must be readable.');
$assert($ticketListJs !== false, 'ticket list JS asset must be readable.');
$assert($ticketListDueDateJs !== false, 'ticket list due-date JS asset must be readable.');
$assert($ticketListTimeJs !== false, 'ticket list time JS asset must be readable.');
$assert($ticketDetailJs !== false, 'ticket detail JS asset must be readable.');
$assert($bootstrap !== false, 'module bootstrap must be readable.');
$assert($ticketPage !== false, 'pages/ticket-detail.php must be readable.');
$assert($ticketListPage !== false, 'pages/tickets.php must be readable.');
$assert($clientPage !== false, 'pages/client.php must be readable.');
$assert($theme !== false, 'theme.css must be readable.');
$assert($header !== false, 'includes/header.php must be readable.');
$assert($headerJs !== false, 'assets/js/app-header.js must be readable.');

$assert(str_contains($functions, '/includes/components/ticket-detail-surface.php'), 'Ticket detail surface helper must be globally loaded.');
$assert(str_contains($bootstrap, '/tickets/ticket-detail-read-model.php'), 'Ticket detail read model must be loaded by module bootstrap.');
$assert(str_contains($bootstrap, '/tickets/ticket-share-state.php'), 'Ticket share state must be loaded by module bootstrap.');
$assert(str_contains($bootstrap, '/tickets/ticket-detail-context.php'), 'Ticket detail context must be loaded by module bootstrap.');
$assert(str_contains($bootstrap, '/tickets/ticket-bulk-actions.php'), 'Ticket bulk actions must be loaded by module bootstrap.');
$assert(str_contains($bootstrap, '/tickets/ticket-list-filters.php'), 'Ticket list filters must be loaded by module bootstrap.');
$assert(str_contains($bootstrap, '/tickets/ticket-row-view-model.php'), 'Ticket row view model must be loaded by module bootstrap.');

foreach ([
    'function ticket_detail_status_group',
    'function ticket_detail_status_pill_class',
    'function ticket_detail_primary_action_class',
    'function ticket_detail_render_status_pill',
] as $needle) {
    $assert(str_contains($helper, $needle), 'Ticket detail helper missing: ' . $needle);
}

foreach ([
    'function ticket_detail_context',
    'function ticket_detail_available_organizations',
    'function ticket_detail_tag_filter_url',
] as $needle) {
    $assert(str_contains($detailContext, $needle), 'Ticket detail context module missing: ' . $needle);
}

foreach ([
    'function ticket_detail_visible_comments',
    'function ticket_detail_visible_comment_ids',
    'function ticket_detail_visible_attachments',
    'function ticket_detail_initial_attachments',
    'function ticket_detail_comment_attachments',
    'function ticket_detail_build_timeline',
] as $needle) {
    $assert(str_contains($readModel, $needle), 'Ticket detail read model missing: ' . $needle);
}

foreach ([
    'function ticket_detail_share_status',
    'function ticket_detail_share_status_label',
    'function ticket_detail_share_status_class',
    'function ticket_detail_share_state',
] as $needle) {
    $assert(str_contains($shareState, $needle), 'Ticket share state module missing: ' . $needle);
}

foreach ([
    'function ticket_bulk_action_redirect_params',
    'function ticket_bulk_editable_tickets',
    'function ticket_handle_bulk_actions',
] as $needle) {
    $assert(str_contains($ticketBulkActions, $needle), 'Ticket bulk action module missing: ' . $needle);
}

foreach ([
    'function ticket_list_normalize_tag_filters',
    'function ticket_list_allowed_sorts',
    'function ticket_list_visual_view_from_request',
    'function ticket_list_filter_state_from_request',
] as $needle) {
    $assert(str_contains($ticketListFilters, $needle), 'Ticket list filter module missing: ' . $needle);
}

foreach ([
    'function ticket_registry_split_model',
    'function ticket_registry_kanban_model',
    'function ticket_registry_status_accent_class',
    'function ticket_registry_priority_badge_class',
] as $needle) {
    $assert(str_contains($ticketRowViewModel, $needle), 'Ticket row view model missing: ' . $needle);
}

$assert(str_contains($ticketListPage, 'ticket-list-page-controller.php'), 'Tickets route must delegate request handling.');
$assert(str_contains($ticketListPage, 'ticket-list-page.php'), 'Tickets route must delegate registry rendering.');
$assert(str_contains($ticketListController, 'ticket_list_filter_state_from_request($_GET, $_COOKIE, $is_archive)'), 'Ticket-list controller must consume extracted filter state.');
$assert(str_contains($ticketListController, 'ticket_handle_bulk_actions('), 'Ticket-list controller must delegate bulk actions.');
$assert(str_contains($ticketListView, 'ticket_registry_split_model($statuses, $tickets, $status_id, $ticket_list_view)'), 'Ticket-list view must consume row split model.');
$assert(str_contains($ticketListView, 'ticket_registry_kanban_model('), 'Ticket-list view must consume kanban model.');
$assert(str_contains($ticketListView, 'ticket-list-board.php'), 'Ticket-list view must delegate Kanban markup.');
$assert(str_contains($ticketListView, 'ticket-list-table.php'), 'Ticket-list view must delegate table markup.');
$assert(str_contains($ticketListBoard, 'data-kanban-scope="main"'), 'Ticket-list board component must own Kanban columns.');
$assert(str_contains($ticketListTable, 'tickets-table'), 'Ticket-list table component must own list rows.');
$assert(str_contains($ticketListPage, 'includes/components/ticket-list-assets.php'), 'Tickets page must delegate its scripts and templates.');
$assert(str_contains($ticketListAssets, 'assets/js/ticket-list.js'), 'Ticket list assets component must load extracted ticket list JS.');
$assert(str_contains($ticketListAssets, 'assets/js/ticket-list-due-date.js'), 'Ticket list assets component must load due-date behavior.');
$assert(str_contains($ticketListAssets, 'assets/js/ticket-list-time.js'), 'Ticket list assets component must load time behavior.');
$assert(!str_contains($ticketListSurface, '$collect_editable_tickets = function'), 'Ticket list must not own editable ticket collection.');
$assert(!str_contains($ticketListSurface, "isset(\$_POST['bulk_update'])"), 'Ticket list must not own bulk update handling.');
$assert(!str_contains($ticketListSurface, '$ticket_registry_allowed_status_groups'), 'Ticket list must not define status class closures inline.');
$assert(!str_contains($ticketListSurface, '$statuses_by_id = [];'), 'Ticket list must not rebuild status lookup inline.');
$assert(!str_contains($ticketListSurface, '$allowed_sorts ='), 'Ticket list must not own sort allow-list logic.');
$assert(!str_contains($ticketListSurface, 'normalize_ticket_tags($_GET'), 'Ticket list must not parse tag request filters inline.');
$assert(!str_contains($ticketListSurface, 'function applyHeaderSort'), 'Ticket list PHP must not own browser behavior.');
$assert(str_contains($ticketListJs, 'window.applyHeaderSort'), 'Ticket list JS must own header sort behavior.');
$assert(str_contains($ticketListDueDateJs, 'function bindDueDatePopover'), 'Due-date behavior must live in its focused module.');
$assert(str_contains($ticketListTimeJs, 'function bindInlineLogTime'), 'Time behavior must live in its focused module.');

foreach ([
    'data-ticket-detail-surface',
    'ticket_detail_render_status_pill($ticket, $statuses)',
    'ticket_detail_primary_action_class($action)',
    'ticket_detail_visible_comments($all_comments, is_agent())',
    'ticket_detail_visible_attachments($attachments, $visible_comment_ids, is_agent())',
    'ticket_detail_initial_attachments($attachments)',
    'ticket_detail_build_timeline($comments, $time_entries)',
    "ticket_detail_comment_attachments(\$attachments, (int) \$comment['id'])",
    'ticket_detail_context($ticket_id, $ticket, $user, $_SESSION)',
    'window.FoxDeskTicketDetailConfig',
    'assets/js/ticket-detail.js',
    "/includes/components/ticket-detail-sidebar.php",
    "/includes/components/ticket-detail-composer.php",
    "/includes/components/ticket-detail-modals.php",
    'ticket-primary-action-form',
    'ticket-primary-action__timer',
] as $needle) {
    $assert(str_contains($ticketPage, $needle), 'Ticket detail page must use extracted surface contract: ' . $needle);
}

$assert(!str_contains($ticketPage, "'ticket-primary-action ticket-primary-action--' . e("), 'Ticket action classes must not be rebuilt inline.');
$assert(!str_contains($ticketPage, "style=\"background-color: <?php echo e(\$ticket['status_color']); ?>15;"), 'Top status pill must not use inline DB colors.');
$assert(!str_contains($ticketPage, 'data-ticket-sidebar-surface'), 'Ticket sidebar surface must stay inside the sidebar component.');
$assert(!str_contains($ticketPage, 'data-ticket-composer-surface'), 'Ticket composer surface must stay inside the composer component.');
$assert(!str_contains($ticketPage, '<div id="edit-ticket-modal"'), 'Ticket modals must stay inside the modals component.');
$assert(!str_contains($ticketPage, 'array_filter($attachments, function ($attachment) use ($visible_comment_ids)'), 'Ticket attachment visibility must stay in the read model.');
$assert(!str_contains($ticketPage, '$timeline_items = [];'), 'Ticket timeline assembly must stay in the read model.');
$assert(!str_contains($ticketPage, '$latest_share = get_latest_ticket_share($ticket_id);'), 'Ticket share state must stay in the share-state module.');
$assert(!str_contains($ticketPage, '$organizations = [];'), 'Ticket detail organization options must stay in the context module.');
$assert(!str_contains($ticketPage, "array_filter(\$attachments, function (\$a) use (\$comment)"), 'Comment attachment lookup must stay in the read model.');
$assert(!str_contains($ticketPage, 'function openEditCommentModal'), 'Ticket detail JS behavior must stay in the ticket-detail JS asset.');
$assert(!str_contains($ticketPage, 'function openTicketTimeline'), 'Ticket timeline JS behavior must stay in the ticket-detail JS asset.');
$assert(!preg_match('/font-size:\s*clamp\(/', $ticketPage), 'Ticket detail page must not use viewport-scaled heading fonts.');
$assert(!preg_match('/font-size:\s*clamp\(/', $clientPage), 'Client page must not use viewport-scaled heading fonts.');

foreach ([
    'data-ticket-sidebar-surface',
    'ticket_detail_render_status_pill($ticket, $statuses)',
    'id="ticket-side-panel"',
] as $needle) {
    $assert(str_contains($sidebar, $needle), 'Ticket detail sidebar component missing: ' . $needle);
}

$assert(!str_contains($sidebar, "style=\"background-color: <?php echo e(\$ticket['status_color']); ?>20;"), 'Sidebar status pill must not use inline DB colors.');

foreach ([
    'data-ticket-composer-surface',
    'id="comment-form"',
    'id="comment-submit-btn"',
] as $needle) {
    $assert(str_contains($composer, $needle), 'Ticket detail composer component missing: ' . $needle);
}

foreach ([
    'id="edit-ticket-modal"',
    'id="edit-comment-modal"',
    'id="edit-time-modal"',
] as $needle) {
    $assert(str_contains($modals, $needle), 'Ticket detail modals component missing: ' . $needle);
}

foreach ([
    'window.quickEditField',
    'window.openEditCommentModal',
    'window.openTicketTimeline',
    'initTimer',
    'initQuillEditors',
] as $needle) {
    $assert(str_contains($ticketDetailJs, $needle), 'Ticket detail JS asset missing: ' . $needle);
}

foreach ([
    '--font-sans:',
    '--tracking-ui: 0;',
    '--type-2xl: 1.5rem;',
    '--app-sidebar-width: 280px;',
    '--app-sidebar-compact-width: 76px;',
    '--app-content-max: 1480px;',
    '.app-content',
    '.app-topbar',
    '.app-shell-context',
    '.ticket-status-pill',
    '.ticket-primary-action-form',
    '.ticket-primary-action__timer',
    '.workspace-queue-rail',
    'overflow-x: auto;',
    'scroll-snap-type: x proximity;',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing module/shell contract: ' . $needle);
}

$assert(!preg_match('/--text-[a-z0-9-]+:\s*clamp\(/', $theme), 'Root text tokens must not use clamp().');
$assert(str_contains($header, 'class="app-shell-page antialiased font-sans"'), 'Header body must opt into app shell page class.');
$assert(str_contains($header, 'class="app-topbar desktop-header'), 'Desktop header must use app topbar class.');
$assert(str_contains($header, 'class="app-topbar mobile-header'), 'Mobile header must use app topbar class.');
$assert(str_contains($header, 'class="app-content"'), 'Page content must be wrapped in app-content.');
$assert(str_contains($headerJs, '--app-sidebar-compact-width'), 'Header JS must read compact width from CSS token.');

echo "Module extraction contract OK\n";
