<?php
/**
 * Authentication Functions
 */

if (!function_exists('foxdesk_request_is_https')) {
    function foxdesk_request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!foxdesk_request_uses_trusted_proxy()) {
            return false;
        }

        $forwarded_proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwarded_proto === 'https') {
            return true;
        }

        $forwarded_ssl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
        if ($forwarded_ssl === 'on') {
            return true;
        }

        $cf_visitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
        if ($cf_visitor !== '' && stripos($cf_visitor, '"scheme":"https"') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('foxdesk_request_uses_trusted_proxy')) {
    function foxdesk_request_uses_trusted_proxy(): bool
    {
        if (defined('TRUST_PROXY') && TRUST_PROXY) {
            return true;
        }

        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote === '') {
            return false;
        }

        if ($remote === '127.0.0.1' || $remote === '::1' || str_starts_with($remote, '10.') || str_starts_with($remote, '192.168.')) {
            return true;
        }

        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $remote) === 1) {
            return true;
        }

        $trusted = trim((string) getenv('FOXDESK_TRUSTED_PROXIES'));
        if ($trusted === '') {
            return false;
        }

        $proxies = array_filter(array_map('trim', explode(',', $trusted)));
        return in_array($remote, $proxies, true);
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Check if users.deleted_at column exists (for backward compatibility).
 */
function users_deleted_at_column_exists()
{
    return column_exists('users', 'deleted_at');
}

/**
 * Get current user
 */
function current_user($force_refresh = false)
{
    if (!is_logged_in()) {
        return null;
    }

    static $user = null;

    if ($user === null || $force_refresh) {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
        $params = [$_SESSION['user_id']];
        if (users_deleted_at_column_exists()) {
            $sql .= " AND deleted_at IS NULL";
        }
        if (!empty($_SESSION['tenant_id']) && tenant_scoped_table_has_column('users')) {
            $sql .= " AND tenant_id = ?";
            $params[] = (int) $_SESSION['tenant_id'];
        }
        $user = db_fetch_one($sql, $params);
        if ($user && function_exists('set_current_tenant_from_user')) {
            set_current_tenant_from_user($user);
        }
    }

    return $user;
}

/**
 * Update session with user data
 */
function refresh_user_session()
{
    $user = current_user(true);
    if ($user) {
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_avatar'] = $user['avatar'] ?? '';
        $allowed_langs = ['en', 'cs', 'de', 'it', 'es'];
        $lang = strtolower(trim((string) ($user['language'] ?? '')));
        if (!in_array($lang, $allowed_langs, true)) {
            $lang = strtolower(trim((string) get_setting('app_language', 'en')));
            if (!in_array($lang, $allowed_langs, true)) {
                $lang = 'en';
            }
        }
        $_SESSION['lang'] = $lang;
        unset($_SESSION['lang_override']);
    }
}

/**
 * Check if current user is admin
 */
function is_admin()
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

/**
 * Check if current user is agent or admin
 */
function is_agent()
{
    $user = current_user();
    return $user && in_array($user['role'], ['agent', 'admin']);
}

/**
 * Attempt login
 */
function login($email, $password)
{
    $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    $params = [$email];
    if (users_deleted_at_column_exists()) {
        $sql .= " AND deleted_at IS NULL";
    }
    $sql .= " ORDER BY id ASC LIMIT 1";
    $user = db_fetch_one($sql, $params);

    if ($user && password_verify($password, $user['password'])) {
        return login_user_session($user);
    }

    return false;
}

/**
 * Sign in a verified user object without requiring a password challenge.
 *
 * Used by flows that already performed a stronger one-time verification step,
 * such as SaaS signup magic links.
 */
function login_user_session(array $user): bool
{
    if (empty($user['id']) || empty($user['email'])) {
        return false;
    }

    if (!empty($_COOKIE[foxdesk_remember_cookie_name()])) {
        clear_remember_cookie();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_name'] = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $_SESSION['user_role'] = (string) ($user['role'] ?? 'user');

    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $allowed_langs = ['en', 'cs', 'de', 'it', 'es'];
    $lang = strtolower(trim((string) ($user['language'] ?? '')));
    if (!in_array($lang, $allowed_langs, true)) {
        $lang = strtolower(trim((string) get_setting('app_language', 'en')));
        if (!in_array($lang, $allowed_langs, true)) {
            $lang = 'en';
        }
    }
    $_SESSION['lang'] = $lang;
    unset($_SESSION['lang_override']);

    return true;
}

/**
 * Logout user
 */
function logout()
{
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id) {
        clear_remember_token($user_id);
    } else {
        clear_remember_cookie();
    }

    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    }
}

