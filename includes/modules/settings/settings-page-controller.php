<?php
/** SaaS settings request controller and page-local action handlers. */
// Include update functions
require_once BASE_PATH . '/includes/update-functions.php';
require_once BASE_PATH . '/includes/update-check-functions.php';
require_once BASE_PATH . '/includes/admin-crud-helper.php';
require_once BASE_PATH . '/includes/modules/agent/operating-instructions.php';

$settings_audit = function ($event_type, $context = [], $level = 'info') {
    $user_id = current_user()['id'] ?? null;
    if (function_exists('log_security_event')) {
        $payload = is_string($context) ? $context : json_encode($context, JSON_UNESCAPED_UNICODE);
        log_security_event((string) $event_type, $user_id, (string) ($payload ?: ''));
    }
    if (function_exists('debug_log')) {
        debug_log((string) $event_type, $context, $level, 'settings');
    }
};

function settings_render_update_redirect(string $redirect_url): void
{
    $theme_version = defined('APP_VERSION') ? (string) APP_VERSION : (string) time();
    $safe_redirect_url = htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8');
    $safe_theme_version = htmlspecialchars($theme_version, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta http-equiv="refresh" content="2;url=' . $safe_redirect_url . '">';
    echo '<title>' . e(t('Updating...')) . '</title>';
    echo '<link href="assets/css/theme.min.css?v=' . $safe_theme_version . '" rel="stylesheet">';
    echo '</head><body class="system-notice-page">';
    echo '<main class="system-notice-card" role="status" aria-live="polite">';
    echo '<div class="system-notice-spinner" aria-hidden="true"></div>';
    echo '<h1 class="system-notice-title">' . e(t('Update complete')) . '</h1>';
    echo '<p class="system-notice-copy">' . e(t('Redirecting...')) . '</p>';
    echo '</main></body></html>';
    exit;
}

function settings_api_redirect(): void
{
    redirect('admin', ['section' => 'settings', 'tab' => 'api']);
}

function settings_api_scope_presets(array $catalog): array
{
    $filter = static function (array $scopes) use ($catalog): array {
        return array_values(array_filter($scopes, static fn(string $scope): bool => isset($catalog[$scope])));
    };

    $read = $filter([
        'work:read',
        'tickets:read',
        'attachments:read',
        'notifications:read',
        'users:read',
        'clients:read',
        'time:read',
        'reports:read',
    ]);

    $write = $filter(array_merge($read, [
        'tickets:write',
        'comments:write',
        'attachments:write',
        'notifications:write',
        'time:write',
        'reports:write',
    ]));

    $all = $filter(array_merge($write, [
        'delete:write',
    ]));

    return [
        'read_only' => [
            'label' => 'Read only',
            'description' => 'Can view tickets, clients, reports, attachments, and time without changing data.',
            'scopes' => $read,
        ],
        'read_write' => [
            'label' => 'Read & write',
            'description' => 'Can create and update tickets, comments, time, attachments, reports, and notifications.',
            'scopes' => $write,
        ],
        'all' => [
            'label' => 'All',
            'description' => 'Includes read/write plus deleting comments and time entries. Use only for trusted admins or agents.',
            'scopes' => $all,
        ],
    ];
}

function settings_api_scopes_from_preset(string $preset, array $catalog, array $fallback_scopes = []): array
{
    $presets = settings_api_scope_presets($catalog);
    if (isset($presets[$preset])) {
        return $presets[$preset]['scopes'];
    }

    return $fallback_scopes;
}

function settings_fetch_user_api_tokens(int $user_id): array
{
    if (!function_exists('api_tokens_table_exists') || !api_tokens_table_exists()) {
        return [];
    }

    $params = [$user_id];
    $sql = "SELECT * FROM api_tokens WHERE user_id = ?";
    if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('api_tokens')) {
        $sql .= " AND tenant_id = ?";
        $params[] = current_tenant_id();
    }
    $sql .= " ORDER BY created_at DESC";

    return db_fetch_all($sql, $params);
}

function settings_handle_api_access_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $user = current_user();
    if (!$user) {
        return;
    }

    if (isset($_POST['create_api_token']) && function_exists('generate_api_token')) {
        require_csrf_token();
        $token_name = trim((string) ($_POST['api_token_name'] ?? ''));
        $selected_scopes = $_POST['api_token_scopes'] ?? [];
        $scope_catalog = function_exists('api_token_scope_catalog') ? api_token_scope_catalog($user) : [];
        $selected_scopes = settings_api_scopes_from_preset(
            (string) ($_POST['api_permission_preset'] ?? 'read_write'),
            $scope_catalog,
            is_array($selected_scopes) ? $selected_scopes : []
        );

        if ($token_name === '') {
            flash(t('Token name is required.'), 'error');
            settings_api_redirect();
        }

        $token_result = generate_api_token(
            (int) $user['id'],
            $token_name,
            null,
            $selected_scopes
        );

        if (!empty($token_result['token'])) {
            $_SESSION['new_profile_api_token'] = $token_result['token'];
            $_SESSION['new_profile_api_token_scopes'] = $token_result['scopes'] ?? [];
            flash(t('API key created. Copy it now.'), 'success');
        } else {
            flash(t('Could not create API key.'), 'error');
        }
        settings_api_redirect();
    }

    if (isset($_POST['revoke_api_token']) && function_exists('revoke_api_token')) {
        require_csrf_token();
        $token_id = (int) ($_POST['token_id'] ?? 0);
        $params = [$token_id, (int) $user['id']];
        $sql = "SELECT id FROM api_tokens WHERE id = ? AND user_id = ?";
        if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('api_tokens')) {
            $sql .= " AND tenant_id = ?";
            $params[] = current_tenant_id();
        }
        $token = $token_id > 0 && function_exists('api_tokens_table_exists') && api_tokens_table_exists()
            ? db_fetch_one($sql, $params)
            : null;

        if ($token) {
            revoke_api_token($token_id);
            flash(t('API key revoked.'), 'success');
        } else {
            flash(t('API key not found.'), 'error');
        }
        settings_api_redirect();
    }
}

// Handle form submissions
settings_handle_post_request($settings_audit);
settings_handle_api_access_post();

// Refresh settings
$settings = get_settings();
$tab = settings_tab_from_request($_GET);

// Process POST handlers for workflow tab before any layout output
settings_handle_workflow_post($tab, $_POST);
