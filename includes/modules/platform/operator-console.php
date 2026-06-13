<?php
/**
 * Operator console helpers for SaaS tenant detail, lifecycle, and owner flows.
 */

function platform_allowed_tenant_statuses(): array
{
    return ['active', 'trialing', 'past_due', 'trial_expired', 'suspended', 'blocked', 'canceled'];
}

function platform_allowed_subscription_statuses(): array
{
    return ['trialing', 'active', 'manual', 'free', 'past_due', 'trial_expired', 'suspended', 'blocked', 'canceled'];
}

function platform_log_operator_action(string $event_type, int $tenant_id, array $context = []): void
{
    if (!function_exists('log_security_event')) {
        return;
    }

    $parts = ['tenant_id=' . $tenant_id];
    foreach ($context as $key => $value) {
        $safe_key = preg_replace('/[^a-zA-Z0-9_:-]/', '_', (string) $key) ?: 'value';
        $safe_value = str_replace([';', "\r", "\n"], ['_', ' ', ' '], (string) $value);
        $parts[] = $safe_key . '=' . $safe_value;
    }

    log_security_event($event_type, (int) ($_SESSION['user_id'] ?? 0), implode(';', $parts));
}

function platform_operator_reason(string $reason, string $fallback): string
{
    $reason = trim($reason);
    if ($reason === '') {
        $reason = $fallback;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($reason, 0, 500);
    }

    return substr($reason, 0, 500);
}

function platform_update_tenant_lifecycle(int $tenant_id, string $status, string $subscription_status): void
{
    $status = trim($status);
    $subscription_status = trim($subscription_status) !== '' ? trim($subscription_status) : 'manual';
    if ($tenant_id <= 0 || !in_array($status, platform_allowed_tenant_statuses(), true)) {
        throw new InvalidArgumentException('Invalid workspace update.');
    }
    if (!in_array($subscription_status, platform_allowed_subscription_statuses(), true)) {
        throw new InvalidArgumentException('Invalid billing status.');
    }

    db_update('tenants', [
        'status' => $status,
        'subscription_status' => $subscription_status,
        'plan' => billing_plan_code(),
        'suspended_at' => in_array($status, ['past_due', 'trial_expired', 'suspended', 'blocked', 'canceled'], true) ? date('Y-m-d H:i:s') : null,
        'blocked_at' => in_array($status, ['trial_expired', 'blocked', 'canceled'], true) ? date('Y-m-d H:i:s') : null,
    ], 'id = ?', [$tenant_id]);
}

function platform_extend_trial(int $tenant_id, int $days): void
{
    $days = max(1, min(90, $days));
    if ($tenant_id <= 0) {
        throw new InvalidArgumentException('Invalid workspace.');
    }

    db_query(
        "UPDATE tenants
         SET status = 'trialing',
             subscription_status = 'trialing',
             trial_ends_at = DATE_ADD(GREATEST(COALESCE(trial_ends_at, NOW()), NOW()), INTERVAL {$days} DAY),
             suspended_at = NULL,
             blocked_at = NULL
         WHERE id = ?",
        [$tenant_id]
    );
}

