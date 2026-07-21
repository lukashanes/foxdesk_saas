<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/ticket-list-source.php';
$functions = file_get_contents($root . '/includes/functions.php');
$component = file_get_contents($root . '/includes/components/ticket-registry-surface.php');
$page = ticket_list_surface_source($root);
$theme = file_get_contents($root . '/theme.css');
$js = file_get_contents($root . '/assets/js/ticket-list.js');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($functions !== false && $component !== false && $page !== false && $theme !== false && $js !== false, 'Ticket registry surface files must be readable.');
$assert(str_contains($functions, '/includes/components/ticket-registry-surface.php'), 'Ticket registry component must be loaded globally.');

foreach ([
    'function ticket_registry_render_view_tabs',
    'function ticket_registry_render_filter_summary',
    'if (empty($filter_notes) && !$has_filters)',
    'ticket-view-tabs',
    'ticket-filter-summary',
] as $needle) {
    $assert(str_contains($component, $needle), 'Ticket registry component missing: ' . $needle);
}

$assert(!str_contains($component, 'ticket-filter-summary__count'), 'Ticket registry summary must not repeat the selected view count below the view tabs.');

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
$assert(!str_contains($page, 'style="background-color: <?php echo e($ticket[\'status_color\']); ?>20;'), 'Ticket status badges must use tone classes, not inline DB colors.');
$assert(!str_contains($page, 'style="background-color: <?php echo e($priority_color); ?>20;'), 'Ticket priority badges must use tone classes, not inline DB colors.');
$assert(!str_contains($page, "btn.querySelector('.rounded-full')?.style.background"), 'Inline ticket updates must use CSS modifier classes, not computed inline dot styles.');
$assert(str_contains($page, 'data-tone-class="ticket-status-inline--'), 'Status dropdown items must expose target CSS class.');
$assert(str_contains($page, 'data-row-accent-class="ticket-status-accent--'), 'Status dropdown items must expose row accent CSS class.');
$assert(str_contains($page, 'data-tone-class="ticket-priority-inline--'), 'Priority dropdown items must expose target CSS class.');
$assert(str_contains($js, 'replaceModifierClass(trigger'), 'Inline ticket updates must swap badge CSS modifier classes.');
$assert(str_contains($js, "replaceModifierClass(row, 'ticket-status-accent'"), 'Inline ticket updates must swap row accent CSS modifier classes.');
$assert(str_contains($theme, '.ticket-status-option--active'), 'theme.css must define status option classes.');
$assert(str_contains($theme, '.ticket-priority-option--urgent'), 'theme.css must define priority option classes.');
$assert(str_contains($theme, '.ticket-priority-dot--urgent'), 'theme.css must define priority dot classes.');
$assert(!str_contains($page, 'style="border-color: var(--border-light); background: var(--surface-secondary);"'), 'Ticket surface bars must use CSS classes, not inline token styles.');
$assert(!str_contains($page, '<span style="opacity:0.4'), 'Empty ticket values must use the shared empty-value class.');
$assert(!str_contains($page, '<span style="opacity:0.6'), 'Muted ticket values must use the shared muted-value class.');
$assert(!str_contains($theme, '--shadow-xs'), 'Ticket registry CSS must use defined shadow tokens.');

echo "Ticket registry surface contract OK\n";
