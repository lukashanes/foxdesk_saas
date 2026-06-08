<?php
/**
 * Production smoke: send a real Cloudflare Email reply with an attachment,
 * wait for FoxDesk ingest, and print the R2 archive keys to verify with Wrangler.
 *
 * Usage:
 *   php bin/test-cloudflare-inbound-archive.php --tenant-id=3 --json
 *   php bin/test-cloudflare-inbound-archive.php --tenant-id=3 --keep-db
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must run in CLI mode.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/mailer.php';
require_once BASE_PATH . '/includes/email-routing-functions.php';

$opts = getopt('', ['tenant-id:', 'timeout:', 'json', 'keep-db']);
$json = array_key_exists('json', $opts);
$keep_db = array_key_exists('keep-db', $opts);
$timeout = max(10, (int) ($opts['timeout'] ?? 90));
$tenant_id = (int) ($opts['tenant-id'] ?? 0);

function inbound_archive_out(array $result, bool $json): void
{
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(!empty($result['ok']) ? 0 : 2);
    }

    echo '[cloudflare-inbound-archive] status=' . ($result['status'] ?? 'unknown') . PHP_EOL;
    foreach (['ticket_id', 'reply_to', 'raw_r2_key', 'attachment_r2_key'] as $key) {
        if (!empty($result[$key])) {
            echo $key . '=' . $result[$key] . PHP_EOL;
        }
    }
    if (empty($result['ok'])) {
        fwrite(STDERR, '[cloudflare-inbound-archive] ERROR: ' . ($result['message'] ?? 'Smoke failed.') . PHP_EOL);
        exit(2);
    }
    exit(0);
}

function inbound_archive_pick_tenant(int $tenant_id): array
{
    if ($tenant_id > 0) {
        $tenant = db_fetch_one("SELECT * FROM tenants WHERE id = ? LIMIT 1", [$tenant_id]);
    } else {
        $tenant = db_fetch_one("SELECT * FROM tenants WHERE status IN ('active', 'trialing') ORDER BY id LIMIT 1");
    }

    if (!$tenant) {
        throw new RuntimeException('No active tenant found for inbound archive smoke.');
    }

    return $tenant;
}

function inbound_archive_pick_user(int $tenant_id): array
{
    $user = db_fetch_one(
        "SELECT * FROM users
         WHERE tenant_id = ? AND is_active = 1 AND deleted_at IS NULL
         ORDER BY FIELD(role, 'admin', 'agent', 'user'), id
         LIMIT 1",
        [$tenant_id]
    );

    if (!$user) {
        throw new RuntimeException('No active user found for inbound archive smoke.');
    }

    return $user;
}

function inbound_archive_pick_status(): int
{
    $status = db_fetch_one("SELECT id FROM statuses WHERE is_default = 1 ORDER BY sort_order, id LIMIT 1")
        ?: db_fetch_one("SELECT id FROM statuses ORDER BY sort_order, id LIMIT 1");
    $status_id = (int) ($status['id'] ?? 0);
    if ($status_id <= 0) {
        throw new RuntimeException('No ticket status found for inbound archive smoke.');
    }
    return $status_id;
}

function inbound_archive_allowed_sender_snapshot(string $email): ?array
{
    $row = db_fetch_one("SELECT * FROM allowed_senders WHERE type = 'email' AND value = ? LIMIT 1", [$email]);
    return $row ?: null;
}

function inbound_archive_upsert_allowed_sender(int $tenant_id, string $email, int $user_id): void
{
    db_query(
        "INSERT INTO allowed_senders (tenant_id, type, value, user_id, active, created_at, updated_at)
         VALUES (?, 'email', ?, ?, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), user_id = VALUES(user_id), active = 1, updated_at = NOW()",
        [$tenant_id, $email, $user_id]
    );
}

function inbound_archive_restore_allowed_sender(string $email, ?array $snapshot): void
{
    if ($snapshot === null) {
        db_query("DELETE FROM allowed_senders WHERE type = 'email' AND value = ?", [$email]);
        return;
    }

    db_query(
        "UPDATE allowed_senders SET tenant_id = ?, user_id = ?, active = ?, updated_at = ? WHERE id = ?",
        [
            $snapshot['tenant_id'] ?? null,
            $snapshot['user_id'] ?? null,
            (int) ($snapshot['active'] ?? 1),
            $snapshot['updated_at'] ?? date('Y-m-d H:i:s'),
            (int) $snapshot['id'],
        ]
    );
}

function inbound_archive_find_attachment(int $ticket_id, string $subject): ?array
{
    return db_fetch_one(
        "SELECT tm.id AS message_id, tm.subject, tma.storage_path, tma.filename, tma.size
         FROM ticket_messages tm
         JOIN ticket_message_attachments tma ON tma.ticket_message_id = tm.id
         WHERE tm.ticket_id = ? AND tm.subject = ?
         ORDER BY tm.id DESC, tma.id DESC
         LIMIT 1",
        [$ticket_id, $subject]
    ) ?: null;
}

$ticket_id = 0;
$allowed_sender_snapshot = null;
$from_email = '';

try {
    $tenant = inbound_archive_pick_tenant($tenant_id);
    $tenant_id = (int) $tenant['id'];
    $user = inbound_archive_pick_user($tenant_id);
    $user_id = (int) $user['id'];
    $status_id = inbound_archive_pick_status();

    $from_email = strtolower(trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_FROM', '')));
    if ($from_email === '' || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('CLOUDFLARE_EMAIL_FROM is not configured.');
    }

    $allowed_sender_snapshot = inbound_archive_allowed_sender_snapshot($from_email);
    inbound_archive_upsert_allowed_sender($tenant_id, $from_email, $user_id);

    $suffix = strtolower(bin2hex(random_bytes(4)));
    $subject = 'FoxDesk inbound archive smoke ' . $suffix;
    $hash = substr('cf' . bin2hex(random_bytes(8)), 0, 16);
    $ticket_id = (int) db_insert('tickets', [
        'tenant_id' => $tenant_id,
        'hash' => $hash,
        'title' => $subject,
        'description' => 'Temporary Cloudflare inbound archive smoke ticket.',
        'type' => 'smoke',
        'priority_id' => null,
        'user_id' => $user_id,
        'organization_id' => $user['organization_id'] ?? null,
        'status_id' => $status_id,
        'ticket_type_id' => null,
        'source' => 'email',
        'is_archived' => 0,
        'assignee_id' => $user_id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $reply_to = foxdesk_ticket_reply_address([
        'id' => $ticket_id,
        'tenant_id' => $tenant_id,
    ]);
    if ($reply_to === '') {
        throw new RuntimeException('Unable to generate ticket reply address.');
    }

    $attachment_body = "FoxDesk inbound archive smoke\nTicket: {$ticket_id}\nSubject: {$subject}\n";
    $sent = send_cloudflare_email($reply_to, $subject, nl2br(htmlspecialchars($attachment_body, ENT_QUOTES | ENT_HTML5, 'UTF-8')), true, [
        'account_id' => trim((string) mailer_env_or_constant('CLOUDFLARE_ACCOUNT_ID', '')),
        'api_token' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_API_TOKEN', '')),
        'from_email' => $from_email,
        'from_name' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'FoxDesk')),
        'reply_to' => trim((string) mailer_env_or_constant('CLOUDFLARE_EMAIL_REPLY_TO', $from_email)),
        'attachments' => [[
            'filename' => 'foxdesk-inbound-archive-smoke.txt',
            'type' => 'text/plain',
            'content' => $attachment_body,
        ]],
    ]);
    if (!$sent) {
        throw new RuntimeException('Cloudflare Email Sending did not accept the smoke message.');
    }

    $deadline = time() + $timeout;
    $attachment = null;
    while (time() <= $deadline) {
        $attachment = inbound_archive_find_attachment($ticket_id, $subject);
        if ($attachment) {
            break;
        }
        sleep(3);
    }

    if (!$attachment) {
        throw new RuntimeException('Timed out waiting for inbound email attachment ingest.');
    }

    $attachment_key = preg_replace('#^r2://#', '', (string) $attachment['storage_path']);
    $raw_key = preg_replace('#/attachments/.*$#', '/raw.eml', $attachment_key);

    $result = [
        'ok' => true,
        'status' => 'ingested',
        'tenant_id' => $tenant_id,
        'ticket_id' => $ticket_id,
        'reply_to' => $reply_to,
        'subject' => $subject,
        'raw_r2_key' => $raw_key,
        'attachment_r2_key' => $attachment_key,
        'wrangler_get_raw' => 'npx wrangler r2 object get foxdesk-email-archive/' . $raw_key . ' --remote --file /tmp/foxdesk-raw.eml',
        'wrangler_get_attachment' => 'npx wrangler r2 object get foxdesk-email-archive/' . $attachment_key . ' --remote --file /tmp/foxdesk-attachment.txt',
        'db_cleaned' => !$keep_db,
    ];
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'status' => 'failed',
        'tenant_id' => $tenant_id,
        'ticket_id' => $ticket_id,
        'message' => $e->getMessage(),
    ];
} finally {
    if (!$keep_db) {
        if ($ticket_id > 0) {
            try {
                db_query("DELETE FROM tickets WHERE id = ?", [$ticket_id]);
            } catch (Throwable $e) {
                // Keep the original smoke result.
            }
        }
        if ($from_email !== '') {
            try {
                inbound_archive_restore_allowed_sender($from_email, $allowed_sender_snapshot);
            } catch (Throwable $e) {
                // Keep the original smoke result.
            }
        }
    }
}

inbound_archive_out($result, $json);
