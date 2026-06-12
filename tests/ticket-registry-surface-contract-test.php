<?php

$root = dirname(__DIR__);
$functions = file_get_contents($root . '/includes/functions.php');
$component = file_get_contents($root . '/includes/components/ticket-registry-surface.php');
$page = file_get_contents($root . '/pages/tickets.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($functions !== false && $component !== false && $page !== false && $theme !== false, 'Ticket registry surface files must be readable.');
$assert(str_contains($functions, '/includes/components/ticket-registry-surface.php'), 'Ticket registry component must be loaded globally.');

foreach ([
    'function ticket_registry_render_view_tabs',
    'function ticket_registry_render_filter_summary',
    'ticket-view-tabs',
    'ticket-filter-summary',
] as $needle) {
    $assert(str_contains($component, $needle), 'Ticket registry component missing: ' . $needle);
}

foreach ([
    'data-ticket-registry-surface',
    'data-ticket-contract-mode="refresh"',
    'data-ticket-contract-row',
    'data-ticket-field="title"',
    'data-ticket-field="status"',
    'data-ticket-field="priority"',
    'data-ticket-field="client"',
    'data-ticket-field="assignee"',
    'data-ticket-field="code"',
    'ticket_registry_render_view_tabs',
    'ticket_registry_render_filter_summary',
    '$page_header_subtitle = \'\';',
    'ticket-segmented-control',
    'ticket-status-accent',
    'ticket-active-tags-bar',
    'tickets-table--fixed',
    'ticket-filter-clear-button',
    'ticket-bulk-actions',
    'ticket-empty-value',
] as $needle) {
    $assert(str_contains($page, $needle), 'Tickets page missing registry surface contract: ' . $needle);
}

foreach ([
    '.ticket-registry-page',
    '.ticket-registry-toolbar',
    '.ticket-registry-card',
    '.ticket-filter-summary',
    '.ticket-segmented-control',
    '.ticket-status-accent',
    '.ticket-status-dot',
    '.ticket-status-inline',
    '.ticket-priority-inline',
    '.ticket-active-tags-bar',
    '.tickets-table--fixed',
    '.ticket-filter-clear-button',
    '.ticket-bulk-actions',
    '.ticket-empty-value',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing ticket registry selector: ' . $needle);
}

$assert(!str_contains($page, 'bg-blue-600 text-white border-blue-600'), 'Ticket layout toggles should use CSS classes, not Tailwind inline state strings.');
$assert(!str_contains($page, 'style="table-layout: fixed;"'), 'Tickets table layout must be a CSS class.');
$assert(!str_contains($page, 'row.style.display'), 'Quick add row visibility must use classes, not inline display writes.');
$assert(!str_contains($page, "tableRow.style.background"), 'Bulk selection must use classes, not inline table-row backgrounds.');
$assert(!str_contains($page, "mobileCard.style.background"), 'Bulk selection must use classes, not inline mobile-card backgrounds.');
$assert(!str_contains($page, 'style="border-left: 5px solid'), 'Ticket status accents must use status classes, not inline colors.');
$assert(!str_contains($page, 'style="border-color: var(--border-light); background: var(--surface-secondary);"'), 'Ticket surface bars must use CSS classes, not inline token styles.');
$assert(!str_contains($page, '<span style="opacity:0.4'), 'Empty ticket values must use the shared empty-value class.');
$assert(!str_contains($page, '<span style="opacity:0.6'), 'Muted ticket values must use the shared muted-value class.');
$assert(!str_contains($theme, '--shadow-xs'), 'Ticket registry CSS must use defined shadow tokens.');

echo "Ticket registry surface contract OK\n";
