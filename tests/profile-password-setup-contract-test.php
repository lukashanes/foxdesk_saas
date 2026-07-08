<?php

$root = dirname(__DIR__);

$profile = file_get_contents($root . '/pages/profile.php');
$auth = file_get_contents($root . '/includes/auth.php');
$totp = file_get_contents($root . '/includes/totp.php');
$mailer = file_get_contents($root . '/includes/mailer.php');
$lang_en = file_get_contents($root . '/includes/lang/en.php');
$lang_cs = file_get_contents($root . '/includes/lang/cs.php');

if ($profile === false || $auth === false || $totp === false || $mailer === false || $lang_en === false || $lang_cs === false) {
    fwrite(STDERR, "Unable to read profile contract files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($profile, "isset(\$_POST['update_profile'])"), 'Profile page must let users update profile details.');
$assert(str_contains($profile, "name=\"first_name\""), 'Profile page must expose first name input.');
$assert(str_contains($profile, "name=\"last_name\""), 'Profile page must expose last name input.');
$assert(str_contains($profile, "name=\"language\""), 'Profile page must expose language preferences.');

$assert(str_contains($profile, "isset(\$_POST['update_notifications'])"), 'Profile page must let users save notification preferences.');
$assert(str_contains($profile, "in_app_notifications_enabled"), 'Profile page must expose in-app notification preference.');
$assert(str_contains($profile, "in_app_sound_enabled"), 'Profile page must expose notification sound preference.');
$assert(str_contains($profile, "isset(\$_POST['update_notification_types'])"), 'Profile page must let users save per-type notification preferences.');

$assert(str_contains($profile, "isset(\$_POST['change_password'])"), 'Profile page must let users change password when they know the current password.');
$assert(str_contains($auth, 'function update_password'), 'Auth layer must expose update_password.');
$assert(str_contains($profile, "isset(\$_POST['send_password_setup_link'])"), 'Profile page must expose a password setup link action for magic-link signup users.');
$assert(str_contains($profile, 'generate_reset_token()'), 'Password setup action must generate a reset token.');
$assert(str_contains($profile, 'hash_reset_token($token)'), 'Password setup action must store only a hashed reset token.');
$assert(str_contains($profile, 'password_reset_store_user_token($user, $token_hash, $expires)'), 'Password setup action must persist reset_token through the tenant-aware helper.');
$assert(str_contains($profile, 'send_password_reset_email'), 'Password setup action must send the standard reset email.');
$assert(str_contains($profile, 'profile_password_setup_requested'), 'Password setup success must be security logged.');
$assert(str_contains($profile, 'profile_password_setup_failed'), 'Password setup failure must be security logged.');
$assert(str_contains($profile, 'name="send_password_setup_link"'), 'Profile page must render the setup-link submit button.');
$assert(str_contains($profile, 'Do not know your current password?'), 'Profile page must explain the setup-link path.');

$assert(str_contains($profile, "isset(\$_POST['start_2fa_setup'])"), 'Profile page must support starting 2FA setup.');
$assert(str_contains($profile, "isset(\$_POST['verify_2fa_setup'])"), 'Profile page must support verifying 2FA setup.');
$assert(str_contains($profile, "isset(\$_POST['disable_2fa'])"), 'Profile page must support disabling 2FA.');
$assert(str_contains($profile, "isset(\$_POST['regenerate_backup_codes'])"), 'Profile page must support regenerating backup codes.');
$assert(str_contains($profile, 'id="two-factor-section"'), 'Profile page must expose a stable 2FA section hook.');
$assert(str_contains($totp, 'function totp_generate_secret'), 'TOTP helper must generate secrets.');
$assert(str_contains($totp, 'function totp_verify'), 'TOTP helper must verify codes.');
$assert(str_contains($totp, 'function generate_backup_codes'), 'TOTP helper must generate backup codes.');

$assert(str_contains($mailer, 'function send_password_reset_email'), 'Mailer must provide reset email function used by profile setup links.');

foreach ([
    'Do not know your current password?',
    'Password setup link sent. Check your email.',
    'Send setup link',
    'Send yourself a secure link to set a new password.',
    'We could not send the password setup link. Try again later.',
] as $key) {
    $assert(str_contains($lang_en, "'" . $key . "'"), "English translation missing for {$key}.");
    $assert(str_contains($lang_cs, "'" . $key . "'"), "Czech translation missing for {$key}.");
}

echo "Profile password setup contract OK\n";
