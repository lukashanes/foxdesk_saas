<?php

$root = dirname(__DIR__);

$security = file_get_contents($root . '/includes/security-helpers.php');
$forgot = file_get_contents($root . '/pages/forgot-password.php');
$reset = file_get_contents($root . '/pages/reset-password.php');
$signup = file_get_contents($root . '/includes/signup-functions.php');
$profile = file_get_contents($root . '/pages/profile.php');
$users = file_get_contents($root . '/pages/admin/users.php');
$platform = file_get_contents($root . '/includes/modules/platform/operator-console.php');
$ingest = file_get_contents($root . '/includes/email-ingest-functions.php');

foreach ([
    'security helpers' => $security,
    'forgot password' => $forgot,
    'reset password' => $reset,
    'signup helpers' => $signup,
    'profile' => $profile,
    'admin users' => $users,
    'platform console' => $platform,
    'email ingest' => $ingest,
] as $label => $source) {
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$label} source.\n");
        exit(1);
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($security, 'function password_reset_user_tenant_id'), 'Password reset must resolve the user tenant before mutations.');
$assert(str_contains($security, 'function password_reset_store_user_token'), 'Password reset token storage helper is missing.');
$assert(str_contains($security, 'function password_reset_clear_user_token'), 'Password reset token clear helper is missing.');
$assert(str_contains($security, 'function password_reset_find_user_by_token'), 'Password reset token lookup helper is missing.');
$assert(str_contains($security, 'SELECT tenant_id FROM users WHERE id = ? LIMIT 1'), 'Password reset helper must recover tenant_id when callers only have user id.');
$assert(str_contains($security, "return 'id = ? AND tenant_id = ?'"), 'Password reset mutations must explicitly scope user updates by tenant_id.');
$assert(str_contains($security, 'reset_token IN (?, ?)'), 'Password reset lookup must support hashed tokens and legacy plaintext links.');

$assert(str_contains($forgot, 'SELECT id, tenant_id, first_name, email FROM users'), 'Forgot password must fetch tenant_id with the user.');
$assert(str_contains($forgot, 'password_reset_store_user_token($user, $token_hash, $expires)'), 'Forgot password must store reset tokens through the tenant-aware helper.');
$assert(str_contains($forgot, 'urlencode($token)'), 'Forgot password reset links must URL-encode the token.');

$assert(str_contains($reset, 'password_reset_find_user_by_token($token)'), 'Reset page must verify tokens through the shared lookup helper.');
$assert(str_contains($reset, 'password_reset_user_where($user, $params)'), 'Reset page must update the password through a tenant-aware where clause.');
$assert(str_contains($reset, 'password_reset_clear_user_token($user)'), 'Reset page must clear reset tokens through the tenant-aware helper.');

foreach ([
    'signup existing user access' => $signup,
    'profile password setup' => $profile,
    'admin user reset' => $users,
    'platform owner reset' => $platform,
    'email ingest setup' => $ingest,
] as $label => $source) {
    $assert(str_contains($source, 'password_reset_store_user_token'), "{$label} must use the tenant-aware reset token storage helper.");
}

echo "Password reset tenant contract OK\n";
