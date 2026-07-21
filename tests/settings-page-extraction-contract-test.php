<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/settings-source.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$route = settings_source_read($root, 'pages/admin/settings.php');
$controller = settings_source_read($root, 'includes/modules/settings/settings-page-controller.php');
$viewModel = settings_source_read($root, 'includes/modules/settings/settings-page-view-model.php');
$renderer = settings_source_read($root, 'includes/modules/settings/settings-page-render.php');

$lineCount = static fn(string $contents): int => substr_count($contents, "\n") + 1;

$assert($lineCount($route) < 700, 'Settings route must stay below 700 lines.');
$assert($lineCount($controller) < 700, 'Settings request controller must stay below 700 lines.');
$assert($lineCount($viewModel) < 700, 'Settings page view model must stay below 700 lines.');
$assert(!str_contains($route, '$_POST'), 'Settings route must not handle POST actions directly.');
$assert(!str_contains($route, '<form'), 'Settings route must not render section forms directly.');

foreach ([
    'settings-page-controller.php',
    'settings-page-view-model.php',
    'settings-page-render.php',
] as $module) {
    $assert(str_contains($route, $module), 'Settings route must delegate to ' . $module . '.');
}

foreach ([
    'settings_handle_post_request($settings_audit)',
    'settings_handle_api_access_post()',
    'settings_handle_workflow_post($tab, $_POST)',
] as $needle) {
    $assert(str_contains($controller, $needle), 'Settings request controller is missing: ' . $needle);
}

foreach ([
    'settings_email_support_surface',
    "if (\$tab === 'email')",
    "if (\$tab === 'api')",
    'settings_section_partial',
] as $needle) {
    $assert(str_contains($viewModel, $needle), 'Settings page view model is missing: ' . $needle);
}

$sections = ['general', 'api', 'email', 'templates', 'workflow', 'system', 'logs', 'security'];
foreach ($sections as $section) {
    $path = 'includes/modules/settings/views/' . $section . '.php';
    $partial = settings_source_read($root, $path);
    $assert($lineCount($partial) < 700, $path . ' must stay below 700 lines.');
    $assert(str_contains($viewModel, "'{$section}' => '{$section}.php'"), 'Settings partial registry is missing ' . $section . '.');
}

$assert(str_contains($renderer, 'settings_section_partial($tab)'), 'Settings renderer must resolve the active section through the allowlist.');
$assert(str_contains($renderer, 'include $settings_section_partial'), 'Settings renderer must include the selected partial.');

echo "Settings page extraction contract OK\n";