function platform_block_tenant(int $tenant_id): void
{
    if ($tenant_id <= 0) {
        throw new InvalidArgumentException('Invalid workspace.');
    }

    db_update('tenants', [
        'status' => 'blocked',
        'subscription_status' => 'blocked',
        'suspended_at' => date('Y-m-d H:i:s'),
        'blocked_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$tenant_id]);
}

function platform_reactivate_tenant(int $tenant_id, string $reason = ''): void
{
    if ($tenant_id <= 0) {
        throw new InvalidArgumentException('Invalid workspace.');
    }

    $reason = platform_operator_reason($reason, 'Manual reactivation approved by platform operator.');

    db_update('tenants', [
        'status' => 'active',
        'subscription_status' => 'manual',
        'billing_override_reason' => $reason,
        'billing_override_at' => date('Y-m-d H:i:s'),
        'billing_override_by' => (int) ($_SESSION['user_id'] ?? 0),
        'suspended_at' => null,
        'blocked_at' => null,
    ], 'id = ?', [$tenant_id]);
}

function platform_grant_free_access(int $tenant_id, string $reason = ''): void
{
    if ($tenant_id <= 0) {
        throw new InvalidArgumentException('Invalid workspace.');
    }

    $reason = platform_operator_reason($reason, 'Operator approved free access.');

    db_update('tenants', [
        'status' => 'active',
        'subscription_status' => 'free',
        'plan' => billing_plan_code(),
        'billing_override_reason' => $reason,
        'billing_override_at' => date('Y-m-d H:i:s'),
        'billing_override_by' => (int) ($_SESSION['user_id'] ?? 0),
        'suspended_at' => null,
        'blocked_at' => null,
    ], 'id = ?', [$tenant_id]);
}

function platform_find_owner(int $tenant_id): ?array
{
    $tenant = db_fetch_one("SELECT owner_user_id FROM tenants WHERE id = ? LIMIT 1", [$tenant_id]);
    if (!$tenant) {
        return null;
    }

    $deleted_filter = column_exists('users', 'deleted_at') ? "AND deleted_at IS NULL" : "";
    $owner_id = (int) ($tenant['owner_user_id'] ?? 0);
    if ($owner_id > 0) {
        $owner = db_fetch_one(
            "SELECT * FROM users WHERE id = ? AND tenant_id = ? {$deleted_filter} LIMIT 1",
            [$owner_id, $tenant_id]
        );
        if ($owner) {
            return $owner;
        }
    }

    return db_fetch_one(
        "SELECT * FROM users WHERE tenant_id = ? AND role = 'admin' AND is_active = 1 {$deleted_filter} ORDER BY id ASC LIMIT 1",
        [$tenant_id]
    ) ?: null;
}

function platform_create_owner_reset(int $tenant_id, array $owner): array
{
    require_once BASE_PATH . '/includes/security-helpers.php';

    $owner_id = (int) ($owner['id'] ?? 0);
    if ($tenant_id <= 0 || $owner_id <= 0) {
        throw new InvalidArgumentException('Workspace owner is missing.');
    }

    $token = generate_reset_token();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    db_update('users', [
        'reset_token' => hash_reset_token($token),
        'reset_token_expires' => $expires,
    ], 'id = ? AND tenant_id = ?', [$owner_id, $tenant_id]);

    $base_url = function_exists('get_app_url') ? get_app_url() : (defined('APP_URL') ? APP_URL : '');
    $reset_link = rtrim((string) $base_url, '/') . '/index.php?page=reset-password&token=' . $token;

    return [
        'reset_link' => $reset_link,
        'expires_at' => $expires,
    ];
}

function platform_send_owner_reset(int $tenant_id): array
{
    require_once BASE_PATH . '/includes/mailer.php';

    $owner = platform_find_owner($tenant_id);
    if (!$owner) {
        throw new InvalidArgumentException('Workspace owner is missing.');
    }

    $reset = platform_create_owner_reset($tenant_id, $owner);
    $sent = send_password_reset_email(
        (string) $owner['email'],
        (string) ($owner['first_name'] ?? 'Owner'),
        (string) $reset['reset_link']
    );

    if (function_exists('log_security_event')) {
        log_security_event('platform_owner_reset_requested', (int) ($_SESSION['user_id'] ?? 0), 'tenant_id=' . $tenant_id . ';owner_id=' . (int) $owner['id']);
    }

    return array_merge($reset, [
        'sent' => (bool) $sent,
        'owner' => $owner,
    ]);
}

function platform_invite_or_set_owner(int $tenant_id, string $email, string $first_name, string $last_name = ''): array
{
    require_once BASE_PATH . '/includes/security-helpers.php';
    require_once BASE_PATH . '/includes/mailer.php';

    $email = strtolower(trim($email));
    $first_name = trim($first_name);
    $last_name = trim($last_name);
    if ($tenant_id <= 0 || $email === '' || $first_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid owner email and first name.');
    }

    $existing = db_fetch_one("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    if ($existing && (int) ($existing['tenant_id'] ?? 0) !== $tenant_id) {
        throw new InvalidArgumentException('This email already belongs to another workspace.');
    }

    if ($existing) {
        $user_id = (int) $existing['id'];
        db_update('users', [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'admin',
            'is_active' => 1,
            'deleted_at' => null,
        ], 'id = ? AND tenant_id = ?', [$user_id, $tenant_id]);
        $owner = db_fetch_one("SELECT * FROM users WHERE id = ? AND tenant_id = ? LIMIT 1", [$user_id, $tenant_id]);
    } else {
        $user_id = (int) db_insert('users', [
            'tenant_id' => $tenant_id,
            'email' => $email,
            'password' => password_hash(generate_password(18), PASSWORD_DEFAULT),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'admin',
            'is_active' => 1,
            'language' => 'en',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $owner = db_fetch_one("SELECT * FROM users WHERE id = ? AND tenant_id = ? LIMIT 1", [$user_id, $tenant_id]);
    }

    if (!$owner) {
        throw new RuntimeException('Owner account could not be prepared.');
    }

    db_update('tenants', [
        'owner_user_id' => (int) $owner['id'],
        'billing_email' => $email,
    ], 'id = ?', [$tenant_id]);

    $reset = platform_create_owner_reset($tenant_id, $owner);
    $sent = send_password_reset_email(
        (string) $owner['email'],
        (string) ($owner['first_name'] ?? 'Owner'),
        (string) $reset['reset_link']
    );

    if (function_exists('log_security_event')) {
        log_security_event('platform_owner_invited', (int) ($_SESSION['user_id'] ?? 0), 'tenant_id=' . $tenant_id . ';owner_id=' . (int) $owner['id']);
    }

    return array_merge($reset, [
        'sent' => (bool) $sent,
        'owner' => $owner,
    ]);
}

function platform_create_migration_token(int $tenant_id): array
{
    require_once BASE_PATH . '/includes/migration-functions.php';

    return migration_bridge_create_connection(
        $tenant_id,
        (int) ($_SESSION['user_id'] ?? 0),
        'Self-hosted sync'
    );
}

function platform_tenant_detail_context(int $tenant_id): ?array
{
    if ($tenant_id <= 0) {
        return null;
    }

    $tenant = db_fetch_one("
        SELECT
          t.*,
          u.email AS owner_email,
          u.first_name AS owner_first_name,
          u.last_name AS owner_last_name
        FROM tenants t
        LEFT JOIN users u ON u.id = t.owner_user_id
        WHERE t.id = ?
        LIMIT 1
    ", [$tenant_id]);
    if (!$tenant) {
        return null;
    }

    $migration_connections = [];
    if (function_exists('migration_bridge_ensure_connections_table')) {
        try {
            migration_bridge_ensure_connections_table();
            $migration_connections = db_fetch_all("
                SELECT id, label, source_url, source_version, status, last_seen_at, created_at, expires_at, cutover_at,
                       attachment_sync_count, attachment_sync_bytes, attachment_sync_last_at, attachment_sync_last_key
                FROM migration_connections
                WHERE tenant_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT 6
            ", [$tenant_id]);
        } catch (Throwable $e) {
            // Keep tenant detail available even if the migration table cannot be prepared.
        }
    }

    $deleted_filter = column_exists('users', 'deleted_at') ? "AND deleted_at IS NULL" : "";
    $users = db_fetch_all("
        SELECT id, email, first_name, last_name, role, is_active, created_at
        FROM users
        WHERE tenant_id = ? {$deleted_filter}
        ORDER BY role = 'admin' DESC, is_active DESC, id ASC
        LIMIT 12
    ", [$tenant_id]);

    $history = [];
    if (table_exists('billing_stripe_events')) {
        foreach (db_fetch_all("
            SELECT event_type AS title, status, error_message AS detail, created_at
            FROM billing_stripe_events
            WHERE tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 8
        ", [$tenant_id]) as $row) {
            $history[] = [
                'kind' => 'Stripe',
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'detail' => (string) ($row['detail'] ?? ''),
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
    if (table_exists('billing_usage_reports')) {
        foreach (db_fetch_all("
            SELECT CONCAT('storage usage ', period_key) AS title, status, CONCAT(quantity, ' extra GB') AS detail, created_at
            FROM billing_usage_reports
            WHERE tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 8
        ", [$tenant_id]) as $row) {
            $history[] = [
                'kind' => 'Usage report',
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'detail' => (string) $row['detail'],
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
    if (table_exists('billing_trial_email_events')) {
        foreach (db_fetch_all("
            SELECT event_type AS title, status, error_message AS detail, created_at
            FROM billing_trial_email_events
            WHERE tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 8
        ", [$tenant_id]) as $row) {
            $history[] = [
                'kind' => 'Trial email',
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'detail' => (string) ($row['detail'] ?? ''),
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
    usort($history, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
    $history = array_slice($history, 0, 12);

    $usage_events = [];
    if (table_exists('billing_usage_events')) {
        $usage_events = db_fetch_all("
            SELECT event_type, SUM(quantity) AS quantity, MAX(created_at) AS latest_at
            FROM billing_usage_events
            WHERE tenant_id = ?
              AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
            GROUP BY event_type
            ORDER BY latest_at DESC
            LIMIT 8
        ", [$tenant_id]);
    }

    return [
        'tenant' => $tenant,
        'usage' => billing_tenant_usage($tenant_id),
        'owner' => platform_find_owner($tenant_id),
        'users' => $users,
        'migration_connections' => $migration_connections,
        'history' => $history,
        'usage_events' => $usage_events,
    ];
}
