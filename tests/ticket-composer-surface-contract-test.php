<?php

$root = dirname(__DIR__);
$page = file_get_contents($root . '/pages/ticket-detail.php');
$composer = file_get_contents($root . '/includes/components/ticket-detail-composer.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($page !== false && $composer !== false && $theme !== false, 'Ticket composer surface files must be readable.');

$assert(str_contains($page, "/includes/components/ticket-detail-composer.php"), 'Ticket detail page must include the composer component.');
$assert(!str_contains($page, 'data-ticket-composer-surface'), 'Ticket composer markup must stay inside the composer component.');

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
    $assert(str_contains($composer, $needle), 'Ticket composer markup missing surface contract: ' . $needle);
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

$assert(!str_contains($composer, 'style='), 'Ticket composer surface must not use inline style attributes.');
$assert(!str_contains($composer, 'style="height: 42px;"'), 'Composer controls must use ticket-composer CSS, not inline heights.');
$assert(!str_contains($composer, 'style="border-color: var(--border-light); height: 42px;"'), 'Upload zone must use ticket-composer-upload-zone CSS.');
$assert(!str_contains($composer, 'manual-duration-chip btn btn-ghost px-2 py-1 text-xs'), 'Manual time chips must use ticket-composer-manual-chip.');
$assert(!str_contains($composer, 'class="flex items-center text-sm cursor-pointer whitespace-nowrap text-theme-secondary"'), 'Notification toggle must use ticket-composer-skip-notification.');
$assert(str_contains($composer, 'ticket-composer-manual-header'), 'Agent composer must show the time entry section with a clear label.');
$assert(str_contains($composer, "t('Time spent')"), 'Visible agent time entry section must use the shared Time spent label.');
$assert(!str_contains($composer, 'id="manual-entry-row" class="ticket-composer-manual-entry hidden"'), 'Agent time entry must be visible by default so comments can be saved with time.');
$assert(str_contains($composer, 'aria-expanded="true"'), 'Manual time toggle must match the visible default state.');
$assert(!str_contains($page, 'btn.style.background'), 'Comment mode JS must use the is-active class instead of inline styles.');
$assert(!str_contains($page, 'btn.style.color'), 'Comment mode JS must not write inline colors.');

echo "Ticket composer surface contract OK\n";
