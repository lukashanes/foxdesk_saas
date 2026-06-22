<?php

$root = dirname(__DIR__);
$page = file_get_contents($root . '/pages/ticket-detail.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($page !== false && $theme !== false, 'Ticket activity surface files must be readable.');

foreach ([
    'data-ticket-activity-surface',
    'ticket-activity-card',
    'ticket-activity-list',
    'ticket-comment__body',
    'ticket-comment__badge--internal',
    'ticket-time-entry-chip',
    'ticket-inline-icon-button',
] as $needle) {
    $assert(str_contains($page, $needle), 'Ticket activity markup missing surface contract: ' . $needle);
}

foreach ([
    '.ticket-activity-card',
    '.ticket-activity-header',
    '.ticket-comment',
    '.ticket-comment__body',
    '.ticket-time-entry-chip',
    '.ticket-inline-icon-button',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing activity selector: ' . $needle);
}

$activity_start = strpos($page, '<div class="card ticket-activity-card" data-ticket-activity-surface>');
$composer_start = strpos($page, "include BASE_PATH . '/includes/components/ticket-detail-composer.php'");
$assert($activity_start !== false && $composer_start !== false && $composer_start > $activity_start, 'Unable to isolate ticket activity markup.');
$activity_markup = substr($page, $activity_start, $composer_start - $activity_start);

$assert(!str_contains($activity_markup, 'style='), 'Ticket activity surface must not use inline style attributes.');
$assert(!str_contains($activity_markup, 'hover:bg-[var(--surface-secondary)]'), 'Ticket comments must use ticket-comment CSS instead of ad hoc hover utility classes.');
$assert(!str_contains($activity_markup, 'style="background:'), 'Ticket comment avatars must use CSS classes instead of inline background styles.');
$assert(!str_contains($activity_markup, 'p-0.5 hover:text-red-500 transition text-theme-muted'), 'Time entry action buttons must use ticket-inline-icon-button.');

echo "Ticket activity surface contract OK\n";