// =============================================================================
// REMEMBER-ME (PERSISTENT LOGIN)
// =============================================================================

function foxdesk_remember_cookie_name(): string
{
    $context = function_exists('foxdesk_session_context') ? foxdesk_session_context() : 'workspace';
    return preg_replace('/[^A-Za-z0-9_]/', '_', 'foxdesk_remember_' . $context) ?: 'foxdesk_remember';
}

/**
 * Remember-me cookies are intentionally disabled for accounts protected by 2FA.
 * A persistent login token would otherwise bypass the second factor entirely.
 */
function remember_me_allowed_for_user(array $user): bool
{
    if (empty($user)) {
        return false;
    }

    if (defined('BASE_PATH') && file_exists(BASE_PATH . '/includes/totp.php')) {
        require_once BASE_PATH . '/includes/totp.php';
    }

    $role = (string) ($user['role'] ?? '');
    $totp_enabled = function_exists('is_2fa_enabled')
        ? is_2fa_enabled($user)
        : !empty($user['totp_enabled']);
    $role_requires_2fa = $role !== '' && function_exists('is_2fa_required_for_role')
        ? is_2fa_required_for_role($role)
        : false;

    return !$totp_enabled && !$role_requires_2fa;
}

/**
 * Ensure the remember_token column exists on users table (auto-migration).
 */
function ensure_remember_token_column()
{
    static $checked = false;
    if ($checked) return true;
    $checked = true;

    if (!column_exists('users', 'remember_token')) {
        try {
            db_query("ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) DEFAULT NULL");
        } catch (Throwable $e) {
            return false;
        }
    }
    return true;
}

/**
 * Create a remember-me token for the user and set a 30-day cookie.
 */
function set_remember_token($user_id)
{
    if (!ensure_remember_token_column()) return;

    $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
    $params = [(int) $user_id];
    if (tenant_scoped_table_has_column('users')) {
        $sql .= " AND tenant_id = ?";
        $params[] = current_tenant_id();
    }
    $user = db_fetch_one($sql, $params);
    if (!$user || !remember_me_allowed_for_user($user)) {
        try {
            db_update('users', ['remember_token' => null], 'id = ?', [(int) $user_id]);
        } catch (Throwable $e) {
            // Non-critical
        }
        clear_remember_cookie();
        return;
    }

    $token = bin2hex(random_bytes(32)); // 64 hex chars
    $hash = hash('sha256', $token);

    db_update('users', ['remember_token' => $hash], 'id = ?', [$user_id]);

    $is_https = foxdesk_request_is_https();
    setcookie(foxdesk_remember_cookie_name(), $token, [
        'expires'  => time() + (defined('REMEMBER_ME_DURATION') ? REMEMBER_ME_DURATION : 2592000),
        'path'     => '/',
        'httponly'  => true,
        'secure'   => $is_https,
        'samesite' => 'Lax',
    ]);
}

/**
 * Validate the remember-me cookie and auto-login the user.
 *
 * @return bool True if the user was successfully auto-logged in.
 */
function validate_remember_token()
{
    $cookie_name = foxdesk_remember_cookie_name();
    if (empty($_COOKIE[$cookie_name])) return false;
    if (!ensure_remember_token_column()) return false;

    $token = $_COOKIE[$cookie_name];
    if (strlen($token) !== 64) {
        clear_remember_cookie();
        return false;
    }

    $hash = hash('sha256', $token);

    $sql = "SELECT * FROM users WHERE remember_token = ? AND is_active = 1";
    if (users_deleted_at_column_exists()) {
        $sql .= " AND deleted_at IS NULL";
    }
    $user = db_fetch_one($sql, [$hash]);

    if (!$user) {
        clear_remember_cookie();
        return false;
    }

    if (!remember_me_allowed_for_user($user)) {
        clear_remember_token((int) $user['id']);
        return false;
    }

    // Auto-login: populate session
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role']  = $user['role'];
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $allowed_langs = ['en', 'cs', 'de', 'it', 'es'];
    $lang = strtolower(trim((string) ($user['language'] ?? '')));
    if (!in_array($lang, $allowed_langs, true)) {
        $lang = strtolower(trim((string) get_setting('app_language', 'en')));
        if (!in_array($lang, $allowed_langs, true)) {
            $lang = 'en';
        }
    }
    $_SESSION['lang'] = $lang;
    unset($_SESSION['lang_override']);

    // Rotate token for extra security (token is single-use)
    set_remember_token($user['id']);

    return true;
}

