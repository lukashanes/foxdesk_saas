<?php

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$database = file_get_contents($root . '/includes/database.php');
$tenant = file_get_contents($root . '/includes/tenant-functions.php');

$assert($database !== false && $tenant !== false, 'Unable to read database tenant boundary files.');

$assert(str_contains($tenant, 'function tenant_where_has_scope'), 'Tenant helper must detect an existing tenant predicate.');
$assert(str_contains($tenant, 'function tenant_scope_mutation_where'), 'Tenant mutation scoping helper is missing.');
$assert(str_contains($tenant, 'tenant_scoped_table_has_column($table)'), 'Tenant mutation helper must only affect tenant-scoped tables.');
$assert(str_contains($tenant, 'tenant_where_has_scope($where)'), 'Tenant mutation helper must not duplicate explicit tenant predicates.');
$assert(str_contains($tenant, 'current_tenant_id()'), 'Tenant mutation helper must bind the current tenant.');
$assert(str_contains($tenant, "'(' . \$where . ') AND tenant_id = ?'"), 'Tenant mutation helper must append tenant_id to unscoped mutations.');

$assert(
    str_contains($database, 'tenant_scope_mutation_where($table, $where, $where_params)'),
    'db_update must apply tenant mutation scoping.'
);
$assert(
    str_contains($database, 'tenant_scope_mutation_where($table, $where, $params)'),
    'db_delete must apply tenant mutation scoping.'
);

$platform = file_get_contents($root . '/includes/modules/platform/operator-console.php');
$migration = file_get_contents($root . '/includes/migration-functions.php');
$migration_api = file_get_contents($root . '/includes/api/migration-handler.php');
$assert($platform !== false, 'Unable to read platform operator console.');
$assert($migration !== false && $migration_api !== false, 'Unable to read migration bridge files.');
$assert(str_contains($platform, "db_update('tenants'"), 'Platform lifecycle should continue to update global tenant records explicitly.');
$assert(
    preg_match('/function\s+tenant_owned_tables\s*\(\)\s*:\s*array\s*\{[\s\S]*?return\s*\[(?<tables>[\s\S]*?)\];/m', $tenant, $owned_match) === 1,
    'Unable to inspect tenant_owned_tables().'
);
$assert(!str_contains($owned_match['tables'], "'tenants'"), 'tenants table must not be classified as tenant-owned.');

foreach ([
    "db_update('migration_connections'",
    "db_update('migration_object_map'",
] as $needle) {
    $assert(
        !preg_match("/" . preg_quote($needle, '/') . "[\\s\\S]{0,220}'id = \\?'/", $migration . "\n" . $migration_api),
        'Migration bridge updates must include an explicit tenant predicate: ' . $needle
    );
}

echo "Tenant mutation boundary contract OK\n";
