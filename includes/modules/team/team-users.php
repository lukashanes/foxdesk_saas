<?php
/**
 * Team/users admin read model and form helpers.
 */

function team_users_table_capabilities(): array
{
    $ai_agent_available = team_users_ensure_ai_agent_schema();

    return [
        'ai_agent' => $ai_agent_available,
        'email_notifications' => function_exists('column_exists') && column_exists('users', 'email_notifications_enabled'),
        'in_app_notifications' => function_exists('column_exists') && column_exists('users', 'in_app_notifications_enabled'),
        'in_app_sound' => function_exists('column_exists') && column_exists('users', 'in_app_sound_enabled'),
        'contact_phone' => function_exists('column_exists') && column_exists('users', 'contact_phone'),
        'notes' => function_exists('column_exists') && column_exists('users', 'notes'),
        'deleted_at' => function_exists('column_exists') && column_exists('users', 'deleted_at'),
    ];
}

function team_users_schema_column_exists(string $table, string $column): bool
{
    if (function_exists('column_exists_uncached')) {
        return column_exists_uncached($table, $column);
    }

    return function_exists('column_exists') && column_exists($table, $column);
}

function team_users_ensure_ai_agent_schema(): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    $available = false;
    if (!function_exists('table_exists') || !table_exists('users')) {
        return false;
    }

    try {
        if (!team_users_schema_column_exists('users', 'is_ai_agent')) {
            db_query("ALTER TABLE users ADD COLUMN is_ai_agent TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
        }

        if (!team_users_schema_column_exists('users', 'ai_model')) {
            db_query("ALTER TABLE users ADD COLUMN ai_model VARCHAR(100) NULL DEFAULT NULL AFTER is_ai_agent");
        }

        $available = team_users_schema_column_exists('users', 'is_ai_agent');
    } catch (Throwable $e) {
        error_log('AI agent schema check failed: ' . $e->getMessage());
        $available = false;
    }

    return $available;
}

function team_users_filter_state(array $input): array
{
    return [
        'search' => trim((string) ($input['search'] ?? '')),
        'role' => in_array(($input['role'] ?? ''), ['user', 'agent', 'admin'], true) ? (string) $input['role'] : '',
        'status' => in_array(($input['status'] ?? ''), ['active', 'inactive'], true) ? (string) $input['status'] : '',
    ];
}

function team_users_tenant_filter(string $table, string $alias, array &$params): string
{
    if (function_exists('tenant_sql_filter')) {
        return tenant_sql_filter($table, $alias, $params);
    }

    if (function_exists('column_exists') && column_exists($table, 'tenant_id') && function_exists('current_tenant_id')) {
        $params[] = current_tenant_id();
        return ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'tenant_id = ?';
    }

    return '';
}

function team_users_valid_organization_ids(array $organizations): array
{
    return array_map('intval', array_column($organizations, 'id'));
}

function team_users_filter_organization_ids($organization_ids, ?array $valid_organization_ids = null): array
{
    $ids = normalize_organization_ids($organization_ids);
    if ($valid_organization_ids === null) {
        return $ids;
    }

    $valid = array_map('intval', $valid_organization_ids);
    return array_values(array_intersect($ids, $valid));
}

function team_users_normalize_organization_assignment($organization_id, $membership_ids, ?array $valid_organization_ids = null): array
{
    $primary_id = !empty($organization_id) ? (int) $organization_id : null;
    $memberships = team_users_filter_organization_ids($membership_ids, $valid_organization_ids);

    if ($primary_id !== null && $valid_organization_ids !== null && !in_array($primary_id, $valid_organization_ids, true)) {
        $primary_id = null;
    }

    if ($primary_id !== null && !in_array($primary_id, $memberships, true)) {
        $memberships[] = $primary_id;
    }

    $memberships = team_users_filter_organization_ids($memberships, $valid_organization_ids);
    if ($primary_id === null && !empty($memberships)) {
        $primary_id = (int) $memberships[0];
    }

    return [
        'organization_id' => $primary_id,
        'organization_membership_ids' => $memberships,
    ];
}

