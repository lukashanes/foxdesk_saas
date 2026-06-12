<?php

$root = dirname(__DIR__);

$migration = file_get_contents($root . '/includes/migration-functions.php');
$handler = file_get_contents($root . '/includes/api/migration-handler.php');
$router = file_get_contents($root . '/includes/api/router.php');
$platform = file_get_contents($root . '/pages/platform.php');
$operator = file_get_contents($root . '/includes/modules/platform/operator-console.php');

if ($migration === false || $handler === false || $router === false || $platform === false || $operator === false) {
    fwrite(STDERR, "Unable to read SaaS migration bridge files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($migration, 'function migration_bridge_create_connection'), 'Migration token creation helper is missing.');
$assert(str_contains($migration, 'function migration_bridge_build_plan'), 'Migration plan helper is missing.');
$assert(str_contains($migration, 'function migration_bridge_import_table_chunk'), 'Migration chunk import helper is missing.');
$assert(str_contains($migration, 'function migration_bridge_import_attachment_upload'), 'Migration attachment import helper is missing.');
$assert(str_contains($migration, 'function migration_bridge_record_attachment_sync_evidence'), 'Migration attachment sync evidence helper is missing.');
$assert(str_contains($migration, 'migration_object_map'), 'Migration object map table is missing.');
$assert(str_contains($migration, 'attachment_sync_count'), 'Migration connections must track synced attachment count.');
$assert(str_contains($migration, 'attachment_sync_bytes'), 'Migration connections must track synced attachment bytes.');
$assert(str_contains($migration, 'attachment_sync_last_checksum'), 'Migration connections must retain last attachment checksum evidence.');
$assert(str_contains($migration, 'single_active_instance'), 'Migration bridge must declare single active instance cutover.');
$assert(str_contains($handler, 'function api_migration_connect'), 'Migration connect API is missing.');
$assert(str_contains($handler, 'function api_migration_plan'), 'Migration plan API is missing.');
$assert(str_contains($handler, 'function api_migration_status'), 'Migration status API is missing.');
$assert(str_contains($handler, 'function api_migration_push_table'), 'Migration table push API is missing.');
$assert(str_contains($handler, 'function api_migration_push_attachment'), 'Migration attachment push API is missing.');
$assert(str_contains($handler, 'migration_bridge_record_attachment_sync_evidence'), 'Migration attachment push API must write sync evidence.');
$assert(str_contains($handler, "'attachment_sync' => \$evidence"), 'Migration attachment push API must return sync evidence.');
$assert(str_contains($router, "'migration-connect'"), 'Migration connect route is missing.');
$assert(str_contains($router, "'migration-plan'"), 'Migration plan route is missing.');
$assert(str_contains($router, "'migration-status'"), 'Migration status route is missing.');
$assert(str_contains($router, "'migration-push-table'"), 'Migration table push route is missing.');
$assert(str_contains($router, "'migration-push-attachment'"), 'Migration attachment push route is missing.');
$assert(str_contains($operator, 'function platform_create_migration_token'), 'Platform migration token helper is missing.');
$assert(str_contains($operator, 'attachment_sync_count, attachment_sync_bytes'), 'Platform operator detail must load migration attachment evidence.');
$assert(str_contains($platform, 'Migration bridge'), 'Platform tenant detail must expose migration bridge.');
$assert(str_contains($platform, 'attachments '), 'Platform tenant detail must display migration attachment evidence.');
$assert(str_contains($platform, 'Copy it now; it will not be shown again'), 'Migration token must be treated as a one-time secret.');

echo "SaaS migration bridge contract OK\n";
