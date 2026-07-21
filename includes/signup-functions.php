<?php
/**
 * Email-only SaaS signup helpers.
 */

require_once __DIR__ . '/mailer.php';

function signup_magic_link_ttl_seconds(): int
{
    return 1800;
}

function signup_magic_links_ensure_schema(): void
{
    schema_require('email-only signup', ['signup_magic_links'], [
        'signup_magic_links' => [
            'email', 'token_hash', 'expires_at', 'consumed_at', 'ip', 'user_agent', 'created_at',
        ],
    ]);
}

function signup_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function signup_magic_link_url(string $token): string
{
    $base = rtrim(function_exists('get_app_url') ? get_app_url() : (defined('APP_URL') ? APP_URL : ''), '/');
    if ($base === '') {
        $base = 'https://app.foxdesk.net';
    }

    return $base . '/index.php?page=signup&token=' . urlencode($token);
}

function signup_reset_link_url(string $token): string
{
    $base = rtrim(function_exists('get_app_url') ? get_app_url() : (defined('APP_URL') ? APP_URL : ''), '/');
    if ($base === '') {
        $base = 'https://app.foxdesk.net';
    }

    return $base . '/index.php?page=reset-password&token=' . urlencode($token);
}

function signup_find_user_by_email(string $email): ?array
{
    $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    if (function_exists('users_deleted_at_column_exists') && users_deleted_at_column_exists()) {
        $sql .= " AND deleted_at IS NULL";
    }
    $sql .= " ORDER BY id ASC LIMIT 1";
    $user = db_fetch_one($sql, [$email]);

    return $user ?: null;
}

function signup_send_existing_user_access_email(array $user): bool
{
    $token = generate_reset_token();
    $token_hash = hash_reset_token($token);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    password_reset_store_user_token($user, $token_hash, $expires);

    $name = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    return send_password_reset_email((string) $user['email'], $name !== '' ? $name : 'there', signup_reset_link_url($token));
}

function signup_request_magic_link(string $email): array
{
    signup_magic_links_ensure_schema();

    $email = signup_normalize_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }

    $existing_user = signup_find_user_by_email($email);
    if ($existing_user) {
        $sent = signup_send_existing_user_access_email($existing_user);
        if (function_exists('log_security_event')) {
            log_security_event('signup_existing_user_requested', (int) $existing_user['id'], 'email=' . $email);
            if (!$sent) {
                log_security_event('signup_existing_user_email_failed', (int) $existing_user['id'], 'email=' . $email);
            }
        }

        return ['status' => 'existing_user', 'sent' => true];
    }

    db_query(
        "UPDATE signup_magic_links SET consumed_at = NOW() WHERE email = ? AND consumed_at IS NULL",
        [$email]
    );

    $token = generate_reset_token();
    $token_hash = hash_reset_token($token);
    $expires = date('Y-m-d H:i:s', time() + signup_magic_link_ttl_seconds());
    $user_agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ip = function_exists('get_client_ip') ? get_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $link_id = db_insert('signup_magic_links', [
        'email' => $email,
        'token_hash' => $token_hash,
        'expires_at' => $expires,
        'ip' => $ip !== '' ? $ip : null,
        'user_agent' => $user_agent !== '' ? $user_agent : null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $sent = send_signup_magic_link_email($email, signup_magic_link_url($token));
    if (!$sent) {
        db_update('signup_magic_links', ['consumed_at' => date('Y-m-d H:i:s')], 'id = ?', [$link_id]);
        if (function_exists('log_security_event')) {
            log_security_event('signup_magic_email_failed', null, 'email=' . $email);
        }

        return ['status' => 'new_user', 'sent' => false];
    }

    if (function_exists('log_security_event')) {
        log_security_event('signup_magic_link_requested', null, 'email=' . $email);
    }

    return ['status' => 'new_user', 'sent' => true];
}

function signup_complete_magic_link(string $token): array
{
    signup_magic_links_ensure_schema();

    $token = trim($token);
    if ($token === '') {
        return ['status' => 'invalid'];
    }

    $token_hash = hash_reset_token($token);
    $row = db_fetch_one(
        "SELECT * FROM signup_magic_links WHERE token_hash = ? LIMIT 1",
        [$token_hash]
    );

    if (!$row) {
        return ['status' => 'invalid'];
    }
    if (!empty($row['consumed_at'])) {
        return ['status' => 'used'];
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        db_update('signup_magic_links', ['consumed_at' => date('Y-m-d H:i:s')], 'id = ?', [$row['id']]);
        return ['status' => 'expired', 'email' => $row['email']];
    }

    $email = signup_normalize_email((string) $row['email']);
    $existing_user = signup_find_user_by_email($email);
    if ($existing_user) {
        db_update('signup_magic_links', ['consumed_at' => date('Y-m-d H:i:s')], 'id = ?', [$row['id']]);
        return ['status' => 'existing_user', 'email' => $email];
    }

    $claimed = db_update(
        'signup_magic_links',
        ['consumed_at' => date('Y-m-d H:i:s')],
        'id = ? AND consumed_at IS NULL',
        [$row['id']]
    );
    if ($claimed !== 1) {
        return ['status' => 'used'];
    }

    try {
        $workspace = create_tenant_workspace([
            'admin_email' => $email,
            'status' => 'trialing',
            'subscription_status' => 'trialing',
            'plan' => function_exists('billing_plan_code') ? billing_plan_code() : 'cloud',
        ]);

        if (function_exists('billing_send_trial_email_for_tenant')) {
            billing_send_trial_email_for_tenant((int) $workspace['tenant_id'], 'trial_started');
        }

        $user = db_fetch_one("SELECT * FROM users WHERE id = ? LIMIT 1", [$workspace['user_id']]);
        if (!$user || !function_exists('login_user_session') || !login_user_session($user)) {
            return ['status' => 'created_not_signed_in', 'workspace' => $workspace];
        }

        if (function_exists('log_security_event')) {
            log_security_event('signup_magic_link_consumed', (int) $workspace['user_id'], 'email=' . $email);
        }

        return ['status' => 'created', 'workspace' => $workspace];
    } catch (Throwable $e) {
        if (function_exists('log_security_event')) {
            log_security_event('signup_magic_link_failed', null, 'email=' . $email . '; error=' . $e->getMessage());
        }

        throw $e;
    }
}