/**
 * Clear the remember-me token from DB for a specific user.
 */
function clear_remember_token($user_id)
{
    if (!ensure_remember_token_column()) return;
    try {
        db_update('users', ['remember_token' => null], 'id = ?', [$user_id]);
    } catch (Throwable $e) {
        // Non-critical
    }
    clear_remember_cookie();
}

/**
 * Delete the remember-me cookie.
 */
function clear_remember_cookie()
{
    $is_https = foxdesk_request_is_https();
    $cookie_name = foxdesk_remember_cookie_name();
    setcookie($cookie_name, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly'  => true,
        'secure'   => $is_https,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[$cookie_name]);
}

/**
 * Get user by ID
 */
function get_user($id)
{
    static $cache = [];
    $id = (int) $id;
    $tenant_id = function_exists('current_tenant_id') ? current_tenant_id() : 0;
    $key = $tenant_id . ':' . $id;
    if (!isset($cache[$key])) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $params = [$id];
        if (tenant_scoped_table_has_column('users')) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenant_id;
        }
        $cache[$key] = db_fetch_one($sql, $params);
    }
    return $cache[$key];
}

/**
 * Get all users
 */
function get_all_users()
{
    $sql = "SELECT * FROM users";
    $conditions = [];
    if (users_deleted_at_column_exists()) {
        $conditions[] = "deleted_at IS NULL";
    }
    $params = [];
    if (tenant_scoped_table_has_column('users')) {
        $conditions[] = "tenant_id = ?";
        $params[] = current_tenant_id();
    }
    $conditions[] = "email NOT LIKE 'deleted-user-%@invalid.local'";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY first_name, last_name";
    return db_fetch_all($sql, $params);
}

/**
 * Get all client users (role = user)
 */
function get_clients()
{
    $sql = "SELECT * FROM users WHERE role = 'user'";
    if (users_deleted_at_column_exists()) {
        $sql .= " AND deleted_at IS NULL";
    }
    $params = [];
    if (tenant_scoped_table_has_column('users')) {
        $sql .= " AND tenant_id = ?";
        $params[] = current_tenant_id();
    }
    $sql .= " AND email NOT LIKE 'deleted-user-%@invalid.local'";
    $sql .= " ORDER BY first_name, last_name";
    return db_fetch_all($sql, $params);
}

/**
 * Create new user
 */
function create_user($email, $password, $first_name, $last_name = '', $role = 'user', $language = 'en')
{
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $data = [
        'email' => $email,
        'password' => $hash,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => $role,
        'language' => $language,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    if (tenant_scoped_table_has_column('users')) {
        $data['tenant_id'] = current_tenant_id();
    }

    return db_insert('users', $data);
}

/**
 * Update user password
 */
function update_password($user_id, $new_password)
{
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $where = 'id = ?';
    $params = [$user_id];
    if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('users')) {
        $where .= ' AND tenant_id = ?';
        $params[] = current_tenant_id();
    }
    return db_update('users', ['password' => $hash], $where, $params);
}
/**
 * Check if currently impersonating
 */
function is_impersonating()
{
    return isset($_SESSION['impersonator_id']);
}

// =============================================================================
// API TOKEN AUTHENTICATION
// =============================================================================

/**
 * Check if the current request uses Bearer token authentication
 */
function is_api_token_request()
{
    return bearer_token_from_request() !== '';
}

function bearer_token_from_request(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'Bearer ') !== 0) {
        return '';
    }

    return trim(substr($header, 7));
}

/**
 * Check if the api_tokens table exists
 */
function api_tokens_table_exists()
{
    return table_exists('api_tokens');
}

