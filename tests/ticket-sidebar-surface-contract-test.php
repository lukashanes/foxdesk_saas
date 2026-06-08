<?php

$root = dirname(__DIR__);
$component = file_get_contents($root . '/includes/components/ticket-detail-surface.php');
$page = file_get_contents($root . '/pages/ticket-detail.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($component !== false && $page !== false && $theme !== false, 'Ticket sidebar surface files must be readable.');

foreach ([
    'function ticket_detail_priority_key',
    'function ticket_detail_priority_pill_class',
    'function ticket_detail_render_priority_pill',
] as $needle) {
    $assert(str_contains($component, $needle), 'Ticket detail component missing sidebar helper: ' . $needle);
}

foreach ([
    'data-ticket-sidebar-surface',
    'ticket-client-pill',
    'ticket-side-select',
    'ticket-side-edit-button',
    'ticket-date-value',
    'ticket-side-action-button',
    'ticket-attachment-link',
    'ticket-share-input',
] as $needle) {
    $assert(str_contains($page, $needle), 'Ticket detail sidebar missing surface contract: ' . $needle);
}

foreach ([
    '.ticket-side-card',
    '.ticket-client-pill',
    '.ticket-side-select',
    '.ticket-side-action-button',
    '.ticket-priority-pill',
    '.ticket-date-value',
    '.ticket-attachment-link',
    '.ticket-share-input',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing sidebar selector: ' . $needle);
}

$sidebar_start = strpos($page, 'data-ticket-sidebar-surface');
$modal_start = strpos($page, '<!-- Edit Ticket Modal -->');
$assert($sidebar_start !== false && $modal_start !== false && $modal_start > $sidebar_start, 'Unable to isolate ticket sidebar markup.');
$sidebar_markup = substr($page, $sidebar_start, $modal_start - $sidebar_start);

$assert(!str_contains($sidebar_markup, 'style='), 'Ticket sidebar surface must not use inline style attributes.');
$assert(!str_contains($sidebar_markup, 'onmouseover='), 'Ticket sidebar surface must not use inline mouseover styling.');
$assert(!str_contains($sidebar_markup, 'onmouseout='), 'Ticket sidebar surface must not use inline mouseout styling.');
$assert(!str_contains($sidebar_markup, 'text-xs py-0.5 px-1 rounded border-0 cursor-pointer'), 'Quick property selects must use ticket-side-select.');

echo "Ticket sidebar surface contract OK\n";
