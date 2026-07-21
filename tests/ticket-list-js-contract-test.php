<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/ticket-list-source.php';

$route = ticket_list_route_source($root);
$controller = ticket_list_controller_source($root);
$view = ticket_list_view_source($root);
$board = ticket_list_board_source($root);
$table = ticket_list_table_source($root);
$component = ticket_list_read_source($root, 'includes/components/ticket-list-assets.php');
$asset = ticket_list_read_source($root, 'assets/js/ticket-list.js');
$dueDateAsset = ticket_list_read_source($root, 'assets/js/ticket-list-due-date.js');
$timeAsset = ticket_list_read_source($root, 'assets/js/ticket-list-time.js');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert(count(file($root . '/pages/tickets.php') ?: []) <= 700, 'pages/tickets.php must stay at or below 700 lines.');
$assert(count(file($root . '/assets/js/ticket-list.js') ?: []) <= 900, 'assets/js/ticket-list.js must stay at or below 900 lines.');
$assert(count(file($root . '/assets/js/ticket-list-due-date.js') ?: []) <= 900, 'Ticket due-date JS module must stay at or below 900 lines.');
$assert(count(file($root . '/assets/js/ticket-list-time.js') ?: []) <= 900, 'Ticket time JS module must stay at or below 900 lines.');
$assert(count(file($root . '/includes/components/ticket-list-board.php') ?: []) <= 900, 'Ticket-list board component must stay at or below 900 lines.');
$assert(count(file($root . '/includes/components/ticket-list-table.php') ?: []) <= 900, 'Ticket-list table component must stay at or below 900 lines.');

foreach ([
    'ticket-list-page-controller.php',
    'ticket-list-page.php',
    'ticket-list-assets.php',
] as $needle) {
    $assert(str_contains($route, $needle), 'Tickets route missing composition boundary: ' . $needle);
}
$assert(!str_contains($route, 'get_tickets('), 'Tickets route must not own query logic.');
$assert(!str_contains($route, 'data-ticket-registry-surface'), 'Tickets route must not own registry markup.');
$assert(str_contains($controller, '$date_sort_url'), 'Ticket-list controller must expose the date sort URL.');
$assert(str_contains($controller, '$ticket_list_asset_version'), 'Ticket-list controller must define the cache buster.');
$assert(str_contains($controller, "\$file = BASE_PATH . '/'"), 'Ticket-list cache buster must resolve assets from the application root.');
$assert(str_contains($controller, "filemtime(\$file)"), 'Ticket-list cache buster must change with asset files.');
$assert(str_contains($view, 'data-ticket-registry-surface'), 'Ticket-list view must own registry markup.');
$assert(str_contains($view, 'ticket-list-board.php'), 'Ticket-list view must delegate board rendering.');
$assert(str_contains($view, 'ticket-list-table.php'), 'Ticket-list view must delegate table rendering.');
$assert(str_contains($board, 'data-kanban-scope="main"'), 'Ticket-list board component must own Kanban rendering.');
$assert(str_contains($table, 'tickets-table'), 'Ticket-list table component must own table rendering.');

foreach ([
    'window.FoxDeskTicketListConfig',
    'assets/js/ticket-list.js',
    'assets/js/ticket-list-due-date.js',
    'assets/js/ticket-list-time.js',
] as $needle) {
    $assert(str_contains($component, $needle), 'Ticket-list asset loader missing: ' . $needle);
}
foreach (['ticket-list.js', 'ticket-list-due-date.js', 'ticket-list-time.js'] as $filename) {
    $assert(
        str_contains($component, "assets/js/{$filename}?v=<?php echo e(\$ticket_list_asset_version('assets/js/{$filename}')); ?>"),
        $filename . ' must use filemtime cache busting.'
    );
}

foreach ([
    'window.FoxDeskTicketList = {',
    'window.applyHeaderSort',
    'window.toggleBulkMode',
    'window.toggleAll',
    'window.updateSelectedCount',
    'window.inlineUpdate = function',
    'window.inlineUpdateType = function',
    'window.inlineUpdateCompany = function',
    'window.inlineUpdateAssign = function',
    'function bindSearchSuggestions',
    'function bindInlineDropdowns',
    'function bindSubjectInlineEditor',
    'function bindNewTicketRow',
    'document.body.appendChild(dropdown)',
    'restoreOpenDropdown()',
    'clearDropdownPosition(openDropdown)',
    "dropdown.style.position = 'fixed';",
    'var left = rect.left;',
    'var top = rect.bottom + 4;',
    "document.addEventListener('DOMContentLoaded'",
] as $needle) {
    $assert(str_contains($asset, $needle), 'Core ticket-list JS missing behavior: ' . $needle);
}

foreach ([
    'function bindDueDatePopover',
    "apiCall('quick-due-date'",
    'document.body.appendChild(popover)',
    "document.addEventListener('DOMContentLoaded', bindDueDatePopover)",
] as $needle) {
    $assert(str_contains($dueDateAsset, $needle), 'Due-date module missing behavior: ' . $needle);
}

foreach ([
    'function bindInlineLogTime',
    "action=quick-log-time",
    'function openCustom',
    'function openChips',
    "document.addEventListener('DOMContentLoaded', bindInlineLogTime)",
] as $needle) {
    $assert(str_contains($timeAsset, $needle), 'Time module missing behavior: ' . $needle);
}

$assert(!str_contains($asset, 'function bindDueDatePopover'), 'Due-date behavior must not move back into the core asset.');
$assert(!str_contains($asset, 'function bindInlineLogTime'), 'Time behavior must not move back into the core asset.');
$assert(strpos($asset, 'document.body.appendChild(dropdown)') < strpos($asset, "dropdown.style.position = 'fixed';"), 'Inline dropdown must be portaled before positioning.');
$assert(!str_contains($asset, 'window.pageXOffset'), 'Fixed dropdowns must not add document scroll offsets.');
$assert(!str_contains($asset, 'window.pageYOffset'), 'Fixed dropdowns must not add document scroll offsets.');
$assert(str_contains($asset, 'openOriginalParent.insertBefore(openDropdown, openOriginalNextSibling)'), 'Inline dropdowns must restore to their original cell.');

echo "Ticket list JS contract OK\n";
