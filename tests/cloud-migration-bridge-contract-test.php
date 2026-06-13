<?php

$root = dirname(__DIR__);

$release = file_get_contents($root . '/docs/RELEASE_CHANNELS.md');
$migrationDocs = file_get_contents($root . '/docs/SELF_HOSTED_TO_SAAS_MIGRATION.md');
$checklist = file_get_contents($root . '/docs/SELF_HOSTED_RELEASE_CHECKLIST.md');
$router = file_get_contents($root . '/includes/api/router.php');
$handler = file_get_contents($root . '/includes/api/migration-handler.php');
$migration = file_get_contents($root . '/includes/migration-functions.php');
$platform = file_get_contents($root . '/pages/platform.php');

if (
    $release === false ||
    $migrationDocs === false ||
    $checklist === false ||
    $router === false ||
    $handler === false ||
    $migration === false ||
    $platform === false
) {
    fwrite(STDERR, "Unable to read cloud migration bridge files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

foreach ([
    "'migration-connect'",
    "'migration-plan'",
    "'migration-status'",
    "'migration-push-table'",
    "'migration-push-attachment'",
] as $route) {
    $assert(str_contains($router, $route), "Migration bridge route missing: {$route}");
}

foreach ([
    'function api_migration_connect',
    'function api_migration_plan',
    'function api_migration_status',
    'function api_migration_push_table',
    'function api_migration_push_attachment',
    'migration_bridge_record_attachment_sync_evidence',
    "'mode' => 'self_hosted_to_saas_then_cutover'",
] as $needle) {
    $assert(str_contains($handler, $needle), "Migration handler missing: {$needle}");
}

foreach ([
    'function migration_bridge_build_plan',
    'function migration_bridge_import_table_chunk',
    'function migration_bridge_import_attachment_upload',
    'function migration_bridge_record_attachment_sync_evidence',
    'single_active_instance',
] as $needle) {
    $assert(str_contains($migration, $needle), "Migration bridge module missing: {$needle}");
}

$assert(str_contains($platform, 'Migration bridge'), 'Platform operator console must expose migration bridge controls.');
$assert(str_contains($platform, 'Copy it now; it will not be shown again'), 'Migration token must remain a one-time secret.');

$assert(str_contains($release, 'API sync is the preferred production path'), 'Release docs must make API sync the preferred path.');
$assert(str_contains($release, 'ZIP export/import is kept only as a fallback'), 'Release docs must keep ZIP import/export as fallback only.');
$assert(str_contains($release, 'SaaS platform operator dashboards'), 'Release docs must exclude platform operator dashboards from public updates.');
$assert(str_contains($release, 'Stripe customer/subscription administration internals'), 'Release docs must exclude SaaS billing internals from public updates.');

$assert(str_contains($migrationDocs, 'The preferred production path is API sync followed by final cutover'), 'Migration docs must prefer API sync.');
$assert(str_contains($migrationDocs, 'Use ZIP import only when API sync is not available'), 'Migration docs must document ZIP as fallback only.');
$assert(str_contains($migrationDocs, 'disables local'), 'Migration docs must say self-hosted active processing stops after cutover.');
$assert(str_contains($migrationDocs, 'Sync includes attachments through a streaming API upload'), 'Migration docs must include attachment sync.');

foreach ([
    'Allowed Scope',
    'The release must not include',
    'migration bridge client and final cutover controls',
    'ZIP migration export fallback',
    'platform operator console screens',
    'Stripe customer, subscription, portal, checkout, VAT, or metered billing admin',
    'final cutover disables active self-hosted ingest',
] as $needle) {
    $assert(str_contains($checklist, $needle), "Self-hosted release checklist missing: {$needle}");
}

echo "Cloud migration bridge contract OK\n";
