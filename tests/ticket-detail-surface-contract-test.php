<?php

$root = dirname(__DIR__);
$functions = file_get_contents($root . '/includes/functions.php');
$component = file_get_contents($root . '/includes/components/ticket-detail-surface.php');
$page = file_get_contents($root . '/pages/ticket-detail.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($functions !== false && $component !== false && $page !== false && $theme !== false, 'Ticket detail surface files must be readable.');
$assert(str_contains($functions, '/includes/components/ticket-detail-surface.php'), 'Ticket detail component must be loaded globally.');

foreach ([
    'function ticket_detail_status_group',
    'function ticket_detail_status_pill_class',
    'function ticket_detail_primary_action_class',
    'function ticket_detail_render_status_pill',
] as $needle) {
    $assert(str_contains($component, $needle), 'Ticket detail component missing: ' . $needle);
}

foreach ([
    'data-ticket-detail-surface',
    'ticket-detail-main',
    'ticket-back-link',
    'ticket_detail_render_status_pill($ticket, $statuses)',
    'ticket_detail_primary_action_class($action)',
    'ticket-primary-action-form',
    'ticket-primary-action__timer',
] as $needle) {
    $assert(str_contains($page, $needle), 'Ticket detail page missing surface contract: ' . $needle);
}

foreach ([
    '.ticket-detail-page',
    '.ticket-detail-main',
    '.ticket-back-link',
    '.ticket-status-pill',
    '.ticket-status-pill--active',
    '.ticket-primary-action-form',
    '.ticket-primary-action__timer',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing ticket detail selector: ' . $needle);
}

$assert(!str_contains($page, 'class="inline-flex items-center gap-1 hover:underline" style="color: var(--text-muted);"'), 'Back link must use ticket-back-link instead of inline styles.');
$assert(!str_contains($page, 'style="background-color: <?php echo e($ticket[\'status_color\']); ?>15;'), 'Top status pill must not use inline DB colors.');
$assert(!str_contains($page, 'style="background-color: <?php echo e($ticket[\'status_color\']); ?>20;'), 'Sidebar status pill must not use inline DB colors.');
$assert(!str_contains($page, "toolbarBtn.className = 'td-tool-btn"), 'Timer JS must not downgrade the primary action into legacy toolbar styling.');

echo "Ticket detail surface contract OK\n";
