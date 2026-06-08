<?php

$root = dirname(__DIR__);
$theme = file_get_contents($root . '/theme.css');
$cloud = file_get_contents($root . '/assets/public/cloud.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($theme !== false, 'theme.css must be readable.');
$assert($cloud !== false, 'cloud.css must be readable.');

foreach ([
    '--font-sans:',
    '--type-xs: 0.75rem;',
    '--type-sm: 0.875rem;',
    '--type-base: 1rem;',
    '--type-lg: 1.125rem;',
    '--type-xl: 1.25rem;',
    '--type-2xl: 1.5rem;',
    '--type-3xl: 2rem;',
    '--tracking-ui: 0;',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing design token: ' . $needle);
}

$assert(!preg_match('/--text-[a-z0-9-]+:\s*clamp\(/', $theme), 'theme.css root type tokens must not use clamp().');
$assert(!preg_match('/font-size:\s*clamp\(/', $theme), 'theme.css must not use viewport-scaled font-size clamp().');
$assert(!preg_match('/font-size:\s*clamp\(/', $cloud), 'cloud.css must not use viewport-scaled font-size clamp().');
$assert(str_contains($theme, 'letter-spacing: var(--tracking-ui);'), 'App body must use the UI tracking token.');
$assert(str_contains($cloud, 'letter-spacing: 0;'), 'Public body must set neutral letter spacing.');

echo "Design token contract OK\n";
