<?php
/**
 * Tenant helpers for the SaaS edition.
 *
 * The current migration path keeps existing single-tenant installs working by
 * assigning all legacy rows to a default tenant. New SaaS work can then add
 * stricter tenant-aware flows incrementally.
 */

require_once __DIR__ . '/cloudflare-email-route-provisioning.php';

function tenant_owned_tables(): array
{
    return [
        'users',
        'organizations',
        'tickets',
        'comments',
        'ticket_time_entries',
        'agent_client_billable_rates',
        'attachments',
        'ticket_shares',
        'report_shares',
        'ticket_access',
        'activity_log',
        'api_tokens',
        'api_token_audit_logs',
        'api_idempotency_keys',
        'pending_deletions',
        'mobile_auth_challenges',
        'mobile_sessions',
        'mobile_devices',
        'mobile_idempotency_keys',
        'notifications',
        'push_subscriptions',
        'allowed_senders',
        'recurring_tasks',
        'report_templates',
        'report_snapshots',
        'billing_usage_events',
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

    if (!in_array($table, tenant_owned_tables(), true)) {
        return false;
    }

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

    schema_require('SaaS workspace', ['tenants'], [
        'tenants' => [
            'uuid', 'slug', 'plan', 'status', 'trial_ends_at', 'owner_user_id',
            'billing_email', 'stripe_customer_id', 'stripe_subscription_id',
            'subscription_status', 'max_users', 'max_agents', 'suspended_at', 'blocked_at',
        ],
        'users' => ['tenant_id', 'is_platform_admin'],
        'attachments' => ['tenant_id', 'storage_driver', 'storage_bucket', 'storage_key'],
    ]);

    $default = db_fetch_one("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1");
    if (!$default) {
        throw new FoxDeskDatabaseUpgradeRequired('SaaS workspace', ['default tenant row']);
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

/**
 * Run background work inside one explicit workspace and always restore the
 * caller's tenant context, including when the job throws.
 */
function tenant_run_in_context(int $tenant_id, callable $callback): mixed
{
    if ($tenant_id <= 0) {
        throw new InvalidArgumentException('A valid tenant id is required.');
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    $had_tenant = array_key_exists('tenant_id', $_SESSION);
    $previous_tenant = $_SESSION['tenant_id'] ?? null;
    $_SESSION['tenant_id'] = $tenant_id;

    try {
        return $callback();
    } finally {
        if ($had_tenant) {
            $_SESSION['tenant_id'] = $previous_tenant;
        } else {
            unset($_SESSION['tenant_id']);
        }
    }
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

function tenant_where_has_scope(string $where): bool
{
    return (bool) preg_match('/(^|[^A-Za-z0-9_])tenant_id([^A-Za-z0-9_]|$)/i', $where);
}

function tenant_scope_mutation_where(string $table, string $where, array &$params): string
{
    if (!tenant_scoped_table_has_column($table) || tenant_where_has_scope($where)) {
        return $where;
    }

    $params[] = current_tenant_id();
    return '(' . $where . ') AND tenant_id = ?';
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

function tenant_humanize_label(string $value): string
{
    $value = trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $value));
    if ($value === '') {
        return 'FoxDesk';
    }

    $words = array_filter(explode(' ', strtolower($value)));
    $words = array_map(static fn($word) => ucfirst($word), $words);

    return implode(' ', $words);
}

function tenant_workspace_name_from_email(string $email): string
{
    $email = strtolower(trim($email));
    $parts = explode('@', $email, 2);
    $local = $parts[0] ?? 'workspace';
    $domain = $parts[1] ?? '';
    $free_mail_domains = [
        'gmail.com',
        'googlemail.com',
        'seznam.cz',
        'email.cz',
        'post.cz',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'icloud.com',
        'me.com',
        'yahoo.com',
        'proton.me',
        'protonmail.com',
    ];

    if ($domain !== '' && !in_array($domain, $free_mail_domains, true)) {
        $domain_parts = array_values(array_filter(explode('.', $domain)));
        $label = $domain_parts[0] ?? '';
        if (in_array($label, ['app', 'helpdesk', 'mail', 'support', 'www'], true) && isset($domain_parts[1])) {
            $label = $domain_parts[1];
        }

        $name = tenant_humanize_label($label);
        return $name !== '' ? $name : 'FoxDesk workspace';
    }

    $name = tenant_humanize_label($local);
    return trim($name . ' workspace');
}

function tenant_admin_first_name_from_email(string $email): string
{
    $local = explode('@', trim($email), 2)[0] ?? 'Admin';
    $local = preg_replace('/[+].*$/', '', $local);
    $parts = preg_split('/[._\-\s]+/', (string) $local);
    $first = is_array($parts) ? ($parts[0] ?? '') : '';
    $name = tenant_humanize_label($first);

    return $name !== '' ? $name : 'Admin';
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
        if (function_exists('foxdesk_is_platform_host') && foxdesk_is_platform_host()) {
            if (function_exists('logout') && is_logged_in()) {
                logout();
            }
            header('Location: index.php?page=login&platform_login_rejected=1');
            exit;
        }

        $login_url = function_exists('foxdesk_platform_url')
            ? foxdesk_platform_url('index.php?page=login')
            : 'index.php?page=login';
        header('Location: ' . $login_url);
        exit;
    }
}

function create_tenant_workspace(array $data): array
{
    ensure_tenant_baseline();

    $admin_email = strtolower(trim((string) ($data['admin_email'] ?? '')));
    $workspace_name = trim((string) ($data['workspace_name'] ?? ''));
    if ($workspace_name === '' && $admin_email !== '') {
        $workspace_name = tenant_workspace_name_from_email($admin_email);
    }
    $admin_first = trim((string) ($data['admin_first_name'] ?? ''));
    if ($admin_first === '' && $admin_email !== '') {
        $admin_first = tenant_admin_first_name_from_email($admin_email);
    }
    $admin_last = trim((string) ($data['admin_last_name'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    if ($password === '') {
        $password = bin2hex(random_bytes(24));
    }

    if ($workspace_name === '' || $admin_email === '' || $admin_first === '') {
        throw new InvalidArgumentException('Workspace name, admin email, and first name are required.');
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
            'plan' => $data['plan'] ?? 'cloud',
            'billing_email' => $data['billing_email'] ?? $admin_email,
            'subscription_status' => $data['subscription_status'] ?? 'trialing',
            'max_users' => (int) ($data['max_users'] ?? 1000000),
            'max_agents' => (int) ($data['max_agents'] ?? 1000000),
            'trial_ends_at' => $data['trial_ends_at'] ?? (function_exists('billing_trial_ends_at_for_new_workspace') ? billing_trial_ends_at_for_new_workspace() : date('Y-m-d H:i:s', strtotime('+14 days'))),
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

        $email_route = ['ok' => true, 'status' => 'skipped', 'reason' => 'not_available'];
        try {
            $email_route = cloudflare_email_routing_provision_workspace_alias([
                'id' => $tenant_id,
                'name' => $workspace_name,
                'slug' => $slug,
                'status' => $data['status'] ?? 'trialing',
            ]);
            if (empty($email_route['ok'])) {
                error_log('FoxDesk workspace email route provisioning warning: ' . json_encode($email_route));
            }
        } catch (Throwable $e) {
            $email_route = [
                'ok' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
            error_log('FoxDesk workspace email route provisioning failed: ' . $e->getMessage());
        }

        return ['tenant_id' => $tenant_id, 'user_id' => $admin_id, 'slug' => $slug, 'email_route' => $email_route];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
