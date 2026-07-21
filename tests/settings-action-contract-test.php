<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/settings-source.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$page = settings_source_read($root, 'pages/admin/settings.php');
$pageController = settings_source_read($root, 'includes/modules/settings/settings-page-controller.php');
$pageViewModel = settings_source_read($root, 'includes/modules/settings/settings-page-view-model.php');
$pageRenderer = settings_source_read($root, 'includes/modules/settings/settings-page-render.php');
$bootstrap = file_get_contents($root . '/includes/modules/bootstrap.php');
$actions = file_get_contents($root . '/includes/modules/settings/settings-actions.php');
$workflow = file_get_contents($root . '/includes/modules/settings/settings-workflow.php');
$viewModel = file_get_contents($root . '/includes/modules/settings/settings-view-model.php');
$tabs = file_get_contents($root . '/includes/components/admin-settings-tabs.php');

$assert($bootstrap !== false && $actions !== false && $workflow !== false && $viewModel !== false && $tabs !== false, 'Settings files must be readable.');

foreach ([
    '/settings/settings-actions.php',
    '/settings/settings-email.php',
    '/settings/settings-updates.php',
    '/settings/settings-security.php',
    '/settings/settings-workflow.php',
    '/settings/settings-view-model.php',
] as $needle) {
    $assert(str_contains($bootstrap, $needle), 'Settings module bootstrap missing: ' . $needle);
}

$assert(str_contains($page, 'settings-page-controller.php'), 'Settings route must delegate request actions to the page controller.');
$assert(str_contains($pageController, 'settings_handle_post_request($settings_audit)'), 'Settings request controller must delegate main POST actions.');
$assert(str_contains($pageController, 'settings_handle_workflow_post($tab, $_POST)'), 'Settings request controller must delegate workflow POST actions.');
$assert(str_contains($pageController, 'settings_tab_from_request($_GET)'), 'Settings request controller must use settings tab view model.');
$assert(str_contains($pageRenderer, 'render_admin_settings_tabs($tab)'), 'Settings renderer must render tabs through the component.');
$assert(str_contains($pageViewModel, 'settings_section_partial'), 'Settings page view model must own the section allowlist.');

foreach ([
    "if (isset(\$_POST['save_email'])",
    "if (isset(\$_POST['save_2fa_settings'])",
    "if (isset(\$_POST['install_remote_update'])",
    '$workflow_handlers = [',
    '$settings_tabs = [',
] as $needle) {
    $assert(!str_contains($page, $needle), 'Settings route must not own extracted settings logic: ' . $needle);
}

foreach ([
    'function settings_handle_post_request',
    'save_email',
    'save_2fa_settings',
    'install_remote_update',
    'save_template',
    'settings_is_managed_update_action($_POST)',
] as $needle) {
    $assert(str_contains($actions, $needle), 'Settings action module missing: ' . $needle);
}

$assert(str_contains($workflow, 'function settings_handle_workflow_post'), 'Settings workflow module must expose workflow handler.');
$assert(str_contains($viewModel, 'function settings_tab_from_request'), 'Settings view model must expose tab helper.');
$assert(str_contains($tabs, 'function render_admin_settings_tabs'), 'Settings tabs component must expose renderer.');
$assert(str_contains($tabs, 'class="settings-section-card'), 'Settings renderer must use compact section cards.');
$assert(!str_contains($tabs, 'class="admin-tab'), 'Settings renderer must not use horizontal admin tabs.');

echo "Settings action contract OK\n";
