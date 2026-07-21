<?php

$root = dirname(__DIR__);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$path}" . PHP_EOL);
        exit(1);
    }
    return $contents;
};

$tenant = $read('includes/tenant-functions.php');
$schema = $read('includes/schema.sql');
$tenantMigration = $read('migrations/2026072003_runtime_feature_columns.php');
$database = $read('includes/database.php');
$allowed = $read('includes/api/allowed-senders-handler.php');
$users = $read('includes/api/user-handler.php');
$ticket_api = $read('includes/api/ticket-handler.php');
$ticket_crud = $read('includes/ticket-crud-functions.php');
$ticket_forms = $read('includes/components/ticket-form-handlers.php');
$email_ingest = $read('includes/email-ingest-functions.php');
$uploads = $read('includes/upload-functions.php');
$api_router = $read('includes/api/router.php');
$auth = $read('includes/auth.php');
$billing_review = $read('includes/modules/reports/billing-review.php');
$platform = $read('pages/platform.php');

foreach ([
    'users',
    'organizations',
    'tickets',
    'comments',
    'ticket_time_entries',
    'attachments',
    'ticket_access',
    'notifications',
    'allowed_senders',
    'mobile_sessions',
    'mobile_devices',
] as $table) {
    $assert(str_contains($tenant, "'{$table}'"), "Tenant-owned table is missing from tenant registry: {$table}");
}

$assert(str_contains($database, 'tenant_scope_mutation_where($table, $where, $where_params)'), 'db_update must scope tenant-owned mutations.');
$assert(str_contains($database, 'tenant_scope_mutation_where($table, $where, $params)'), 'db_delete must scope tenant-owned deletes.');
$assert(
    str_contains($schema, 'uniq_tenant_type_value (tenant_id, type, value)') &&
    str_contains($tenantMigration, 'ADD UNIQUE KEY uniq_tenant_type_value (tenant_id, type, value)'),
    'allowed_senders must use a tenant-scoped unique key in fresh installs and upgrades.'
);
$assert(
    preg_match('/function\s+tenant_owned_tables\s*\(\)\s*:\s*array\s*\{[\s\S]*?return\s*\[(?<tables>[\s\S]*?)\];/m', $tenant, $owned_match) === 1,
    'Unable to inspect tenant_owned_tables().'
);
$assert(!str_contains($owned_match['tables'], "'tenants'"), 'Global tenants table must not be treated as tenant-owned data.');

$assert(str_contains($allowed, "tenant_sql_filter('allowed_senders', 's'"), 'Allowed sender list must be tenant filtered.');
$assert(str_contains($allowed, 'INSERT INTO allowed_senders (tenant_id, type, value, user_id, active)'), 'Allowed sender upsert must bind tenant_id.');
$assert(str_contains($allowed, "tenant_sql_filter('users', ''"), 'Allowed sender user lookup must be tenant filtered.');
$assert(str_contains($allowed, "db_delete('allowed_senders'"), 'Allowed sender delete must use db_delete tenant guard.');
$assert(str_contains($allowed, "db_update('allowed_senders'"), 'Allowed sender toggle must use db_update tenant guard.');
$assert(!str_contains($allowed, 'DELETE FROM allowed_senders WHERE id = ?'), 'Allowed sender delete must not bypass tenant scoping.');
$assert(!str_contains($allowed, 'UPDATE allowed_senders SET active = NOT active WHERE id = ?'), 'Allowed sender toggle must not bypass tenant scoping.');

$assert(str_contains($users, "tenant_sql_filter('users', ''"), 'User search/autocomplete must be tenant filtered.');
$assert(!str_contains($users, 'UPDATE users SET dashboard_layout = ? WHERE id = ?'), 'User preference writes must use db_update tenant guard.');

$combined_ticket_writes = $ticket_api . "\n" . $ticket_crud . "\n" . $ticket_forms . "\n" . $email_ingest;
$assert(!str_contains($combined_ticket_writes, 'UPDATE tickets SET updated_at = NOW() WHERE id = ?'), 'Ticket touch writes must use db_update tenant guard.');
$assert(!str_contains($combined_ticket_writes, 'UPDATE tickets SET updated_at = ? WHERE id = ?'), 'Ticket touch writes must use db_update tenant guard.');
$assert(substr_count($combined_ticket_writes, "db_update('tickets', ['updated_at'") >= 4, 'Ticket touch writes should consistently use db_update.');

$assert(str_contains($uploads, "tenant_sql_filter('attachments'"), 'Attachment lookups must be tenant filtered.');
$assert(str_contains($uploads, 'attachment_user_can_access'), 'Attachment downloads must go through authorization checks.');
$assert(str_contains($uploads, 'function attachment_user_can_delete'), 'Attachment deletion must have a dedicated permission helper.');
$assert(str_contains($api_router, "'delete-attachment' => 'api_delete_attachment'"), 'Attachment delete API route is missing.');
$assert(str_contains($auth, "'delete-attachment' => 'attachments:write'"), 'Attachment delete API token scope is missing.');
$assert(str_contains($ticket_api, 'attachment_user_can_delete($attachment, $user)'), 'Attachment delete API must enforce attachment delete permission.');
$assert(str_contains($billing_review, 'WHERE t.tenant_id = ?'), 'SaaS report billing review must be tenant isolated.');
$assert(str_contains($tenant, 'function is_platform_admin'), 'Platform admin role helper is missing.');
$assert(str_contains($tenant, 'function require_platform_admin'), 'Platform admin route guard is missing.');
$assert(str_contains($platform, 'require_platform_admin();'), 'Platform console must require platform admin checks.');

echo "SaaS security boundary contract OK\n";
