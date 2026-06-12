<?php
/**
 * Cloudflare Email Routing -> FoxDesk ingest endpoint.
 *
 * Worker posts are authenticated with an HMAC signature over timestamp.body.
 */

require_once BASE_PATH . '/includes/email-routing-functions.php';
require_once BASE_PATH . '/includes/email-ingest-functions.php';

function api_cloudflare_email_header(string $name): string
{
    $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$server_key] ?? ''));
}

function api_cloudflare_email_verify_signature(string $body): void
{
    $rate_key = function_exists('rate_limit_key') ? rate_limit_key('cf_email_ingest') : 'cf_email_ingest';
    if (function_exists('rate_limit_is_blocked') && rate_limit_is_blocked($rate_key, 30, 300)) {
        api_error('Too many email ingest authentication attempts', 429);
    }

    $secret = foxdesk_email_route_secret();
    if ($secret === '') {
        api_error('Cloudflare email route secret is not configured', 500);
    }

    $timestamp = api_cloudflare_email_header('X-FoxDesk-Email-Timestamp');
    $signature = api_cloudflare_email_header('X-FoxDesk-Email-Signature');
    if ($timestamp === '' || $signature === '') {
        if (function_exists('rate_limit_record')) {
            rate_limit_record($rate_key, 300);
        }
        api_error('Missing email ingest signature', 401);
    }

    $ts = ctype_digit($timestamp) ? (int) $timestamp : 0;
    if ($ts <= 0 || abs(time() - $ts) > 600) {
        if (function_exists('rate_limit_record')) {
            rate_limit_record($rate_key, 300);
        }
        api_error('Expired email ingest signature', 401);
    }

    $expected_hex = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $expected = 'sha256=' . $expected_hex;
    if (!hash_equals($expected, $signature) && !hash_equals($expected_hex, $signature)) {
        if (function_exists('rate_limit_record')) {
            rate_limit_record($rate_key, 300);
        }
        api_error('Invalid email ingest signature', 401);
    }

    if (function_exists('rate_limit_clear')) {
        rate_limit_clear($rate_key);
    }
}

function api_cloudflare_email_verify_archive_payload(array $payload): void
{
    $raw_key = trim((string) ($payload['raw_r2_key'] ?? ''));
    if ($raw_key !== '' && empty($payload['raw_archive_verified'])) {
        api_error('Email archive was not verified by Cloudflare Worker', 422);
    }

    $attachments = is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [];
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $r2_key = trim((string) ($attachment['r2_key'] ?? $attachment['storage_key'] ?? ''));
        if ($r2_key !== '' && empty($attachment['archive_verified'])) {
            api_error('Email attachment archive was not verified by Cloudflare Worker', 422);
        }
    }
}

function api_cloudflare_email_ingest(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_error('Method not allowed', 405);
    }

    if (function_exists('foxdesk_cloudflare_email_ingest_enabled') && !foxdesk_cloudflare_email_ingest_enabled()) {
        api_error('Cloudflare email ingest is disabled for this FoxDesk edition', 404);
    }

    $body = file_get_contents('php://input');
    if (!is_string($body) || trim($body) === '') {
        api_error('Missing request body', 400);
    }

    api_cloudflare_email_verify_signature($body);

    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        api_error('Invalid JSON payload', 400);
    }
    api_cloudflare_email_verify_archive_payload($payload);

    try {
        $result = email_ingest_process_cloudflare_payload($payload);
    } catch (Throwable $e) {
        error_log('Cloudflare email ingest failed: ' . $e->getMessage());
        api_error('Email ingest failed', 500);
    }

    api_success([
        'ingest' => $result,
    ]);
}
