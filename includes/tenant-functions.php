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

        $tenant_columns = [
            'owner_user_id' => "ALTER TABLE tenants ADD COLUMN owner_user_id INT NULL AFTER status",
            'billing_email' => "ALTER TABLE tenants ADD COLUMN billing_email VARCHAR(255) NULL AFTER owner_user_id",
            'stripe_customer_id' => "ALTER TABLE tenants ADD COLUMN stripe_customer_id VARCHAR(255) NULL AFTER billing_email",
            'stripe_subscription_id' => "ALTER TABLE tenants ADD COLUMN stripe_subscription_id VARCHAR(255) NULL AFTER stripe_customer_id",
            'subscription_status' => "ALTER TABLE tenants ADD COLUMN subscription_status VARCHAR(50) NOT NULL DEFAULT 'manual' AFTER stripe_subscription_id",
            'max_users' => "ALTER TABLE tenants ADD COLUMN max_users INT NOT NULL DEFAULT 10 AFTER subscription_status",
            'max_agents' => "ALTER TABLE tenants ADD COLUMN max_agents INT NOT NULL DEFAULT 3 AFTER max_users",
            'suspended_at' => "ALTER TABLE tenants ADD COLUMN suspended_at DATETIME NULL AFTER trial_ends_at",
        ];
        foreach ($tenant_columns as $column => $sql) {
            if (!column_exists('tenants', $column)) {
                db_query($sql);
            }
        }

        if (table_exists('users') && !column_exists('users', 'is_platform_admin')) {
            db_query("ALTER TABLE users ADD COLUMN is_platform_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
            db_query("ALTER TABLE users ADD INDEX idx_platform_admin (is_platform_admin)");
        }

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

        if (table_exists('users') && column_exists('users', 'is_platform_admin')) {
            $platform_count = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM users WHERE is_platform_admin = 1")['c'] ?? 0);
            if ($platform_count === 0) {
                db_query(
                    "UPDATE users SET is_platform_admin = 1 WHERE tenant_id = ? AND role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1",
                    [$default_id]
                );
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

function tenant_slug_from_name(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim((string) $slug, '-');
    return $slug !== '' ? substr($slug, 0, 80) : 'workspace';
}

function tenant_unique_slug(string $name): string
{
    $base = tenant_slug_from_name($name);
    $slug = $base;
    $i = 2;
    while (db_fetch_one("SELECT id FROM tenants WHERE slug = ? LIMIT 1", [$slug])) {
        $slug = substr($base, 0, 70) . '-' . $i;
        $i++;
    }
    return $slug;
}

function is_platform_admin(?array $user = null): bool
{
    if (!function_exists('current_user')) {
        return false;
    }
    $user = $user ?: current_user();
    return $user && !empty($user['is_platform_admin']);
}

function require_platform_admin(): void
{
    if (!is_platform_admin()) {
        http_response_code(403);
        header('Location: index.php?page=dashboard');
        exit;
    }
}

function create_tenant_workspace(array $data): array
{
    ensure_tenant_baseline();

    $workspace_name = trim((string) ($data['workspace_name'] ?? ''));
    $admin_email = strtolower(trim((string) ($data['admin_email'] ?? '')));
    $admin_first = trim((string) ($data['admin_first_name'] ?? ''));
    $admin_last = trim((string) ($data['admin_last_name'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($workspace_name === '' || $admin_email === '' || $admin_first === '' || $password === '') {
        throw new InvalidArgumentException('Workspace name, admin email, first name, and password are required.');
    }
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid admin email.');
    }
    if (strlen($password) < 12) {
        throw new InvalidArgumentException('Password must be at least 12 characters.');
    }
    if (db_fetch_one("SELECT id FROM users WHERE email = ? LIMIT 1", [$admin_email])) {
        throw new InvalidArgumentException('A user with this email already exists.');
    }

    $db = get_db();
    $db->beginTransaction();
    try {
        $slug = tenant_unique_slug($workspace_name);
        $tenant_id = (int) db_insert('tenants', [
            'uuid' => tenant_generate_uuid(),
            'name' => $workspace_name,
            'slug' => $slug,
            'status' => $data['status'] ?? 'trialing',
            'plan' => $data['plan'] ?? 'starter',
            'billing_email' => $data['billing_email'] ?? $admin_email,
            'subscription_status' => $data['subscription_status'] ?? 'trialing',
            'max_users' => (int) ($data['max_users'] ?? 10),
            'max_agents' => (int) ($data['max_agents'] ?? 3),
            'trial_ends_at' => $data['trial_ends_at'] ?? date('Y-m-d H:i:s', strtotime('+14 days')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $admin_id = (int) db_insert('users', [
            'tenant_id' => $tenant_id,
            'email' => $admin_email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $admin_first,
            'last_name' => $admin_last,
            'role' => 'admin',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        db_update('tenants', ['owner_user_id' => $admin_id], 'id = ?', [$tenant_id]);

        db_insert('organizations', [
            'tenant_id' => $tenant_id,
            'name' => $workspace_name,
            'contact_email' => $admin_email,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $db->commit();
        return ['tenant_id' => $tenant_id, 'user_id' => $admin_id, 'slug' => $slug];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
