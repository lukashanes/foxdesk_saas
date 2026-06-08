<?php

$root = dirname(__DIR__);
$header = file_get_contents($root . '/includes/header.php');
$platform = file_get_contents($root . '/pages/platform.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($header !== false && $platform !== false && $theme !== false, 'App shell files must be readable.');

$assert(str_contains($header, 'class="app-shell-page antialiased font-sans"'), 'Workspace pages must opt into app shell styling.');
$assert(str_contains($header, 'class="app-topbar desktop-header'), 'Desktop header must use app-topbar shell class.');
$assert(str_contains($header, 'class="app-topbar mobile-header'), 'Mobile header must use app-topbar shell class.');
$assert(str_contains($header, 'app-shell-context'), 'Workspace header must show a compact workspace context.');
$assert(str_contains($platform, 'op-environment-pill'), 'Platform console must show a platform admin environment pill.');

foreach ([
    '--app-sidebar-width: 280px;',
    '--app-sidebar-compact-width: 76px;',
    '--app-content-max: 1480px;',
    '.app-shell-page .app-content',
    '.app-topbar',
    '.app-shell-context',
    '.op-environment-pill',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing app shell visual contract: ' . $needle);
}

$assert(!str_contains($header, 'width: clamp(200px, 25vw, 320px)'), 'Header search width must be controlled by app shell CSS, not inline clamp.');
$assert(!str_contains($header, "compact ? '76px'"), 'Header inline sidebar sync must read compact width from CSS tokens.');

$appHeaderJs = file_get_contents($root . '/assets/js/app-header.js');
$assert($appHeaderJs !== false && str_contains($appHeaderJs, '--app-sidebar-compact-width'), 'Sidebar JS must read compact width from CSS tokens.');

echo "App shell visual contract OK\n";
