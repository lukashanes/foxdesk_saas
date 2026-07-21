<?php

function settings_source_read(string $root, string $path): string
{
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }

    return $contents;
}

function settings_source_bundle(string $root): string
{
    $paths = [
        'pages/admin/settings.php',
        'includes/modules/settings/settings-page-controller.php',
        'includes/modules/settings/settings-page-view-model.php',
        'includes/modules/settings/settings-page-render.php',
        'includes/modules/settings/views/general.php',
        'includes/modules/settings/views/api.php',
        'includes/modules/settings/views/email.php',
        'includes/modules/settings/views/templates.php',
        'includes/modules/settings/views/workflow.php',
        'includes/modules/settings/views/system.php',
        'includes/modules/settings/views/logs.php',
        'includes/modules/settings/views/security.php',
    ];

    return implode("\n", array_map(
        static fn(string $path): string => settings_source_read($root, $path),
        $paths
    ));
}
