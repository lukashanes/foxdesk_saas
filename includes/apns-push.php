<?php
/**
 * FoxDesk - APNs push notifications for native iOS clients.
 *
 * Uses Apple token-based authentication. Missing APNs configuration is treated
 * as disabled so local/self-hosted environments keep working.
 */

function apns_push_config(): array
{
    $team_id = trim((string) (getenv('APNS_TEAM_ID') ?: ''));
    $key_id = trim((string) (getenv('APNS_KEY_ID') ?: ''));
    $bundle_id = trim((string) (getenv('APNS_BUNDLE_ID') ?: 'net.foxdesk.ios'));
    $auth_key = (string) (getenv('APNS_AUTH_KEY') ?: '');
    $auth_key_path = trim((string) (getenv('APNS_AUTH_KEY_PATH') ?: ''));

    if ($auth_key === '' && $auth_key_path !== '' && is_readable($auth_key_path)) {
        $auth_key = (string) file_get_contents($auth_key_path);
    }

    $auth_key = str_replace('\n', "\n", trim($auth_key));

    return [
        'enabled' => $team_id !== '' && $key_id !== '' && $bundle_id !== '' && $auth_key !== '',
        'team_id' => $team_id,
        'key_id' => $key_id,
        'bundle_id' => $bundle_id,
        'auth_key' => $auth_key,
    ];
}

function apns_push_available(): bool
{
    return extension_loaded('curl') && extension_loaded('openssl') && apns_push_config()['enabled'];
}

function apns_create_jwt(array $config): ?string
{
    static $cached = null;

    $cache_key = sha1(($config['team_id'] ?? '') . '|' . ($config['key_id'] ?? '') . '|' . ($config['auth_key'] ?? ''));
    if (is_array($cached)
        && ($cached['key'] ?? '') === $cache_key
        && (int) ($cached['expires_at'] ?? 0) > time() + 60) {
        return $cached['jwt'];
    }

    $header = apns_base64url_encode(json_encode([
        'alg' => 'ES256',
        'kid' => $config['key_id'],
    ]));
    $payload = apns_base64url_encode(json_encode([
        'iss' => $config['team_id'],
        'iat' => time(),
    ]));
    $signing_input = $header . '.' . $payload;

    $key = openssl_pkey_get_private((string) $config['auth_key']);
    if (!$key) {
        error_log('[apns] Invalid APNs auth key.');
        return null;
    }

    $success = openssl_sign($signing_input, $der_signature, $key, OPENSSL_ALGO_SHA256);
    if (!$success || empty($der_signature)) {
        error_log('[apns] Failed to sign APNs JWT.');
        return null;
    }

    $raw_signature = apns_der_signature_to_raw($der_signature);
    if ($raw_signature === null) {
        error_log('[apns] Failed to convert APNs JWT signature.');
        return null;
    }

    $jwt = $signing_input . '.' . apns_base64url_encode($raw_signature);
    $cached = [
        'key' => $cache_key,
        'jwt' => $jwt,
        'expires_at' => time() + 3000,
    ];

    return $jwt;
}

function apns_notification_payload(array $notification): array
{
    $type = (string) ($notification['type'] ?? '');
    $data = is_array($notification['data'] ?? null) ? $notification['data'] : [];
    $ticket_id = (int) ($notification['ticket_id'] ?? 0);
    $ticket_hash = trim((string) ($notification['ticket_hash'] ?? ''));

    $title = match ($type) {
        'new_ticket' => 'New ticket',
        'new_comment' => 'New reply',
        'assigned_to_you' => 'Assigned to you',
        'mentioned' => 'Mentioned in a ticket',
        'ticket_updated' => 'Ticket updated',
        'status_changed' => 'Ticket updated',
        'priority_changed' => 'Priority changed',
        'due_date_reminder' => 'Due date reminder',
        default => 'FoxDesk',
    };

    $body = function_exists('format_notification_text')
        ? format_notification_text($notification)
        : ((string) ($data['ticket_subject'] ?? 'New FoxDesk notification'));
    $body = trim(strip_tags($body));
    if ($body === '' && function_exists('get_notification_snippet')) {
        $body = trim(strip_tags(get_notification_snippet($notification)));
    }
    if ($body === '') {
        $body = 'Open FoxDesk to review the update.';
    }

    if (mb_strlen($body) > 180) {
        $body = mb_substr($body, 0, 177) . '...';
    }

    $payload = [
        'aps' => [
            'alert' => [
                'title' => $title,
                'body' => $body,
            ],
            'sound' => 'default',
        ],
        'notification_id' => (int) ($notification['id'] ?? 0),
        'type' => $type,
    ];

    if ($ticket_id > 0) {
        $payload['ticket_id'] = $ticket_id;
    }
    if ($ticket_hash !== '') {
        $payload['ticket_hash'] = $ticket_hash;
    }

    return $payload;
}

function apns_send_notification(array $device, array $notification): bool
{
    $config = apns_push_config();
    if (!$config['enabled'] || !extension_loaded('curl')) {
        return false;
    }

    $jwt = apns_create_jwt($config);
    if ($jwt === null) {
        return false;
    }

    $token = trim((string) ($device['apns_token'] ?? ''));
    if ($token === '') {
        return false;
    }

    $environment = (string) ($device['apns_environment'] ?? 'sandbox');
    $host = $environment === 'production'
        ? 'https://api.push.apple.com'
        : 'https://api.sandbox.push.apple.com';
    $url = $host . '/3/device/' . rawurlencode($token);
    $payload = json_encode(apns_notification_payload($notification), JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: bearer ' . $jwt,
            'apns-topic: ' . $config['bundle_id'],
            'apns-push-type: alert',
            'apns-priority: 10',
            'apns-expiration: ' . (time() + 86400),
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response_body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        $reason = '';
        if (is_string($response_body) && $response_body !== '') {
            $decoded = json_decode($response_body, true);
            $reason = is_array($decoded) ? (string) ($decoded['reason'] ?? '') : '';
        }
        if ($status === 410 || in_array($reason, ['BadDeviceToken', 'DeviceTokenNotForTopic', 'Unregistered'], true)) {
            apns_deactivate_device((int) ($device['id'] ?? 0));
        }
        error_log('[apns] Send failed with HTTP ' . $status . ($reason !== '' ? ' (' . $reason . ')' : ''));
        return false;
    }

    return true;
}

