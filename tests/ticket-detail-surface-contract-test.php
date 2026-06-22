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
    'ticket-meta-avatar',
    'ticket-meta-avatar__initial',
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
    '.ticket-meta-avatar',
    '.ticket-meta-avatar__initial',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing ticket detail selector: ' . $needle);
}

$assert(!str_contains($page, 'class="inline-flex items-center gap-1 hover:underline" style="color: var(--text-muted);"'), 'Back link must use ticket-back-link instead of inline styles.');
$assert(!str_contains($page, 'style="background-color: <?php echo e($ticket[\'status_color\']); ?>15;'), 'Top status pill must not use inline DB colors.');
$assert(!str_contains($page, 'style="background-color: <?php echo e($ticket[\'status_color\']); ?>20;'), 'Sidebar status pill must not use inline DB colors.');
$assert(!str_contains($page, 'style="background: var(--surface-tertiary);"'), 'Edit-history avatar must use a CSS class, not inline token background.');
$assert(!str_contains($page, 'style="color: var(--text-muted)"'), 'Ticket detail generated text must use CSS classes, not inline muted styles.');
$assert(!str_contains($page, "toast.style.opacity = '0'"), 'Ticket detail toast hiding must use CSS classes, not inline opacity writes.');
$assert(!str_contains($page, "commentEl.style.opacity = '0'"), 'Comment removal must use CSS classes, not inline opacity writes.');
$assert(!str_contains($page, "upload_url(\$ticket['avatar'])"), 'Created-by avatar must not depend on a possibly missing uploaded image.');
$assert(!str_contains($page, '<img src="<?php echo e($ticket_creator'), 'Created-by avatar must use a text fallback, not an image data URL.');
$assert(str_contains($page, 'ticket-history-avatar'), 'Ticket detail must use ticket-history-avatar class.');
$assert(str_contains($page, 'ticket-detail-muted'), 'Ticket detail generated muted text must use ticket-detail-muted class.');
$assert(str_contains($theme, '.ticket-history-avatar'), 'theme.css must define ticket-history-avatar.');
$assert(str_contains($theme, '.ticket-detail-muted'), 'theme.css must define ticket-detail-muted.');
$assert(str_contains($theme, '.ticket-comment.is-removing'), 'theme.css must define comment removal state.');
$assert(str_contains($theme, '.ticket-toast.is-hiding'), 'theme.css must define toast hiding state.');
$assert(!str_contains($page, "toolbarBtn.className = 'td-tool-btn"), 'Timer JS must not downgrade the primary action into legacy toolbar styling.');

echo "Ticket detail surface contract OK\n";
