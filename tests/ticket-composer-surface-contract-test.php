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

$assert($page !== false && $theme !== false, 'Ticket composer surface files must be readable.');

foreach ([
    'data-ticket-composer-surface',
    'ticket-composer-mode-button',
    'ticket-composer-status-select',
    'ticket-composer-upload-zone',
    'ticket-composer-manual-entry',
    'ticket-composer-timer-controls',
    'ticket-composer-cc-toggle',
    'ticket-composer-submit-button',
] as $needle) {
    $assert(str_contains($page, $needle), 'Ticket composer markup missing surface contract: ' . $needle);
}

foreach ([
    '.ticket-composer',
    '.ticket-composer-mode-button',
    '.ticket-composer-upload-zone',
    '.ticket-composer-manual-entry',
    '.ticket-composer-timer-controls',
    '.ticket-composer-cc-list',
    '.ticket-composer-submit-button',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing composer selector: ' . $needle);
}

$composer_start = strpos($page, '<form method="post" enctype="multipart/form-data" class="ticket-composer"');
$sidebar_start = strpos($page, '<!-- Sidebar -->');
$assert($composer_start !== false && $sidebar_start !== false && $sidebar_start > $composer_start, 'Unable to isolate ticket composer markup.');
$composer_markup = substr($page, $composer_start, $sidebar_start - $composer_start);

$assert(!str_contains($composer_markup, 'style='), 'Ticket composer surface must not use inline style attributes.');
$assert(!str_contains($composer_markup, 'style="height: 42px;"'), 'Composer controls must use ticket-composer CSS, not inline heights.');
$assert(!str_contains($composer_markup, 'style="border-color: var(--border-light); height: 42px;"'), 'Upload zone must use ticket-composer-upload-zone CSS.');
$assert(!str_contains($composer_markup, 'manual-duration-chip btn btn-ghost px-2 py-1 text-xs'), 'Manual time chips must use ticket-composer-manual-chip.');
$assert(!str_contains($composer_markup, 'class="flex items-center text-sm cursor-pointer whitespace-nowrap text-theme-secondary"'), 'Notification toggle must use ticket-composer-skip-notification.');
$assert(!str_contains($page, 'btn.style.background'), 'Comment mode JS must use the is-active class instead of inline styles.');
$assert(!str_contains($page, 'btn.style.color'), 'Comment mode JS must not write inline colors.');

echo "Ticket composer surface contract OK\n";
