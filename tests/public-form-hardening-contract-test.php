<?php

$root = dirname(__DIR__);

$security = file_get_contents($root . '/includes/security-helpers.php');
$login = file_get_contents($root . '/pages/login.php');
$signup = file_get_contents($root . '/pages/signup.php');
$forgot = file_get_contents($root . '/pages/forgot-password.php');
$reset = file_get_contents($root . '/pages/reset-password.php');
$docs = file_get_contents($root . '/docs/NEXT_STEPS.md');

if ($security === false || $login === false || $signup === false || $forgot === false || $reset === false || $docs === false) {
    fwrite(STDERR, "Unable to read public form hardening files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($security, 'function turnstile_enabled'), 'Turnstile enablement helper is missing.');
$assert(str_contains($security, 'function turnstile_script_tag'), 'Turnstile script helper is missing.');
$assert(str_contains($security, 'function turnstile_widget'), 'Turnstile widget helper is missing.');
$assert(str_contains($security, 'function require_turnstile_for_public_form'), 'Turnstile public form guard is missing.');
$assert(str_contains($security, 'https://challenges.cloudflare.com/turnstile/v0/siteverify'), 'Turnstile server verification endpoint is missing.');
$assert(str_contains($security, 'https://challenges.cloudflare.com'), 'CSP must allow Cloudflare Turnstile.');
$assert(str_contains($security, 'turnstile_failed'), 'Turnstile failures should be security logged.');

foreach ([
    'login' => [$login, 'login'],
    'signup' => [$signup, 'signup'],
    'forgot password' => [$forgot, 'password_reset_request'],
    'reset password' => [$reset, 'password_reset_complete'],
] as $label => [$source, $action]) {
    $assert(str_contains($source, 'turnstile_script_tag()'), "{$label} page must include the Turnstile script helper.");
    $assert(str_contains($source, "turnstile_widget('{$action}')"), "{$label} page must render a scoped Turnstile widget.");
    $assert(str_contains($source, "require_turnstile_for_public_form('{$action}')"), "{$label} POST handler must verify Turnstile.");
}

$assert(str_contains($docs, 'Cloudflare Turnstile'), 'Next steps docs should mention public form bot protection.');

echo "Public form hardening contract OK\n";
