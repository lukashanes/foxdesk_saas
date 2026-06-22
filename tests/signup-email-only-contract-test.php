<?php

$root = dirname(__DIR__);

$signup_page = file_get_contents($root . '/pages/signup.php');
$signup_functions = file_get_contents($root . '/includes/signup-functions.php');
$tenant_functions = file_get_contents($root . '/includes/tenant-functions.php');
$auth = file_get_contents($root . '/includes/auth.php');
$mailer = file_get_contents($root . '/includes/mailer.php');
$work_page = file_get_contents($root . '/pages/work.php');
$schema = file_get_contents($root . '/includes/schema.sql');
$upgrade = file_get_contents($root . '/upgrade.php');
$local_config = file_get_contents($root . '/config.local.example.php');
$local_compose = file_get_contents($root . '/docker-compose.local.yml');

if (
    $signup_page === false
    || $signup_functions === false
    || $tenant_functions === false
    || $auth === false
    || $mailer === false
    || $work_page === false
    || $schema === false
    || $upgrade === false
    || $local_config === false
    || $local_compose === false
) {
    fwrite(STDERR, "Unable to read signup email-only files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS signup_magic_links'), 'Schema must create signup_magic_links.');
$assert(str_contains($schema, 'token_hash CHAR(64) NOT NULL UNIQUE'), 'Signup token must be stored as a SHA-256 hash.');
$assert(!preg_match('/\btoken\s+VARCHAR/i', $schema), 'Signup schema must not store plaintext tokens.');
$assert(str_contains($upgrade, "SHOW TABLES LIKE 'signup_magic_links'"), 'Upgrade must create signup_magic_links for existing installs.');

$assert(str_contains($signup_functions, 'function signup_request_magic_link'), 'Signup request helper is missing.');
$assert(str_contains($signup_functions, 'function signup_complete_magic_link'), 'Signup completion helper is missing.');
$assert(str_contains($signup_functions, 'hash_reset_token($token)'), 'Signup helper must hash the plaintext token before storage.');
$assert(str_contains($signup_functions, 'signup_magic_link_ttl_seconds'), 'Signup helper must define a token lifetime.');
$assert(str_contains($signup_functions, '1800'), 'Signup links must expire after 30 minutes.');
$assert(str_contains($signup_functions, 'consumed_at = NOW() WHERE email = ? AND consumed_at IS NULL'), 'New signup must invalidate old unused links for the same email.');
$assert(str_contains($signup_functions, 'signup_send_existing_user_access_email'), 'Existing users must receive access/reset email instead of a duplicate workspace.');
$assert(str_contains($signup_functions, 'signup_existing_user_email_failed'), 'Existing-user signup email failures must be logged.');
$assert(str_contains($signup_functions, "return ['status' => 'existing_user', 'sent' => true]"), 'Existing-user signup requests must keep a neutral success response.');
$assert(str_contains($signup_functions, 'create_tenant_workspace'), 'Magic-link completion must provision the workspace after verification.');
$assert(str_contains($signup_functions, 'login_user_session'), 'Magic-link completion must sign in the verified user.');
$assert(str_contains($signup_functions, "return ['status' => 'used']"), 'Used signup links must not be accepted twice.');
$assert(str_contains($signup_functions, "return ['status' => 'expired'"), 'Expired signup links must return an explicit expired state.');
$assert(str_contains($signup_functions, 'strtotime((string) $row[\'expires_at\']) < time()'), 'Signup completion must enforce link expiry.');
$assert(str_contains($signup_functions, "'id = ? AND consumed_at IS NULL'"), 'Signup completion must atomically claim an unused magic link before provisioning.');
$assert(strpos($signup_functions, "'id = ? AND consumed_at IS NULL'") < strpos($signup_functions, 'create_tenant_workspace'), 'Signup link must be claimed before workspace provisioning starts.');

$assert(str_contains($signup_page, "require_turnstile_for_public_form('signup')"), 'Signup POST must verify Turnstile.');
$assert(str_contains($signup_page, "turnstile_widget('signup')"), 'Signup page must render Turnstile.');
$assert(str_contains($signup_page, 'catch (InvalidArgumentException $e)'), 'Signup page may show validation errors for invalid email input.');
$assert(str_contains($signup_page, 'signup_magic_request_failed'), 'Signup page must log unexpected magic-link request failures.');
$assert(str_contains($signup_page, "We could not send the signup link. Please try again."), 'Signup page must use a safe generic send failure message.');
$assert(str_contains($signup_page, "url('legal', ['type' => 'terms'])"), 'Signup must keep Terms link.');
$assert(str_contains($signup_page, "url('legal', ['type' => 'privacy'])"), 'Signup must keep Privacy link.');
$assert(str_contains($signup_page, 'name="email"'), 'Signup form must contain one email input.');
foreach ([
    'name="workspace_name"',
    'name="admin_first_name"',
    'name="admin_last_name"',
    'name="admin_email"',
    'name="password"',
    'name="password_confirm"',
] as $forbidden) {
    $assert(!str_contains($signup_page, $forbidden), "Signup page must not contain {$forbidden}.");
}
$assert(str_contains($signup_page, 'Start free trial'), 'Signup submit copy must be clear and short.');
$assert(str_contains($signup_page, 'Check your email'), 'Signup success state must be neutral.');
$assert(str_contains($signup_page, 'That link expired'), 'Signup page must explain expired links.');
$assert(str_contains($signup_page, 'already been used'), 'Signup page must explain used links.');
$assert(str_contains($signup_page, '&signup=trial'), 'Verified signup must redirect into the app with signup trial context.');

$assert(str_contains($work_page, '($_GET[\'signup\'] ?? \'\') === \'trial\''), 'Work page must show first-run onboarding after verified signup.');
$assert(str_contains($work_page, 'data-signup-onboarding'), 'First-run onboarding must be easy to target in visual smoke tests.');
$assert(str_contains($work_page, "url('admin', ['section' => 'settings'])"), 'First-run onboarding must link workspace settings.');
$assert(str_contains($work_page, "url('admin', ['section' => 'users'])"), 'First-run onboarding must link team setup.');
$assert(str_contains($work_page, "url('billing')"), 'First-run onboarding must link billing setup.');

$assert(str_contains($tenant_functions, 'function tenant_workspace_name_from_email'), 'Tenant provisioning must derive workspace name from email.');
$assert(str_contains($tenant_functions, 'function tenant_admin_first_name_from_email'), 'Tenant provisioning must derive admin first name from email.');
$assert(str_contains($tenant_functions, 'bin2hex(random_bytes(24))'), 'Email-only provisioning must generate a safe hidden password.');
$assert(str_contains($auth, 'function login_user_session'), 'Auth must expose a verified-session login helper.');
$assert(str_contains($mailer, 'function send_signup_magic_link_email'), 'Mailer must send signup magic links.');
$assert(str_contains($mailer, "mailer_env_or_constant('SMTP_HOST'"), 'Mailer must support SMTP configuration from environment.');
$assert(str_contains($mailer, '$user !== \'\''), 'SMTP must allow local capture servers without AUTH credentials.');
$assert(str_contains($local_config, "define('MAIL_PROVIDER', 'smtp')"), 'Local config example must use SMTP capture, not PHP mail.');
$assert(str_contains($local_config, "define('SMTP_HOST', 'mailpit')"), 'Local config example must point SMTP to Mailpit.');
$assert(str_contains($local_compose, 'SMTP_HOST: mailpit'), 'Local compose app service must expose Mailpit SMTP host.');

echo "Signup email-only contract OK\n";