function mobile_sessions_table_exists(): bool
{
    return table_exists('mobile_sessions');
}

function api_token_scope_catalog(array $user = null): array
{
    $user = $user ?: (current_user() ?: []);
    $is_staff = in_array((string) ($user['role'] ?? ''), ['admin', 'agent'], true);
    $can_time = function_exists('can_view_time') ? can_view_time($user) : $is_staff;

    $catalog = [
        'work:read' => 'Read Work queues',
        'tickets:read' => 'Read tickets',
        'tickets:write' => 'Create and update tickets',
        'comments:write' => 'Add ticket comments',
        'attachments:read' => 'Read attachment metadata',
        'attachments:write' => 'Upload attachments',
        'notifications:read' => 'Read notifications',
        'notifications:write' => 'Mark notifications read',
    ];

    if ($is_staff) {
        $catalog['users:read'] = 'Read users visible to this account';
        $catalog['clients:read'] = 'Read client overviews';
    }

    if ($is_staff || $can_time) {
        $catalog['time:read'] = 'Read time entries';
    }

    if ($is_staff) {
        $catalog['time:write'] = 'Add and control time entries';
    }

    if (($user['role'] ?? '') === 'admin' || $can_time) {
        $catalog['reports:read'] = 'Read report billing reviews';
    }

    if (($user['role'] ?? '') === 'admin') {
        $catalog['reports:write'] = 'Prepare and publish reports';
    }

    return $catalog;
}

function api_token_allowed_scopes_for_user(array $user): array
{
    if (($user['role'] ?? '') === 'admin') {
        return array_keys(api_token_scope_catalog($user));
    }

    return array_keys(api_token_scope_catalog($user));
}

function api_token_normalize_scopes($scopes, array $user): array
{
    if ($scopes === null) {
        return ['*'];
    }

    if (is_string($scopes)) {
        $decoded = json_decode($scopes, true);
        $scopes = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $scopes);
    }

    $allowed = array_fill_keys(api_token_allowed_scopes_for_user($user), true);
    $normalized = [];
    foreach ((array) $scopes as $scope) {
        $scope = strtolower(trim((string) $scope));
        if ($scope === '' || $scope === '*') {
            continue;
        }
        if (isset($allowed[$scope])) {
            $normalized[$scope] = true;
        }
    }

    if (empty($normalized)) {
        $fallback = api_token_allowed_scopes_for_user($user);
        foreach (['work:read', 'tickets:read'] as $scope) {
            if (in_array($scope, $fallback, true)) {
                $normalized[$scope] = true;
            }
        }
    }

    return array_keys($normalized);
}

function api_token_current_row(): ?array
{
    return isset($GLOBALS['api_token_row']) && is_array($GLOBALS['api_token_row'])
        ? $GLOBALS['api_token_row']
        : null;
}

function api_token_scopes_from_row(array $token_row): array
{
    if (!array_key_exists('scopes_json', $token_row) || trim((string) ($token_row['scopes_json'] ?? '')) === '') {
        return ['*'];
    }

    $decoded = json_decode((string) $token_row['scopes_json'], true);
    if (!is_array($decoded) || empty($decoded)) {
        return ['*'];
    }

    return array_values(array_unique(array_map(static fn($scope) => strtolower(trim((string) $scope)), $decoded)));
}

function api_token_has_scope(string $scope): bool
{
    $token = api_token_current_row();
    if (!$token) {
        return false;
    }

    $scopes = api_token_scopes_from_row($token);
    if (in_array('*', $scopes, true)) {
        return true;
    }

    $scope = strtolower(trim($scope));
    if (in_array($scope, $scopes, true)) {
        return true;
    }

    [$resource] = array_pad(explode(':', $scope, 2), 2, '');
    return $resource !== '' && in_array($resource . ':*', $scopes, true);
}

