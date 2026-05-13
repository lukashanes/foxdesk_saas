<?php
/**
 * CLI entrypoint: test Cloudflare Email Service configuration.
 *
 * Usage:
 *   php bin/test-cloudflare-email.php --to=user@example.com
 *   php bin/test-cloudflare-email.php --to=user@example.com --dry-run --json
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must run in CLI mode.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));

if (!file_exists(BASE_PATH . '/config.php')) {
    fwrite(STDERR, "Missing config.php. Install/configure the app first.\n");
    exit(1);
}

require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/mailer.php';

$opts = getopt('', ['to:', 'dry-run', 'json']);
$to = trim((string) ($opts['to'] ?? ''));
$dry_run = array_key_exists('dry-run', $opts);
$json = array_key_exists('json', $opts);

$config = [
    'provider' => mailer_provider(),
    'account_id_configured' => trim((string) mailer_env_or_constant('CLOUDFLARE_ACCOUNT_ID', '')) !== '',
    'api_token_configured' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_API_TOKEN', '')) !== '',
    'from' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_FROM', '')),
    'reply_to' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_REPLY_TO', '')),
];

if ($dry_run) {
    $result = [
        'ok' => true,
        'status' => 'dry_run',
        'config' => $config,
    ];
} elseif ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $result = [
        'ok' => false,
        'status' => 'invalid_recipient',
        'message' => 'Pass a valid recipient with --to=email@example.com.',
        'config' => $config,
    ];
} else {
    $subject = 'FoxDesk Cloudflare Email Service test';
    $body = "Hello,\n\nThis is a FoxDesk Cloudflare Email Service test message.\n\nIf you received it, outbound email is working.\n";
    $sent = send_email($to, $subject, $body, false, true);
    $result = [
        'ok' => (bool) $sent,
        'status' => $sent ? 'sent' : 'failed',
        'to' => $to,
        'config' => $config,
    ];
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 2);
}

echo '[cloudflare-email] status=' . ($result['status'] ?? 'unknown')
    . ' provider=' . $config['provider']
    . ' from=' . ($config['from'] ?: '-')
    . ' account_id=' . ($config['account_id_configured'] ? 'configured' : 'missing')
    . ' api_token=' . ($config['api_token_configured'] ? 'configured' : 'missing') . PHP_EOL;

if (empty($result['ok'])) {
    fwrite(STDERR, '[cloudflare-email] ERROR: ' . ($result['message'] ?? 'Send failed.') . PHP_EOL);
    exit(2);
}

exit(0);