function team_users_permission_payload(string $role, ?int $organization_id, array $organization_membership_ids, array $input, ?array $valid_organization_ids = null): ?array
{
    if ($role === 'admin') {
        return [
            'ticket_scope' => 'all',
            'organization_ids' => team_users_filter_organization_ids($organization_membership_ids, $valid_organization_ids),
            'can_archive' => true,
            'can_view_edit_history' => true,
            'can_import_md' => true,
            'can_view_time' => true,
            'can_view_timeline' => true,
        ];
    }

    if (!in_array($role, ['agent', 'user'], true)) {
        return null;
    }

    $ticket_scope = (string) ($input['ticket_scope'] ?? 'all');
    if (!in_array($ticket_scope, ['all', 'assigned', 'organization', 'own'], true)) {
        $ticket_scope = $role === 'user'
            ? ($organization_id !== null ? 'organization' : 'own')
            : 'all';
    }

    $scope_organization_ids = $ticket_scope === 'organization'
        ? team_users_filter_organization_ids($input['scope_organization_ids'] ?? [], $valid_organization_ids)
        : [];

    $effective_organization_ids = team_users_filter_organization_ids(array_merge(
        $organization_membership_ids,
        $scope_organization_ids
    ), $valid_organization_ids);

    return [
        'ticket_scope' => $ticket_scope,
        'organization_ids' => $effective_organization_ids,
        'can_archive' => $role === 'agent' && isset($input['can_archive']),
        'can_view_edit_history' => isset($input['can_view_edit_history']),
        'can_import_md' => $role === 'agent' && isset($input['can_import_md']),
        'can_view_time' => isset($input['can_view_time']),
        'can_view_timeline' => isset($input['can_view_timeline']),
    ];
}

function team_users_fetch(array $filters, array $capabilities): array
{
    $join = 'LEFT JOIN organizations o ON u.organization_id = o.id';
    if (function_exists('tenant_scoped_table_has_column')
        && tenant_scoped_table_has_column('users')
        && tenant_scoped_table_has_column('organizations')) {
        $join .= ' AND o.tenant_id = u.tenant_id';
    }

    $sql = "SELECT u.*, o.name as organization_name
            FROM users u
            {$join}
            WHERE 1=1";
    $params = [];
    $sql .= team_users_tenant_filter('users', 'u', $params);
    $sql .= " AND u.email NOT LIKE 'deleted-user-%@invalid.local'";

    if (!empty($capabilities['deleted_at'])) {
        $sql .= ' AND u.deleted_at IS NULL';
    }
    if (!empty($capabilities['ai_agent'])) {
        $sql .= ' AND (u.is_ai_agent = 0 OR u.is_ai_agent IS NULL)';
    }

    if (($filters['search'] ?? '') !== '') {
        $search_parts = [
            'u.first_name LIKE ?',
            'u.last_name LIKE ?',
            'u.email LIKE ?',
        ];
        if (!empty($capabilities['contact_phone'])) {
            $search_parts[] = 'u.contact_phone LIKE ?';
        }
        if (!empty($capabilities['notes'])) {
            $search_parts[] = 'u.notes LIKE ?';
        }
        $sql .= ' AND (' . implode(' OR ', $search_parts) . ')';
        $search_term = '%' . $filters['search'] . '%';
        foreach ($search_parts as $_) {
            $params[] = $search_term;
        }
    }

    if (($filters['role'] ?? '') !== '') {
        $sql .= ' AND u.role = ?';
        $params[] = $filters['role'];
    }
    if (($filters['status'] ?? '') !== '') {
        $sql .= ' AND u.is_active = ?';
        $params[] = $filters['status'] === 'active' ? 1 : 0;
    }

    $sql .= " ORDER BY FIELD(u.role, 'user', 'agent', 'admin'), u.first_name, u.last_name";
    return db_fetch_all($sql, $params);
}

function team_users_time_totals(?string $range_start, ?string $range_end): array
{
    if (!function_exists('ticket_time_table_exists') || !ticket_time_table_exists()) {
        return [];
    }

    $dur = sql_timer_duration_minutes();
    $sql = "SELECT tte.user_id, SUM({$dur}) as total_minutes
            FROM ticket_time_entries tte
            LEFT JOIN users u ON u.id = tte.user_id
            WHERE 1=1";
    $params = [];
    $sql .= team_users_tenant_filter('users', 'u', $params);

    if ($range_start && $range_end) {
        $sql .= ' AND tte.started_at >= ? AND tte.started_at <= ?';
        $params[] = $range_start;
        $params[] = $range_end;
    }

    $sql .= ' GROUP BY tte.user_id';
    $totals = [];
    foreach (db_fetch_all($sql, $params) as $row) {
        $totals[(int) $row['user_id']] = (int) $row['total_minutes'];
    }

    return $totals;
}

function team_ai_agents_fetch(bool $deleted_at_column_exists): array
{
    $sql = 'SELECT u.* FROM users u WHERE u.is_ai_agent = 1';
    $params = [];
    $sql .= team_users_tenant_filter('users', 'u', $params);
    if ($deleted_at_column_exists) {
        $sql .= ' AND u.deleted_at IS NULL';
    }
    $sql .= ' ORDER BY u.is_active DESC, u.first_name';

    return db_fetch_all($sql, $params);
}