function api_token_required_scope_for_action(string $action): ?string
{
    $map = [
        'upload' => 'attachments:write',
        'delete-attachment' => 'attachments:write',
        'agent-me' => 'work:read',
        'agent-list-statuses' => 'tickets:read',
        'agent-list-priorities' => 'tickets:read',
        'agent-list-users' => 'users:read',
        'agent-create-ticket' => 'tickets:write',
        'agent-list-tickets' => 'tickets:read',
        'agent-get-ticket' => 'tickets:read',
        'agent-add-comment' => 'comments:write',
        'agent-update-status' => 'tickets:write',
        'agent-log-time' => 'time:write',
        'app-shell' => 'work:read',
        'app-home' => 'work:read',
        'app-ticket-list' => 'tickets:read',
        'app-ticket-detail' => 'tickets:read',
        'app-ticket-actions' => 'tickets:read',
        'app-create-ticket' => 'tickets:write',
        'app-add-comment' => 'comments:write',
        'app-attachment-metadata' => 'attachments:read',
        'app-ticket-timer' => 'time:read',
        'app-ticket-timer-action' => 'time:write',
        'app-log-time' => 'time:write',
        'app-client-overview' => 'clients:read',
        'app-reporting-review' => 'reports:read',
        'app-notifications' => 'notifications:read',
        'app-notifications-summary' => 'notifications:read',
        'app-notification-read-state' => 'notifications:write',
    ];

    return $map[$action] ?? null;
}

function api_token_enforce_action_scope(string $action): void
{
    if (empty($GLOBALS['is_api_token_auth'])) {
        return;
    }

    $required = api_token_required_scope_for_action($action);
    if ($required === null) {
        api_error('This endpoint is not available for API tokens.', 403);
    }

    if (!api_token_has_scope($required)) {
        api_error('API token scope is not allowed for this action.', 403);
    }
}

function api_token_action_is_write(string $action): bool
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
}

function api_token_rate_limit_check(string $action): void
{
    $token = api_token_current_row();
    if (!$token || !table_exists('api_token_audit_logs')) {
        return;
    }

    $limit = (int) (getenv('FOXDESK_API_TOKEN_RATE_LIMIT_PER_MINUTE') ?: 120);
    if ($limit <= 0) {
        return;
    }

    try {
        $count = db_fetch_one(
            "SELECT COUNT(*) AS total FROM api_token_audit_logs WHERE token_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [(int) $token['id']]
        );
        if ((int) ($count['total'] ?? 0) >= $limit) {
            api_error('API token rate limit exceeded.', 429);
        }
    } catch (Throwable $e) {
        // Never fail a valid request only because rate-limit bookkeeping failed.
    }
}

function api_token_request_id(): string
{
    $incoming = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($incoming !== '' && preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $incoming)) {
        return $incoming;
    }

    if (empty($GLOBALS['api_request_id'])) {
        $GLOBALS['api_request_id'] = bin2hex(random_bytes(12));
    }

    return $GLOBALS['api_request_id'];
}

function api_token_idempotency_key(): string
{
    $key = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
    if ($key === '' || strlen($key) > 128 || !preg_match('/^[A-Za-z0-9_.:-]+$/', $key)) {
        return '';
    }

    return $key;
}

function api_idempotency_request_hash(string $action): string
{
    $raw = (string) file_get_contents('php://input');
    $post = !empty($_POST) ? json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    return hash('sha256', strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) . "\n" . $action . "\n" . $raw . "\n" . $post);
}

function api_idempotency_replay_if_available(string $action): void
{
    $token = api_token_current_row();
    $key = api_token_idempotency_key();
    if (!$token || $key === '' || !api_token_action_is_write($action) || !table_exists('api_idempotency_keys')) {
        return;
    }

    $request_hash = api_idempotency_request_hash($action);
    $GLOBALS['api_idempotency'] = [
        'key' => $key,
        'request_hash' => $request_hash,
        'action' => $action,
    ];

    $row = db_fetch_one(
        "SELECT * FROM api_idempotency_keys WHERE token_id = ? AND action = ? AND idempotency_key = ? AND expires_at > NOW() LIMIT 1",
        [(int) $token['id'], $action, $key]
    );

    if (!$row) {
        return;
    }

    if (!hash_equals((string) $row['request_hash'], $request_hash)) {
        api_error('Idempotency key was already used with a different request.', 409);
    }

    if (!empty($row['response_json'])) {
        http_response_code((int) ($row['status_code'] ?? 200));
        header('Content-Type: application/json');
        header('X-Idempotent-Replay: true');
        echo $row['response_json'];
        exit;
    }
}

