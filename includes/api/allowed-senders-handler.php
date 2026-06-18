<?php
/**
 * API Handler: Allowed Senders Management
 *
 * CRUD operations for the allowed_senders table (email ingestion allowlist).
 */

/**
 * List all allowed senders
 */
function api_allowed_senders_list() {
    if (!is_admin()) {
        api_error('Forbidden', 403);
    }

    $params = [];
    $where = 'WHERE 1 = 1';
    if (function_exists('tenant_sql_filter')) {
        $where .= tenant_sql_filter('allowed_senders', 's', $params);
    }

    $senders = db_fetch_all(
        "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM allowed_senders s
         LEFT JOIN users u ON s.user_id = u.id" .
            (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('users') ? " AND u.tenant_id = s.tenant_id" : "") . "
         {$where}
         ORDER BY s.type, s.value",
        $params
    );

    api_success(['senders' => $senders]);
}

/**
 * Add a new allowed sender
 */
function api_allowed_senders_add() {
    require_admin_post();

    $input = get_json_input();
    $type = trim($input['type'] ?? '');
    $value = strtolower(trim($input['value'] ?? ''));
    $user_id = !empty($input['user_id']) ? (int)$input['user_id'] : null;

    if (!in_array($type, ['email', 'domain'])) {
        api_error(t('Invalid type'));
    }

    if ($value === '') {
        api_error(t('Value is required'));
    }

    // Validate email format
    if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        api_error(t('Invalid email address'));
    }

    // Strip leading @ from domain
    if ($type === 'domain') {
        $value = ltrim($value, '@');
    }

    if ($user_id !== null) {
        $params = [$user_id];
        $user_sql = "SELECT id FROM users WHERE id = ?";
        if (function_exists('tenant_sql_filter')) {
            $user_sql .= tenant_sql_filter('users', '', $params);
        }
        if (!db_fetch_one($user_sql . " LIMIT 1", $params)) {
            api_error(t('User not found'), 404);
        }
    }

    if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('allowed_senders')) {
        db_query(
            "INSERT INTO allowed_senders (tenant_id, type, value, user_id, active) VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), active = 1, updated_at = NOW()",
            [current_tenant_id(), $type, $value, $user_id]
        );
    } else {
        db_query(
            "INSERT INTO allowed_senders (type, value, user_id, active) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), active = 1, updated_at = NOW()",
            [$type, $value, $user_id]
        );
    }

    api_success(['message' => t('Sender added')]);
}

/**
 * Delete an allowed sender
 */
function api_allowed_senders_delete() {
    require_admin_post();

    $input = get_json_input();
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        api_error(t('Invalid ID'));
    }

    db_delete('allowed_senders', 'id = ?', [$id]);

    api_success(['message' => t('Sender deleted')]);
}

/**
 * Toggle active/inactive status
 */
function api_allowed_senders_toggle() {
    require_admin_post();

    $input = get_json_input();
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        api_error(t('Invalid ID'));
    }

    $params = [$id];
    $sql = "SELECT id, active FROM allowed_senders WHERE id = ?";
    if (function_exists('tenant_sql_filter')) {
        $sql .= tenant_sql_filter('allowed_senders', '', $params);
    }
    $sender = db_fetch_one($sql . " LIMIT 1", $params);
    if (!$sender) {
        api_error(t('Sender not found'), 404);
    }

    db_update('allowed_senders', ['active' => empty($sender['active']) ? 1 : 0], 'id = ?', [$id]);

    api_success(['message' => t('Saved')]);
}