function team_ai_agent_tokens_fetch(array $ai_agents): array
{
    if (empty($ai_agents)) {
        return [];
    }

    $ai_ids = array_map('intval', array_column($ai_agents, 'id'));
    $placeholders = implode(',', array_fill(0, count($ai_ids), '?'));
    $sql = 'SELECT * FROM api_tokens WHERE 1=1';
    $params = [];
    $sql .= team_users_tenant_filter('api_tokens', '', $params);
    $sql .= " AND user_id IN ({$placeholders}) ORDER BY created_at DESC";
    $params = array_merge($params, $ai_ids);

    try {
        return db_fetch_all($sql, $params);
    } catch (Throwable $e) {
        return [];
    }
}

function team_ai_agent_token_scope_groups(): array
{
    return [
        'tickets_read' => [
            'label' => 'Read tickets',
            'description' => 'View Work queues, tickets, users, and clients visible to this agent.',
            'scopes' => ['work:read', 'tickets:read', 'users:read', 'clients:read'],
        ],
        'tickets_write' => [
            'label' => 'Create and update tickets',
            'description' => 'Create tickets and update ticket fields or status.',
            'scopes' => ['tickets:write'],
        ],
        'comments_write' => [
            'label' => 'Add replies',
            'description' => 'Add public or internal comments to tickets.',
            'scopes' => ['comments:write'],
        ],
        'time_read' => [
            'label' => 'Read time',
            'description' => 'View time entries visible to this agent.',
            'scopes' => ['time:read'],
        ],
        'time_write' => [
            'label' => 'Log time',
            'description' => 'Add and control time entries.',
            'scopes' => ['time:write'],
        ],
        'attachments_read' => [
            'label' => 'Read attachments',
            'description' => 'View ticket attachment metadata.',
            'scopes' => ['attachments:read'],
        ],
        'attachments_write' => [
            'label' => 'Upload attachments',
            'description' => 'Upload files to tickets.',
            'scopes' => ['attachments:write'],
        ],
        'reports_read' => [
            'label' => 'Read reports',
            'description' => 'Read report and billing review data.',
            'scopes' => ['reports:read'],
        ],
        'reports_write' => [
            'label' => 'Publish reports',
            'description' => 'Prepare and publish reports.',
            'scopes' => ['reports:write'],
        ],
        'notifications_read' => [
            'label' => 'Read notifications',
            'description' => 'Read notifications visible to this agent.',
            'scopes' => ['notifications:read'],
        ],
        'notifications_write' => [
            'label' => 'Update notifications',
            'description' => 'Mark notifications as read.',
            'scopes' => ['notifications:write'],
        ],
    ];
}

function team_ai_agent_token_default_scope_groups(): array
{
    return [
        'tickets_read',
        'tickets_write',
        'comments_write',
        'time_read',
        'time_write',
        'attachments_read',
        'attachments_write',
        'reports_read',
    ];
}

function team_ai_agent_token_scopes_from_input(array $input): array
{
    $groups = team_ai_agent_token_scope_groups();
    $selected = $input['api_token_scope_groups'] ?? team_ai_agent_token_default_scope_groups();
    if (!is_array($selected)) {
        $selected = [$selected];
    }

    $scopes = [];
    foreach ($selected as $group_key) {
        $group_key = (string) $group_key;
        if (!isset($groups[$group_key])) {
            continue;
        }
        foreach ($groups[$group_key]['scopes'] as $scope) {
            $scopes[$scope] = true;
        }
    }

    if (empty($scopes)) {
        foreach (['work:read', 'tickets:read'] as $scope) {
            $scopes[$scope] = true;
        }
    }

    return array_keys($scopes);
}

function team_ai_agent_revoke_active_tokens(int $user_id): void
{
    if ($user_id <= 0 || !function_exists('revoke_api_token') || !function_exists('api_tokens_table_exists') || !api_tokens_table_exists()) {
        return;
    }

    try {
        $sql = 'SELECT id FROM api_tokens WHERE user_id = ? AND is_active = 1';
        $params = [$user_id];
        $sql .= team_users_tenant_filter('api_tokens', '', $params);
        if (function_exists('column_exists') && column_exists('api_tokens', 'revoked_at')) {
            $sql .= ' AND revoked_at IS NULL';
        }
        foreach (db_fetch_all($sql, $params) as $token) {
            revoke_api_token((int) $token['id']);
        }
    } catch (Throwable $e) {
        // Token cleanup should not block saving the agent.
    }
}