function api_idempotency_store_success(array $response): void
{
    $token = api_token_current_row();
    $state = $GLOBALS['api_idempotency'] ?? null;
    if (!$token || !is_array($state) || empty($state['key']) || !table_exists('api_idempotency_keys')) {
        return;
    }

    $data = [
        'token_id' => (int) $token['id'],
        'user_id' => (int) $token['user_id'],
        'idempotency_key' => (string) $state['key'],
        'action' => (string) $state['action'],
        'request_hash' => (string) $state['request_hash'],
        'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status_code' => http_response_code() ?: 200,
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', time() + 86400),
    ];
    if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('api_idempotency_keys')) {
        $data['tenant_id'] = (int) ($token['tenant_id'] ?? current_tenant_id());
    }

    try {
        db_insert('api_idempotency_keys', $data);
    } catch (Throwable $e) {
        // Duplicate means another request stored it first; the operation has already succeeded.
    }
}

function api_token_resource_from_response(string $action, array $response): array
{
    foreach ([
        'ticket_id' => 'ticket',
        'comment_id' => 'comment',
        'time_entry_id' => 'time_entry',
        'attachment_id' => 'attachment',
    ] as $key => $type) {
        if (isset($response[$key])) {
            return [$type, (int) $response[$key]];
        }
    }

    if (isset($response['data']) && is_array($response['data'])) {
        return api_token_resource_from_response($action, $response['data']);
    }

    return [str_starts_with($action, 'app-') ? substr($action, 4) : $action, null];
}

function api_token_log_action(string $action, array $response = [], int $status_code = 200): void
{
    $token = api_token_current_row();
    $user = current_user();
    if (!$token || !$user || !table_exists('api_token_audit_logs')) {
        return;
    }

    [$resource_type, $resource_id] = api_token_resource_from_response($action, $response);
    $data = [
        'token_id' => (int) $token['id'],
        'user_id' => (int) $user['id'],
        'action' => $action,
        'method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
        'resource_type' => $resource_type,
        'resource_id' => $resource_id,
        'status_code' => $status_code,
        'request_id' => api_token_request_id(),
        'idempotency_key' => api_token_idempotency_key() ?: null,
        'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('api_token_audit_logs')) {
        $data['tenant_id'] = (int) ($token['tenant_id'] ?? current_tenant_id());
    }

    try {
        db_insert('api_token_audit_logs', $data);
    } catch (Throwable $e) {
        // Audit failure should not break the user-facing action.
    }
}

/**
 * Authenticate a request using a Bearer API token.
 *
 * Extracts the token from the Authorization header, hashes it, and looks up
 * the hash in the api_tokens table. On success, populates $_SESSION so that
 * current_user(), is_admin(), is_agent() etc. work transparently.
 *
 * @return array|null  The user row on success, null on failure.
 */
function authenticate_api_token()
{
    if (!api_tokens_table_exists()) {
        return null;
    }

    $raw_token = bearer_token_from_request();
    if ($raw_token === '' || strlen($raw_token) < 10) {
        return null;
    }

    $token_hash = hash('sha256', $raw_token);

    $sql = "SELECT * FROM api_tokens WHERE token_hash = ? AND is_active = 1";
    if (column_exists('api_tokens', 'revoked_at')) {
        $sql .= " AND revoked_at IS NULL";
    }
    $token_row = db_fetch_one($sql, [$token_hash]);

    if (!$token_row) {
        return null;
    }

    // Check expiration
    if (!empty($token_row['expires_at']) && strtotime($token_row['expires_at']) < time()) {
        return null;
    }

    // Load the linked user
    $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
    $params = [$token_row['user_id']];
    if (users_deleted_at_column_exists()) {
        $sql .= " AND deleted_at IS NULL";
    }
    if (!empty($token_row['tenant_id']) && tenant_scoped_table_has_column('users')) {
        $sql .= " AND tenant_id = ?";
        $params[] = (int) $token_row['tenant_id'];
    }
    $user = db_fetch_one($sql, $params);

    if (!$user) {
        return null;
    }

    // Populate session so existing helpers work
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['api_token_id'] = (int) $token_row['id'];
    $GLOBALS['api_token_row'] = $token_row;
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    // Update last_used_at (fire-and-forget, don't fail on error)
    update_token_last_used((int) $token_row['id']);

    return $user;
}

