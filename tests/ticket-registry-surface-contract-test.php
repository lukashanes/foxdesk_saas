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
    'ticket_registry_render_view_tabs',
    'ticket_registry_render_filter_summary',
    '$page_header_subtitle = \'\';',
    'ticket-segmented-control',
] as $needle) {
    $assert(str_contains($page, $needle), 'Tickets page missing registry surface contract: ' . $needle);
}

foreach ([
    '.ticket-registry-page',
    '.ticket-registry-toolbar',
    '.ticket-registry-card',
    '.ticket-filter-summary',
    '.ticket-segmented-control',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing ticket registry selector: ' . $needle);
}

$assert(!str_contains($page, 'bg-blue-600 text-white border-blue-600'), 'Ticket layout toggles should use CSS classes, not Tailwind inline state strings.');
$assert(!str_contains($theme, '--shadow-xs'), 'Ticket registry CSS must use defined shadow tokens.');

echo "Ticket registry surface contract OK\n";
