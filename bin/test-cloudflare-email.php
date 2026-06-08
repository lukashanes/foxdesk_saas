<?php
/**
 * CLI entrypoint: test Cloudflare Email Service configuration.
 *
 * Usage:
 *   php bin/test-cloudflare-email.php --to=user@example.com
 *   php bin/test-cloudflare-email.php --to=user@example.com --scenario=all
 *   php bin/test-cloudflare-email.php --to=user@example.com --scenario=signup
 *   php bin/test-cloudflare-email.php --to=user@example.com --scenario=all --direct-cloudflare
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

$opts = getopt('', ['to:', 'scenario:', 'dry-run', 'json', 'direct-cloudflare']);
$direct_cloudflare = array_key_exists('direct-cloudflare', $opts);

require_once BASE_PATH . '/config.php';
if (!$direct_cloudflare) {
    require_once BASE_PATH . '/includes/database.php';
    require_once BASE_PATH . '/includes/functions.php';
}
require_once BASE_PATH . '/includes/mailer.php';

$to = trim((string) ($opts['to'] ?? ''));
$scenario = strtolower(trim((string) ($opts['scenario'] ?? 'outbound')));
$dry_run = array_key_exists('dry-run', $opts);
$json = array_key_exists('json', $opts);

$config = [
    'provider' => mailer_provider(),
    'account_id_configured' => trim((string) mailer_env_or_constant('CLOUDFLARE_ACCOUNT_ID', '')) !== '',
    'api_token_configured' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_API_TOKEN', '')) !== '',
    'from' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_FROM', '')),
    'reply_to' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_REPLY_TO', '')),
];

function cloudflare_email_demo_html(string $eyebrow, string $title, string $body, string $cta_label = '', string $cta_url = ''): string
{
    $paragraphs = array_filter(array_map('trim', preg_split("/\n{2,}/", trim($body)) ?: []));
    $body_html = '';
    foreach ($paragraphs as $paragraph) {
        $body_html .= '<p style="margin:0 0 14px;color:#334155;font-size:15px;line-height:23px">'
            . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            . '</p>';
    }

    $cta = $cta_label !== ''
        ? '<p style="margin:22px 0 0"><a href="' . htmlspecialchars($cta_url !== '' ? $cta_url : 'https://app.foxdesk.net/', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;border-radius:10px;padding:11px 16px;font-weight:700">' . htmlspecialchars($cta_label, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</a></p>'
        : '';

    return '<!doctype html><html><body style="margin:0;background:#f8fafc;font-family:Inter,Arial,sans-serif;color:#0f172a">'
        . '<div style="max-width:620px;margin:0 auto;padding:32px 18px">'
        . '<div style="font-weight:800;font-size:18px;margin-bottom:18px">FoxDesk</div>'
        . '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(15,23,42,.06)">'
        . '<div style="color:#64748b;font-size:13px;font-weight:700;margin-bottom:8px">' . htmlspecialchars($eyebrow, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</div>'
        . '<h1 style="margin:0 0 14px;font-size:24px;line-height:30px">' . htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</h1>'
        . $body_html
        . $cta
        . '<p style="margin:24px 0 0;color:#64748b;font-size:12px;line-height:18px">You received this operational email from FoxDesk Cloud.</p>'
        . '</div></div></body></html>';
}

$scenarios = [
    'outbound' => [
        'subject' => 'FoxDesk Cloudflare Email Service test',
        'body' => "Hello,\n\nThis is a FoxDesk Cloudflare Email Service test message.\n\nIf you received it, outbound email is working.\n",
        'html' => false,
        'ticket' => false,
    ],
    'signup' => [
        'subject' => 'Welcome to FoxDesk Cloud',
        'body' => cloudflare_email_demo_html(
            'Workspace created',
            'Your FoxDesk workspace is ready',
            "Your 14-day trial has started and your team can begin handling tickets, clients, time tracking, and attachments in one workspace.\n\nNo payment is required during the trial. Billing can be added from the workspace billing page when you are ready.",
            'Open FoxDesk',
            'https://app.foxdesk.net/'
        ),
        'html' => true,
        'ticket' => false,
    ],
    'reset' => [
        'subject' => 'Reset your FoxDesk password',
        'body' => cloudflare_email_demo_html(
            'Password reset',
            'Create a new password',
            "We received a request to reset the password for your FoxDesk account.\n\nIf this was you, open the reset link and choose a new password. If you did not request this, you can ignore this email.",
            'Reset password',
            'https://app.foxdesk.net/index.php?page=forgot-password'
        ),
        'html' => true,
        'ticket' => false,
    ],
    'new-ticket' => [
        'subject' => 'New ticket: VPN access stopped working',
        'body' => "A new ticket was created for Northline Support.\n\nClient: Northline Support\nPriority: High\nRequester: Eva Novak\n\nVPN access stopped working after the laptop update. The requester needs access before today's sales meeting.",
        'html' => false,
        'ticket' => true,
        'payload' => [
            'eyebrow' => 'New ticket',
            'title' => 'VPN access stopped working',
            'cta_label' => 'Open ticket',
            'reason' => 'You are receiving this because this ticket needs team attention.',
        ],
    ],
    'ticket-reply' => [
        'subject' => 'Reply added: VPN access stopped working',
        'body' => "Eva replied to the ticket.\n\nThe VPN client now asks for MFA on every connection and rejects the code after the first attempt.\n\nAttachments: screenshot-vpn-error.png",
        'html' => false,
        'ticket' => true,
        'payload' => [
            'eyebrow' => 'Ticket reply',
            'title' => 'New reply from Eva Novak',
            'cta_label' => 'View reply',
            'reason' => 'Reply to this email to add a public comment to the ticket.',
        ],
    ],
    'billing' => [
        'subject' => 'Your FoxDesk trial ends soon',
        'body' => cloudflare_email_demo_html(
            'Billing',
            'Your trial ends in 3 days',
            "Your FoxDesk Cloud trial is almost over.\n\nTo keep the workspace active, add billing details in the customer portal. You can update company details, VAT ID, payment method, and invoice history there.",
            'Manage billing',
            'https://app.foxdesk.net/index.php?page=billing'
        ),
        'html' => true,
        'ticket' => false,
    ],
];

$selected_scenarios = [];
if ($scenario === 'all') {
    $selected_scenarios = ['signup', 'reset', 'new-ticket', 'ticket-reply', 'billing'];
} elseif (isset($scenarios[$scenario])) {
    $selected_scenarios = [$scenario];
} else {
    $selected_scenarios = [];
}

if ($dry_run) {
    $result = [
        'ok' => true,
        'status' => 'dry_run',
        'scenario' => $scenario,
        'scenarios' => $selected_scenarios,
        'direct_cloudflare' => $direct_cloudflare,
        'config' => $config,
    ];
} elseif (empty($selected_scenarios)) {
    $result = [
        'ok' => false,
        'status' => 'invalid_scenario',
        'message' => 'Use --scenario=outbound, signup, reset, new-ticket, ticket-reply, billing, or all.',
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
    $sent_results = [];
    foreach ($selected_scenarios as $scenario_key) {
        $item = $scenarios[$scenario_key];
        if ($direct_cloudflare) {
            $sent = send_cloudflare_email($to, $item['subject'], $item['body'], !empty($item['html']), [
                'account_id' => trim((string) mailer_env_or_constant('CLOUDFLARE_ACCOUNT_ID', '')),
                'api_token' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_API_TOKEN', '')),
                'from_email' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_FROM', '')),
                'from_name' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'FoxDesk')),
                'reply_to' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_REPLY_TO', '')),
            ]);
        } elseif (!empty($item['ticket'])) {
            $sent = send_ticket_notification_email($to, $item['subject'], $item['body'], $item['payload'] ?? [], true);
        } else {
            $sent = send_email($to, $item['subject'], $item['body'], !empty($item['html']), true);
        }

        $sent_results[$scenario_key] = (bool) $sent;
    }

    $sent = !in_array(false, $sent_results, true);
    $result = [
        'ok' => (bool) $sent,
        'status' => $sent ? 'sent' : 'failed',
        'to' => $to,
        'scenario' => $scenario,
        'scenarios' => $sent_results,
        'direct_cloudflare' => $direct_cloudflare,
        'config' => $config,
    ];
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 2);
}

echo '[cloudflare-email] status=' . ($result['status'] ?? 'unknown')
    . ' provider=' . $config['provider']
    . ' scenario=' . $scenario
    . ' direct_cloudflare=' . ($direct_cloudflare ? 'yes' : 'no')
    . ' from=' . ($config['from'] ?: '-')
    . ' account_id=' . ($config['account_id_configured'] ? 'configured' : 'missing')
    . ' api_token=' . ($config['api_token_configured'] ? 'configured' : 'missing') . PHP_EOL;

if (empty($result['ok'])) {
    fwrite(STDERR, '[cloudflare-email] ERROR: ' . ($result['message'] ?? 'Send failed.') . PHP_EOL);
    exit(2);
}

exit(0);
