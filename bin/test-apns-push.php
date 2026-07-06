#!/usr/bin/env php
<?php

$root = dirname(__DIR__);
require_once $root . '/includes/apns-push.php';

$evidence_dir = $root . '/tmp/ios-apns-smoke';

$args = array_slice($argv, 1);
$send = in_array('--send', $args, true);
$json = in_array('--json', $args, true);
$help = in_array('--help', $args, true) || in_array('-h', $args, true);

$option = static function (string $name, ?string $default = null) use ($args): ?string {
    $prefix = $name . '=';
    foreach ($args as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
};

if ($help) {
    echo <<<TXT
FoxDesk APNs smoke test

Dry-run payload/config check:
  ./bin/run-php.sh bin/test-apns-push.php

Live send to a physical iPhone:
  APNS_TEST_DEVICE_TOKEN=<hex-token> ./bin/run-php.sh bin/test-apns-push.php --send --environment=sandbox

Options:
  --send                     Actually send to Apple APNs.
  --json                     Print JSON output.
  --token=<token>            Override APNS_TEST_DEVICE_TOKEN.
  --environment=sandbox      sandbox or production. Defaults to sandbox.
  --ticket-id=123            Ticket id included in payload.
  --type=new_comment         Notification type.

Required environment for live send:
  APNS_TEAM_ID, APNS_KEY_ID, APNS_AUTH_KEY or APNS_AUTH_KEY_PATH, APNS_BUNDLE_ID.

TXT;
    exit(0);
}

$config = apns_push_config();
$token = trim((string) ($option('--token') ?? getenv('APNS_TEST_DEVICE_TOKEN') ?: ''));
$environment = strtolower(trim((string) ($option('--environment', getenv('APNS_TEST_ENV') ?: 'sandbox'))));
if (!in_array($environment, ['sandbox', 'production'], true)) {
    fwrite(STDERR, "Invalid APNs environment. Use sandbox or production.\n");
    exit(1);
}

$ticket_id = (int) ($option('--ticket-id', getenv('APNS_TEST_TICKET_ID') ?: '123'));
if ($ticket_id <= 0) {
    $ticket_id = 123;
}

$notification = [
    'id' => (int) ($option('--notification-id', getenv('APNS_TEST_NOTIFICATION_ID') ?: '1')),
    'type' => (string) ($option('--type', getenv('APNS_TEST_TYPE') ?: 'new_comment')),
    'ticket_id' => $ticket_id,
    'data' => [
        'ticket_subject' => 'FoxDesk iOS APNs smoke test',
        'actor_name' => 'FoxDesk',
        'snippet' => 'Tap to open the matching ticket in the native iOS app.',
    ],
];

$payload = apns_notification_payload($notification);
$first_release_types = [
    'new_ticket',
    'new_comment',
    'assigned_to_you',
    'mentioned',
    'ticket_updated',
    'status_changed',
    'priority_changed',
    'due_date_reminder',
];
$validated_payloads = [];
foreach ($first_release_types as $type) {
    $type_notification = $notification;
    $type_notification['type'] = $type;
    $validated_payloads[$type] = apns_notification_payload($type_notification);
}
$jwt = $config['enabled'] ? apns_create_jwt($config) : null;

$result = [
    'ok' => true,
    'mode' => $send ? 'send' : 'dry-run',
    'generated_at' => gmdate('c'),
    'config' => [
        'enabled' => (bool) ($config['enabled'] ?? false),
        'curl' => extension_loaded('curl'),
        'openssl' => extension_loaded('openssl'),
        'team_id' => ($config['team_id'] ?? '') !== '',
        'key_id' => ($config['key_id'] ?? '') !== '',
        'bundle_id' => (string) ($config['bundle_id'] ?? ''),
        'auth_key' => ($config['auth_key'] ?? '') !== '',
        'jwt' => $jwt !== null,
    ],
    'target' => [
        'environment' => $environment,
        'has_token' => $token !== '',
    ],
    'validated_types' => array_keys($validated_payloads),
    'validated_payloads' => $validated_payloads,
    'payload' => $payload,
];

function write_apns_evidence(string $evidence_dir, array $result): void
{
    if (!is_dir($evidence_dir)) {
        mkdir($evidence_dir, 0775, true);
    }
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    file_put_contents($evidence_dir . '/latest.json', $json);
    file_put_contents($evidence_dir . '/latest-' . $result['mode'] . '.json', $json);
}

if ($send) {
    if (empty($config['enabled']) || $jwt === null) {
        $result['ok'] = false;
        $result['error'] = 'APNs config is incomplete or invalid. Check APNS_TEAM_ID, APNS_KEY_ID, APNS_AUTH_KEY/APNS_AUTH_KEY_PATH, and APNS_BUNDLE_ID.';
    } elseif ($token === '') {
        $result['ok'] = false;
        $result['error'] = 'Missing APNS_TEST_DEVICE_TOKEN or --token for live send.';
    } else {
        $device = [
            'id' => 0,
            'apns_environment' => $environment,
            'apns_token' => $token,
        ];
        $sent = apns_send_notification($device, $notification);
        $result['sent'] = $sent;
        if (!$sent) {
            $result['ok'] = false;
        }
    }
}

write_apns_evidence($evidence_dir, $result);

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo '[ios:apns:smoke] mode=' . $result['mode'] . "\n";
    echo '[ios:apns:smoke] bundle=' . $result['config']['bundle_id'] . "\n";
    echo '[ios:apns:smoke] config=' . ($result['config']['enabled'] ? 'ready' : 'incomplete') . "\n";
    echo '[ios:apns:smoke] jwt=' . ($result['config']['jwt'] ? 'ok' : 'missing') . "\n";
    echo '[ios:apns:smoke] token=' . ($result['target']['has_token'] ? 'provided' : 'missing') . "\n";
    echo '[ios:apns:smoke] ticket_id=' . ($payload['ticket_id'] ?? 'none') . "\n";
    echo '[ios:apns:smoke] validated_types=' . implode(',', $result['validated_types']) . "\n";
    if (array_key_exists('sent', $result)) {
        echo '[ios:apns:smoke] sent=' . ($result['sent'] ? 'yes' : 'no') . "\n";
    }
}

exit($result['ok'] ? 0 : 1);