/**
 * Authenticate a native mobile access token.
 *
 * Mobile sessions are separate from long-lived automation API tokens. They are
 * short-lived, refreshable, and safe for iOS Keychain storage.
 */
function authenticate_mobile_session()
{
    if (!mobile_sessions_table_exists()) {
        return null;
    }

    $raw_token = bearer_token_from_request();
    if ($raw_token === '' || strlen($raw_token) < 20) {
        return null;
    }

    $token_hash = hash('sha256', $raw_token);
    $now = date('Y-m-d H:i:s');
    $session = db_fetch_one(
        "SELECT * FROM mobile_sessions
         WHERE access_token_hash = ?
           AND revoked_at IS NULL
           AND access_expires_at > ?
         LIMIT 1",
        [$token_hash, $now]
    );

    if (!$session) {
        return null;
    }

    $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
    $params = [(int) $session['user_id']];
    if (users_deleted_at_column_exists()) {
        $sql .= " AND deleted_at IS NULL";
    }
    if (!empty($session['tenant_id']) && tenant_scoped_table_has_column('users')) {
        $sql .= " AND tenant_id = ?";
        $params[] = (int) $session['tenant_id'];
    }
    $user = db_fetch_one($sql, $params);
    if (!$user) {
        return null;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['mobile_session_id'] = (int) $session['id'];
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    try {
        db_update('mobile_sessions', ['last_used_at' => $now], 'id = ?', [(int) $session['id']]);
    } catch (Throwable $e) {
        // Non-critical for auth success.
    }

    return $user;
}

/**
 * Update the last_used_at timestamp of an API token.
 */
function update_token_last_used($token_id)
{
    try {
        $data = ['last_used_at' => date('Y-m-d H:i:s')];
        if (column_exists('api_tokens', 'last_used_ip')) {
            $data['last_used_ip'] = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
        }
        if (column_exists('api_tokens', 'last_used_user_agent')) {
            $data['last_used_user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;
        }
        db_update('api_tokens', $data, 'id = ?', [$token_id]);
    } catch (Throwable $e) {
        // Non-critical — don't break the request
    }
}

/**
 * Generate a new API token.
 *
 * @param int    $user_id  The user this token belongs to.
 * @param string $name     A human-readable label.
 * @param string|null $expires_at  Optional expiration datetime.
 * @return array  ['token' => full plain-text token, 'id' => row id, 'scopes' => granted scopes]
 */
function generate_api_token($user_id, $name, $expires_at = null, $scopes = null)
{
    $token_user = get_user((int) $user_id);
    if (!$token_user) {
        return ['token' => null, 'id' => null, 'scopes' => []];
    }

    $granted_scopes = api_token_normalize_scopes($scopes, $token_user);
    $raw_token = 'fdx_' . bin2hex(random_bytes(24));
    $token_hash = hash('sha256', $raw_token);
    $token_prefix = substr($raw_token, 0, 8);

    $data = [
        'user_id' => (int) $user_id,
        'name' => $name,
        'token_hash' => $token_hash,
        'token_prefix' => $token_prefix,
        'expires_at' => $expires_at,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if (column_exists('api_tokens', 'scopes_json')) {
        $data['scopes_json'] = json_encode($granted_scopes, JSON_UNESCAPED_SLASHES);
    }
    if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('api_tokens')) {
        $data['tenant_id'] = (int) ($token_user['tenant_id'] ?? current_tenant_id());
    }

    $id = db_insert('api_tokens', $data);

    return ['token' => $raw_token, 'id' => $id, 'scopes' => $granted_scopes];
}

/**
 * Revoke an API token (soft-disable).
 */
function revoke_api_token($token_id)
{
    $data = ['is_active' => 0];
    if (column_exists('api_tokens', 'revoked_at')) {
        $data['revoked_at'] = date('Y-m-d H:i:s');
    }
    $where = 'id = ?';
    $params = [$token_id];
    if (function_exists('tenant_scoped_table_has_column') && tenant_scoped_table_has_column('api_tokens')) {
        $where .= ' AND tenant_id = ?';
        $params[] = current_tenant_id();
    }
    return db_update('api_tokens', $data, $where, $params);
}
