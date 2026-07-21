<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/settings-source.php';
$page = settings_source_bundle($root);
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($theme !== false, 'Admin settings files must be readable.');

foreach ([
    'settings-warning-box',
    'settings-warning-text',
    'settings-select--type',
    'settings-select--user',
    'settings-table-wrap',
    'settings-table-row',
    'settings-muted-action',
    'settings-page-link',
    'settings-tfa-role',
    'settings-tfa-warning',
    'td-tool-btn--danger',
    'workflow-card-title--statuses',
    'workflow-card-title--priorities',
    'workflow-card-title--types',
    "warning.classList.add('is-visible')",
    "warning.classList.remove('is-visible')",
] as $needle) {
    $assert(str_contains($page, $needle), 'Settings page missing surface contract: ' . $needle);
}

foreach ([
    '.settings-warning-box',
    '.settings-warning-text',
    '.settings-select--type',
    '.settings-select--user',
    '.settings-table-wrap',
    '.settings-page-link',
    '.settings-tfa-role',
    '.settings-tfa-warning',
    '.tfa-impact-warning.is-visible',
    '.td-tool-btn--danger',
    '.workflow-card-title--statuses',
    '.workflow-card-title--priorities',
    '.workflow-card-title--types',
    '.workflow-card-subtitle',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing settings selector: ' . $needle);
}

$assert(!str_contains($page, 'style="'), 'Admin settings page must not use inline style attributes.');
$assert(!str_contains($page, '<style'), 'Admin settings page must not emit page-level style blocks.');
$assert(!str_contains($page, '.style.'), 'Admin settings JS must not write inline styles.');
$assert(!str_contains($page, 'warning.style.display'), '2FA warnings must use CSS classes, not inline display writes.');

echo "Admin settings surface contract OK\n";