function dispatch_apns_notifications(array $user_ids, array $notification_ids_by_user = []): void
{
    if (empty($user_ids) || !apns_push_available() || !apns_mobile_devices_table_exists()) {
        return;
    }

    $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), static fn($id) => $id > 0)));
    if (empty($user_ids)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $params = $user_ids;
    $tenant_filter = '';
    if (function_exists('current_tenant_id') && function_exists('column_exists') && column_exists('mobile_devices', 'tenant_id')) {
        $tenant_id = (int) current_tenant_id();
        if ($tenant_id > 0) {
            $tenant_filter = ' AND tenant_id = ?';
            $params[] = $tenant_id;
        }
    }

    $devices = db_fetch_all(
        "SELECT id, user_id, apns_environment, apns_token
         FROM mobile_devices
         WHERE is_active = 1 AND platform = 'ios' AND user_id IN ({$placeholders}){$tenant_filter}",
        $params
    );
    if (empty($devices)) {
        return;
    }

    $notifications_by_user = apns_notifications_for_users($user_ids, $notification_ids_by_user);

    foreach ($devices as $device) {
        $user_id = (int) ($device['user_id'] ?? 0);
        foreach ($notifications_by_user[$user_id] ?? [] as $notification) {
            try {
                apns_send_notification($device, $notification);
            } catch (Throwable $e) {
                error_log('[apns] Send exception: ' . $e->getMessage());
            }
        }
    }
}

function apns_notifications_for_users(array $user_ids, array $notification_ids_by_user): array
{
    $result = [];
    foreach ($user_ids as $user_id) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $notification_ids_by_user[$user_id] ?? []))));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = [$user_id];
            $tenant_filter = function_exists('tenant_sql_filter')
                ? tenant_sql_filter('notifications', 'n', $params)
                : '';
            $params = array_merge($params, $ids);
            $rows = db_fetch_all(
                "SELECT n.*, t.hash AS ticket_hash,
                        u.first_name AS actor_first_name, u.last_name AS actor_last_name,
                        u.avatar AS actor_avatar, u.email AS actor_email
                 FROM notifications n
                 LEFT JOIN tickets t ON t.id = n.ticket_id
                 LEFT JOIN users u ON u.id = n.actor_id
                 WHERE n.user_id = ?{$tenant_filter} AND n.id IN ({$placeholders})
                 ORDER BY n.created_at DESC",
                $params
            );
        } else {
            $params = [$user_id];
            $tenant_filter = function_exists('tenant_sql_filter')
                ? tenant_sql_filter('notifications', 'n', $params)
                : '';
            $rows = db_fetch_all(
                "SELECT n.*, t.hash AS ticket_hash,
                        u.first_name AS actor_first_name, u.last_name AS actor_last_name,
                        u.avatar AS actor_avatar, u.email AS actor_email
                 FROM notifications n
                 LEFT JOIN tickets t ON t.id = n.ticket_id
                 LEFT JOIN users u ON u.id = n.actor_id
                 WHERE n.user_id = ?{$tenant_filter} AND n.is_read = 0
                 ORDER BY n.created_at DESC
                 LIMIT 1",
                $params
            );
        }

        foreach ($rows as &$row) {
            $row['data'] = is_array($row['data'] ?? null)
                ? $row['data']
                : (json_decode((string) ($row['data'] ?? '{}'), true) ?: []);
        }
        unset($row);

        $result[$user_id] = function_exists('filter_notifications_for_user')
            ? filter_notifications_for_user($rows, $user_id)
            : $rows;
    }

    return $result;
}

function apns_mobile_devices_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $exists = (bool) db_fetch_one("SHOW TABLES LIKE 'mobile_devices'");
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

function apns_deactivate_device(int $device_id): void
{
    if ($device_id <= 0 || !apns_mobile_devices_table_exists()) {
        return;
    }

    try {
        db_update('mobile_devices', ['is_active' => 0], 'id = ?', [$device_id]);
    } catch (Throwable $e) {
        // Push cleanup must not break notification dispatch.
    }
}

function apns_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function apns_der_signature_to_raw(string $der): ?string
{
    $offset = 0;
    if (!isset($der[$offset]) || ord($der[$offset++]) !== 0x30) {
        return null;
    }

    $total_len = ord($der[$offset++] ?? "\0");
    if ($total_len > 127) {
        $offset += ($total_len & 0x7f);
    }

    if (!isset($der[$offset]) || ord($der[$offset++]) !== 0x02) {
        return null;
    }
    $r_len = ord($der[$offset++] ?? "\0");
    $r = substr($der, $offset, $r_len);
    $offset += $r_len;

    if (!isset($der[$offset]) || ord($der[$offset++]) !== 0x02) {
        return null;
    }
    $s_len = ord($der[$offset++] ?? "\0");
    $s = substr($der, $offset, $s_len);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return substr($r, -32) . substr($s, -32);
}
