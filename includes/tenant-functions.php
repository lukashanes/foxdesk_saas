<?php
/**
 * Tenant helpers for the SaaS edition.
 *
 * The current migration path keeps existing single-tenant installs working by
 * assigning all legacy rows to a default tenant. New SaaS work can then add
 * stricter tenant-aware flows incrementally.
 */

function tenant_owned_tables(): array
{
    return [
        'users',
        'organizations',
        'tickets',
        'comments',
        'ticket_time_entries',
        'attachments',
        'ticket_shares',
        'report_shares',
        'ticket_access',
        'activity_log',
        'api_tokens',
        'notifications',
        'allowed_senders',
        'recurring_tasks',
        'report_templates',
        'report_snapshots',
        'ticket_messages',
        'ticket_message_attachments',
        'email_ingest_logs',
        'security_log',
        'debug_log',
        'page_views',
    ];
}

function tenant_scoped_table_has_column(string $table): bool
{
    static $cache = [];

    if (!function_exists('table_exists') || !table_exists($table)) {
        return false;
    }

    if (!array_key_exists($table, $cache)) {
        try {
            validate_sql_identifier($table);
            $cache[$table] = (bool) db_fetch_one("SHOW COLUMNS FROM {$table} LIKE 'tenant_id'");
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
    }

    return $cache[$table];
}

function ensure_tenant_baseline(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        db_query("
            CREATE TABLE IF NOT EXISTS tenants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                primary_domain VARCHAR(255) NULL,
                plan VARCHAR(50) NOT NULL DEFAULT 'starter',
                status ENUM('active', 'trialing', 'past_due', 'suspended', 'canceled') NOT NULL DEFAULT 'active',
                trial_ends_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_domain (primary_domain)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $default = db_fetch_one("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1");
        if (!$default) {
            db_query(
                "INSERT INTO tenants (uuid, name, slug, status, created_at) VALUES (?, 'Default workspace', 'default', 'active', NOW())",
                [tenant_generate_uuid()]
            );
        }
        $default_id = (int) (db_fetch_one("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1")['id'] ?? 1);

        foreach (tenant_owned_tables() as $table) {
            if (!table_exists($table) || column_exists($table, 'tenant_id')) {
                continue;
            }

            try {
                db_query("ALTER TABLE {$table} ADD COLUMN tenant_id INT NULL AFTER id");
                db_query("ALTER TABLE {$table} ADD INDEX idx_tenant_id (tenant_id)");
                db_query("UPDATE {$table} SET tenant_id = ? WHERE tenant_id IS NULL", [$default_id]);
            } catch (Throwable $e) {
                // Some legacy/shared hosts restrict ALTER. Keep the app usable and
                // let the health/test layer surface the missing tenant column.
                error_log("FoxDesk tenant migration skipped {$table}: " . $e->getMessage());
            }
        }

        foreach (tenant_owned_tables() as $table) {
            if (tenant_scoped_table_has_column($table)) {
                db_query("UPDATE {$table} SET tenant_id = ? WHERE tenant_id IS NULL", [$default_id]);
            }
        }
    } catch (Throwable $e) {
        error_log('FoxDesk tenant baseline failed: ' . $e->getMessage());
    }
}

function tenant_generate_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function default_tenant_id(): int
{
    if (!function_exists('table_exists') || !table_exists('tenants')) {
        return 1;
    }

    $row = db_fetch_one("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1");
    return (int) ($row['id'] ?? 1);
}

function current_tenant_id(): int
{
    if (!empty($_SESSION['tenant_id'])) {
        return (int) $_SESSION['tenant_id'];
    }

    return default_tenant_id();
}

function set_current_tenant_from_user(?array $user): void
{
    if (!$user || empty($user['tenant_id'])) {
        $_SESSION['tenant_id'] = default_tenant_id();
        return;
    }

    $_SESSION['tenant_id'] = (int) $user['tenant_id'];
}

function tenant_sql_filter(string $table, string $alias, array &$params): string
{
    if (!tenant_scoped_table_has_column($table)) {
        return '';
    }

    $prefix = $alias !== '' ? $alias . '.' : '';
    $params[] = current_tenant_id();
    return " AND {$prefix}tenant_id = ?";
}

function tenant_value_for_insert(array $data = []): int
{
    if (!empty($data['tenant_id'])) {
        return (int) $data['tenant_id'];
    }

    return current_tenant_id();
}
