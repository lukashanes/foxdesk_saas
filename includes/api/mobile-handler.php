<?php
/**
 * API Handler: native mobile app authentication and device registration.
 */

function mobile_api_ensure_tables(): void
{
    db_query("CREATE TABLE IF NOT EXISTS mobile_auth_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NULL,
        user_id INT NOT NULL,
        challenge_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_mobile_challenge_hash (challenge_hash),
        INDEX idx_tenant_id (tenant_id),
        INDEX idx_user (user_id),
        INDEX idx_expires (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db_query("CREATE TABLE IF NOT EXISTS mobile_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NULL,
        user_id INT NOT NULL,
        platform VARCHAR(20) NOT NULL DEFAULT 'ios',
        device_id VARCHAR(191) NULL,
        device_name VARCHAR(255) NULL,
        app_version VARCHAR(50) NULL,
        access_token_hash CHAR(64) NOT NULL,
        refresh_token_hash CHAR(64) NOT NULL,
        token_prefix VARCHAR(16) NOT NULL,
        access_expires_at DATETIME NOT NULL,
        refresh_expires_at DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        last_used_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_mobile_access_token_hash (access_token_hash),
        UNIQUE KEY uniq_mobile_refresh_token_hash (refresh_token_hash),
        INDEX idx_tenant_id (tenant_id),
        INDEX idx_user (user_id),
        INDEX idx_device (device_id),
        INDEX idx_access_expires (access_expires_at),
        INDEX idx_refresh_expires (refresh_expires_at),
        INDEX idx_revoked (revoked_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db_query("CREATE TABLE IF NOT EXISTS mobile_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NULL,
        user_id INT NOT NULL,
        mobile_session_id INT NULL,
        platform VARCHAR(20) NOT NULL DEFAULT 'ios',
        device_id VARCHAR(191) NULL,
        device_name VARCHAR(255) NULL,
        app_version VARCHAR(50) NULL,
        apns_environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
        apns_token TEXT NOT NULL,
        apns_token_hash CHAR(64) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_mobile_apns_token_hash (apns_token_hash),
        INDEX idx_tenant_id (tenant_id),
        INDEX idx_user (user_id),
        INDEX idx_session (mobile_session_id),
        INDEX idx_device (device_id),
        INDEX idx_active (is_active),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (mobile_session_id) REFERENCES mobile_sessions(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function mobile_api_short_string($value, int $max): string
{
    return mb_substr(trim((string) $value), 0, $max);
}

function mobile_api_find_user_by_email(string $email): ?array
{
    $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    $params = [$email];
    if (users_deleted_at_column_exists()) {
        $sql .= " AND deleted_at IS NULL";
    }
    $sql .= " ORDER BY id ASC LIMIT 1";

    $user = db_fetch_one($sql, $params);
    return $user ?: null;
}

function mobile_api_user_payload(array $user): array
{
    return [
        'id' => (int) ($user['id'] ?? 0),
        'email' => (string) ($user['email'] ?? ''),
        'first_name' => (string) ($user['first_name'] ?? ''),
        'last_name' => (string) ($user['last_name'] ?? ''),
        'name' => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))),
        'role' => (string) ($user['role'] ?? ''),
        'language' => (string) ($user['language'] ?? 'en'),
        'tenant_id' => isset($user['tenant_id']) ? (int) $user['tenant_id'] : null,
    ];
}

function mobile_api_token_pair(): array
{
    $access_token = 'fdm_at_' . bin2hex(random_bytes(32));
    $refresh_token = 'fdm_rt_' . bin2hex(random_bytes(40));

    return [
        'access_token' => $access_token,
        'refresh_token' => $refresh_token,
        'access_token_hash' => hash('sha256', $access_token),
        'refresh_token_hash' => hash('sha256', $refresh_token),
        'token_prefix' => substr($access_token, 0, 16),
    ];
}

function mobile_api_set_session_context(array $user, int $mobile_session_id = 0): void
{
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_name'] = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $_SESSION['user_role'] = (string) ($user['role'] ?? '');
    if ($mobile_session_id > 0) {
        $_SESSION['mobile_session_id'] = $mobile_session_id;
    }
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }
}

function mobile_api_session_payload(array $tokens, int $access_seconds = 3600, int $refresh_seconds = 5184000): array
{
    return [
        'token_type' => 'Bearer',
        'access_token' => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'],
        'expires_in' => $access_seconds,
        'refresh_expires_in' => $refresh_seconds,
    ];
}

function mobile_api_issue_session(array $user, array $input): array
{
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $access_seconds = 3600;
    $refresh_seconds = 60 * 24 * 60 * 60;
    $now = time();
    $tokens = mobile_api_token_pair();

    $mobile_session_id = (int) db_insert('mobile_sessions', [
        'user_id' => (int) $user['id'],
        'platform' => 'ios',
        'device_id' => mobile_api_short_string($input['device_id'] ?? '', 191) ?: null,
        'device_name' => mobile_api_short_string($input['device_name'] ?? '', 255) ?: null,
        'app_version' => mobile_api_short_string($input['app_version'] ?? '', 50) ?: null,
        'access_token_hash' => $tokens['access_token_hash'],
        'refresh_token_hash' => $tokens['refresh_token_hash'],
        'token_prefix' => $tokens['token_prefix'],
        'access_expires_at' => date('Y-m-d H:i:s', $now + $access_seconds),
        'refresh_expires_at' => date('Y-m-d H:i:s', $now + $refresh_seconds),
        'last_used_at' => date('Y-m-d H:i:s', $now),
        'created_at' => date('Y-m-d H:i:s', $now),
    ]);
    mobile_api_set_session_context($user, $mobile_session_id);

    return mobile_api_session_payload($tokens, $access_seconds, $refresh_seconds);
}

function mobile_api_auth_response(array $user, array $session): array
{
    $response = [
        'requires_2fa' => false,
        'session' => $session,
        'user' => mobile_api_user_payload($user),
    ];

    if (function_exists('app_shell_payload')) {
        $response['app_shell'] = app_shell_payload($user);
    }
    if (function_exists('app_feed_payload')) {
        $response['home'] = app_feed_payload($user, 5);
    }

    return $response;
}

function mobile_api_create_2fa_challenge(array $user): string
{
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $challenge_token = 'fdm_ch_' . bin2hex(random_bytes(24));
    db_insert('mobile_auth_challenges', [
        'user_id' => (int) $user['id'],
        'challenge_hash' => hash('sha256', $challenge_token),
        'expires_at' => date('Y-m-d H:i:s', time() + 600),
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    return $challenge_token;
}

function api_mobile_login(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    mobile_api_ensure_tables();

    $input = get_json_input();
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $rate_key = function_exists('rate_limit_key') ? rate_limit_key('mobile_login', $email) : 'mobile_login';

    if (rate_limit_is_blocked($rate_key, 8, 900)) {
        api_error('Too many attempts. Please wait and try again.', 429);
    }
    if ($email === '' || $password === '') {
        api_error('Email and password are required.', 422);
    }

    $user = mobile_api_find_user_by_email($email);
    if (!$user || !password_verify($password, (string) ($user['password'] ?? ''))) {
        rate_limit_record($rate_key, 900);
        if (function_exists('log_security_event')) {
            log_security_event('mobile_login_failed', null, 'email=' . $email);
        }
        api_error('Invalid email or password.', 401);
    }

    require_once BASE_PATH . '/includes/totp.php';
    ensure_totp_columns();

    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $user_has_2fa = is_2fa_enabled($user);
    $role_requires_2fa = is_2fa_required_for_role((string) ($user['role'] ?? ''));
    if (!$user_has_2fa && $role_requires_2fa) {
        rate_limit_clear($rate_key);
        api_error('Two-factor setup is required before mobile sign-in. Please finish setup in the web app.', 403);
    }

    if ($user_has_2fa) {
        $challenge_token = mobile_api_create_2fa_challenge($user);
        rate_limit_clear($rate_key);
        if (function_exists('log_security_event')) {
            log_security_event('mobile_2fa_challenge', (int) $user['id']);
        }
        api_success([
            'requires_2fa' => true,
            'challenge_token' => $challenge_token,
            'expires_in' => 600,
            'user' => [
                'email' => (string) $user['email'],
                'name' => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))),
            ],
        ]);
    }

    $session = mobile_api_issue_session($user, $input);
    rate_limit_clear($rate_key);
    if (function_exists('log_security_event')) {
        log_security_event('mobile_login_success', (int) $user['id']);
    }

    api_success(mobile_api_auth_response($user, $session));
}

function api_mobile_verify_2fa(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    mobile_api_ensure_tables();

    $input = get_json_input();
    $challenge_token = trim((string) ($input['challenge_token'] ?? ''));
    $code = trim((string) ($input['code'] ?? ''));
    if ($challenge_token === '' || $code === '') {
        api_error('Challenge token and code are required.', 422);
    }

    $challenge = db_fetch_one(
        "SELECT * FROM mobile_auth_challenges
         WHERE challenge_hash = ?
           AND consumed_at IS NULL
           AND expires_at > ?
         LIMIT 1",
        [hash('sha256', $challenge_token), date('Y-m-d H:i:s')]
    );
    if (!$challenge) {
        api_error('Two-factor challenge expired or invalid.', 401);
    }

    $user = db_fetch_one("SELECT * FROM users WHERE id = ? AND is_active = 1", [(int) $challenge['user_id']]);
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $rate_key = 'mobile_2fa_' . (int) $user['id'];
    if (rate_limit_is_blocked($rate_key, 6, 300)) {
        api_error('Too many attempts. Please sign in again.', 429);
    }

    require_once BASE_PATH . '/includes/totp.php';
    $valid = !empty($user['totp_secret']) && totp_verify((string) $user['totp_secret'], $code);
    if (!$valid && function_exists('verify_backup_code')) {
        $valid = verify_backup_code((int) $user['id'], $code);
    }

    if (!$valid) {
        rate_limit_record($rate_key, 300);
        if (function_exists('log_security_event')) {
            log_security_event('mobile_2fa_failed', (int) $user['id']);
        }
        api_error('Invalid two-factor code.', 401);
    }

    db_update(
        'mobile_auth_challenges',
        ['consumed_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [(int) $challenge['id']]
    );
    rate_limit_clear($rate_key);

    $session = mobile_api_issue_session($user, $input);
    if (function_exists('log_security_event')) {
        log_security_event('mobile_2fa_verified', (int) $user['id']);
    }

    api_success(mobile_api_auth_response($user, $session));
}

function api_mobile_refresh(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    mobile_api_ensure_tables();

    $input = get_json_input();
    $refresh_token = trim((string) ($input['refresh_token'] ?? ''));
    if ($refresh_token === '') {
        api_error('Refresh token is required.', 422);
    }

    $mobile_session = db_fetch_one(
        "SELECT * FROM mobile_sessions
         WHERE refresh_token_hash = ?
           AND revoked_at IS NULL
           AND refresh_expires_at > ?
         LIMIT 1",
        [hash('sha256', $refresh_token), date('Y-m-d H:i:s')]
    );
    if (!$mobile_session) {
        api_error('Refresh token expired or invalid.', 401);
    }

    $user = db_fetch_one("SELECT * FROM users WHERE id = ? AND is_active = 1", [(int) $mobile_session['user_id']]);
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $access_seconds = 3600;
    $refresh_seconds = 60 * 24 * 60 * 60;
    $now = time();
    $tokens = mobile_api_token_pair();

    db_update('mobile_sessions', [
        'access_token_hash' => $tokens['access_token_hash'],
        'refresh_token_hash' => $tokens['refresh_token_hash'],
        'token_prefix' => $tokens['token_prefix'],
        'device_id' => mobile_api_short_string($input['device_id'] ?? ($mobile_session['device_id'] ?? ''), 191) ?: null,
        'device_name' => mobile_api_short_string($input['device_name'] ?? ($mobile_session['device_name'] ?? ''), 255) ?: null,
        'app_version' => mobile_api_short_string($input['app_version'] ?? ($mobile_session['app_version'] ?? ''), 50) ?: null,
        'access_expires_at' => date('Y-m-d H:i:s', $now + $access_seconds),
        'refresh_expires_at' => date('Y-m-d H:i:s', $now + $refresh_seconds),
        'last_used_at' => date('Y-m-d H:i:s', $now),
    ], 'id = ?', [(int) $mobile_session['id']]);

    mobile_api_set_session_context($user, (int) $mobile_session['id']);

    $session = mobile_api_session_payload($tokens, $access_seconds, $refresh_seconds);
    api_success(mobile_api_auth_response($user, $session));
}

function api_mobile_me(): void
{
    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    api_success([
        'user' => mobile_api_user_payload($user),
        'app_shell' => function_exists('app_shell_payload') ? app_shell_payload($user) : null,
        'home' => function_exists('app_feed_payload') ? app_feed_payload($user, 5) : null,
    ]);
}

function api_mobile_logout(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    mobile_api_ensure_tables();

    $input = get_json_input();
    $session_id = (int) ($_SESSION['mobile_session_id'] ?? 0);
    if ($session_id > 0) {
        db_update('mobile_sessions', ['revoked_at' => date('Y-m-d H:i:s')], 'id = ?', [$session_id]);
    }

    $refresh_token = trim((string) ($input['refresh_token'] ?? ''));
    if ($refresh_token !== '') {
        db_update(
            'mobile_sessions',
            ['revoked_at' => date('Y-m-d H:i:s')],
            'refresh_token_hash = ?',
            [hash('sha256', $refresh_token)]
        );
    }

    api_success(['logged_out' => true]);
}

function api_mobile_register_device(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    mobile_api_ensure_tables();

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $input = get_json_input();
    $apns_token = trim((string) ($input['apns_device_token'] ?? ''));
    if ($apns_token === '' || strlen($apns_token) < 20 || strlen($apns_token) > 512) {
        api_error('Valid APNs device token is required.', 422);
    }

    $environment = strtolower(trim((string) ($input['apns_environment'] ?? 'sandbox')));
    if (!in_array($environment, ['sandbox', 'production'], true)) {
        $environment = 'sandbox';
    }

    if (function_exists('set_current_tenant_from_user')) {
        set_current_tenant_from_user($user);
    }

    $token_hash = hash('sha256', $apns_token);
    $session_id = (int) ($_SESSION['mobile_session_id'] ?? 0);
    $data = [
        'user_id' => (int) $user['id'],
        'mobile_session_id' => $session_id > 0 ? $session_id : null,
        'platform' => 'ios',
        'device_id' => mobile_api_short_string($input['device_id'] ?? '', 191) ?: null,
        'device_name' => mobile_api_short_string($input['device_name'] ?? '', 255) ?: null,
        'app_version' => mobile_api_short_string($input['app_version'] ?? '', 50) ?: null,
        'apns_environment' => $environment,
        'apns_token' => $apns_token,
        'apns_token_hash' => $token_hash,
        'is_active' => 1,
        'last_registered_at' => date('Y-m-d H:i:s'),
    ];

    $existing = db_fetch_one("SELECT id FROM mobile_devices WHERE apns_token_hash = ? LIMIT 1", [$token_hash]);
    if ($existing) {
        db_update('mobile_devices', $data, 'id = ?', [(int) $existing['id']]);
        $device_id = (int) $existing['id'];
    } else {
        $device_id = (int) db_insert('mobile_devices', $data);
    }

    api_success(['device_id' => $device_id, 'registered' => true]);
}

function api_mobile_unregister_device(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    mobile_api_ensure_tables();

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $input = get_json_input();
    $apns_token = trim((string) ($input['apns_device_token'] ?? ''));
    $device_id = mobile_api_short_string($input['device_id'] ?? '', 191);

    if ($apns_token !== '') {
        db_update(
            'mobile_devices',
            ['is_active' => 0],
            'user_id = ? AND apns_token_hash = ?',
            [(int) $user['id'], hash('sha256', $apns_token)]
        );
    } elseif ($device_id !== '') {
        db_update(
            'mobile_devices',
            ['is_active' => 0],
            'user_id = ? AND device_id = ?',
            [(int) $user['id'], $device_id]
        );
    } else {
        api_error('Device token or device id is required.', 422);
    }

    api_success(['unregistered' => true]);
}
