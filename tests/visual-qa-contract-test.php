<?php

$root = dirname(__DIR__);
$package = file_get_contents($root . '/package.json');
$visualQa = file_get_contents($root . '/tests/smoke/visual-qa.js');
$cssAudit = file_get_contents($root . '/bin/css-visual-audit.js');
$baseline = file_get_contents($root . '/docs/visual-style-baseline.json');
$doc = file_get_contents($root . '/docs/PRODUCT_VOICE_AND_VISUAL_RESTYLE.md');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($package !== false && $visualQa !== false && $cssAudit !== false && $baseline !== false && $doc !== false, 'Visual QA files must be readable.');
$assert(str_contains($package, '"visual:qa"'), 'package.json must expose visual:qa.');
$assert(str_contains($package, '"css:visual-audit"'), 'package.json must expose css:visual-audit.');
$assert(str_contains($package, '"test:visual-qa"'), 'package.json must expose test:visual-qa.');

foreach ([
    'public-cloud',
    'login',
    'work',
    'inbox',
    'tickets',
    'admin-settings',
    'billing',
    'reports',
    'platform',
    'client',
    'ticket-detail',
] as $screen) {
    $assert(str_contains($visualQa, $screen), 'Visual QA must capture screen: ' . $screen);
}

$assert(str_contains($visualQa, 'horizontalOverflow'), 'Visual QA must check horizontal overflow.');
$assert(str_contains($visualQa, 'desktop') && str_contains($visualQa, 'mobile'), 'Visual QA must capture desktop and mobile screenshots.');
$assert(str_contains($cssAudit, 'uniqueFontSizes'), 'CSS audit must count unique font sizes.');
$assert(str_contains($cssAudit, 'uniqueBorderRadii'), 'CSS audit must count unique radii.');
$assert(str_contains($cssAudit, 'uniqueBoxShadows'), 'CSS audit must count unique shadows.');
$assert(str_contains($baseline, 'pre-milestone-7 git HEAD'), 'Visual baseline must document its source.');
$assert(str_contains($doc, 'npm run visual:qa'), 'Restyle doc must document visual QA command.');
$assert(str_contains($doc, 'npm run css:visual-audit'), 'Restyle doc must document CSS audit command.');

echo "Visual QA contract OK\n";
