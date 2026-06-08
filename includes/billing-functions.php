<?php
/**
 * SaaS billing helpers for Stripe Billing + Checkout.
 */

function billing_env_or_constant(string $name, $default = '')
{
    if (defined($name)) {
        return constant($name);
    }

    $value = getenv($name);
    return $value !== false ? $value : $default;
}

function billing_enabled(): bool
{
    $enabled = billing_env_or_constant('BILLING_ENABLED', false);
    return $enabled === true || $enabled === '1' || $enabled === 1 || $enabled === 'true';
}

function billing_env_bool(string $name, bool $default = false): bool
{
    $value = billing_env_or_constant($name, $default);
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function billing_webhook_secret(): string
{
    return trim((string) billing_env_or_constant('STRIPE_WEBHOOK_SECRET', ''));
}

function billing_currency(): string
{
    return strtoupper(trim((string) billing_env_or_constant('BILLING_CURRENCY', 'EUR')));
}

function billing_cloud_base_price_cents(): int
{
    return max(0, (int) billing_env_or_constant('BILLING_CLOUD_BASE_PRICE_CENTS', 1900));
}

function billing_storage_overage_price_cents(): int
{
    return max(0, (int) billing_env_or_constant('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 190));
}

function billing_included_storage_bytes(): int
{
    return max(0, (int) billing_env_or_constant('BILLING_INCLUDED_STORAGE_BYTES', 1073741824));
}

function billing_trial_days(): int
{
    return max(1, (int) billing_env_or_constant('BILLING_TRIAL_DAYS', 14));
}

function billing_trial_grace_days(): int
{
    return max(0, (int) billing_env_or_constant('BILLING_TRIAL_GRACE_DAYS', 3));
}

function billing_past_due_grace_days(): int
{
    return max(0, (int) billing_env_or_constant('BILLING_PAST_DUE_GRACE_DAYS', 7));
}

function billing_plan_code(): string
{
    return 'cloud';
}

function billing_plan_name(): string
{
    return 'FoxDesk Cloud';
}

function billing_format_money(int $cents): string
{
    $amount = number_format($cents / 100, 2);
    return billing_currency() === 'CZK' ? $amount . ' Kc' : 'EUR ' . $amount;
}

function billing_plan_price_id(string $plan = 'cloud'): string
{
    return trim((string) billing_env_or_constant('STRIPE_PRICE_CLOUD_BASE', ''));
}

function billing_storage_overage_price_id(): string
{
    return trim((string) billing_env_or_constant('STRIPE_PRICE_STORAGE_OVERAGE', ''));
}

function billing_storage_meter_event_name(): string
{
    $event_name = trim((string) billing_env_or_constant('STRIPE_STORAGE_METER_EVENT_NAME', 'foxdesk_storage_extra_gb'));
    return $event_name !== '' ? $event_name : 'foxdesk_storage_extra_gb';
}

function billing_stripe_tax_enabled(): bool
{
    return billing_env_bool('STRIPE_TAX_ENABLED', false);
}

function billing_tax_id_collection_enabled(): bool
{
    return billing_env_bool('STRIPE_TAX_ID_COLLECTION_ENABLED', billing_stripe_tax_enabled());
}

function billing_tax_id_collection_required(): string
{
    $required = strtolower(trim((string) billing_env_or_constant('STRIPE_TAX_ID_COLLECTION_REQUIRED', '')));
    return $required === 'if_supported' ? 'if_supported' : '';
}

function billing_stripe_secret_key_mode(): string
{
    $secret = trim((string) billing_env_or_constant('STRIPE_SECRET_KEY', ''));
    if ($secret === '') {
        return 'missing';
    }

    if (str_starts_with($secret, 'sk_test_')) {
        return 'test';
    }

    if (str_starts_with($secret, 'sk_live_')) {
        return 'live';
    }

    return 'unknown';
}

function billing_usage_reporting_config_status(bool $require_stripe_secret = false): array
{
    $key_mode = billing_stripe_secret_key_mode();
    $meter_event = billing_storage_meter_event_name();
    $errors = [];
    $warnings = [];

    if (!extension_loaded('curl')) {
        $errors[] = 'PHP cURL extension is required for Stripe billing requests.';
    }

    if ($meter_event === '') {
        $errors[] = 'STRIPE_STORAGE_METER_EVENT_NAME is required.';
    }

    if ($key_mode === 'missing') {
        $message = 'STRIPE_SECRET_KEY is not configured.';
        if ($require_stripe_secret) {
            $errors[] = $message;
        } else {
            $warnings[] = $message;
        }
    } elseif ($key_mode === 'unknown') {
        $errors[] = 'STRIPE_SECRET_KEY must start with sk_test_ or sk_live_.';
    }

    if (!billing_enabled()) {
        $warnings[] = 'BILLING_ENABLED is false; scheduled maintenance will not send live usage.';
    }

    if (billing_storage_overage_price_id() === '') {
        $warnings[] = 'STRIPE_PRICE_STORAGE_OVERAGE is not configured; Checkout will not include metered storage.';
    }

    if (billing_stripe_tax_enabled() && !billing_tax_id_collection_enabled()) {
        $warnings[] = 'STRIPE_TAX_ENABLED is true but STRIPE_TAX_ID_COLLECTION_ENABLED is false; EU VAT reverse-charge customers might not be able to enter VAT IDs during Checkout.';
    }

    return [
        'ok' => empty($errors),
        'billing_enabled' => billing_enabled(),
        'stripe_tax_enabled' => billing_stripe_tax_enabled(),
        'tax_id_collection_enabled' => billing_tax_id_collection_enabled(),
        'tax_id_collection_required' => billing_tax_id_collection_required(),
        'key_mode' => $key_mode,
        'meter_event' => $meter_event,
        'has_storage_price' => billing_storage_overage_price_id() !== '',
        'curl' => extension_loaded('curl'),
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

function billing_portal_configuration_id(): string
{
    return trim((string) billing_env_or_constant('STRIPE_PORTAL_CONFIGURATION_ID', ''));
}

function billing_append_query(string $url, array $params): string
{
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
    if (!$params) {
        return $url;
    }

    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . http_build_query($params);
}

function billing_is_current_user_platform_admin(): bool
{
    if (!function_exists('current_user') || !function_exists('is_platform_admin')) {
        return false;
    }

    $user = current_user();
    return is_array($user) && is_platform_admin($user);
}

function billing_checkout_return_urls(int $tenant_id): array
{
    $is_platform_admin = billing_is_current_user_platform_admin();
    $default_page = $is_platform_admin ? 'platform' : 'billing';
    $success_url = APP_URL . '/index.php?page=' . $default_page;
    $cancel_url = APP_URL . '/index.php?page=' . $default_page;

    if ($is_platform_admin) {
        $success_url = trim((string) billing_env_or_constant('STRIPE_SUCCESS_URL', $success_url)) ?: $success_url;
        $cancel_url = trim((string) billing_env_or_constant('STRIPE_CANCEL_URL', $cancel_url)) ?: $cancel_url;
    }

    return [
        'success' => billing_append_query($success_url, [
            'tenant_id' => (string) $tenant_id,
            'checkout' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ]),
        'cancel' => billing_append_query($cancel_url, [
            'tenant_id' => (string) $tenant_id,
            'checkout' => 'cancelled',
        ]),
    ];
}

function billing_usage_period_key(?int $timestamp = null): string
{
    return date('Y-m-d', $timestamp ?: time());
}

function billing_ensure_usage_reports_table(): void
{
    if (!function_exists('table_exists') || table_exists('billing_usage_reports')) {
        return;
    }

    db_query("
        CREATE TABLE IF NOT EXISTS billing_usage_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            stripe_customer_id VARCHAR(255) NOT NULL,
            event_name VARCHAR(120) NOT NULL,
            period_key VARCHAR(20) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            idempotency_key VARCHAR(255) NOT NULL,
            status ENUM('pending', 'reported', 'dry_run', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
            error_message TEXT NULL,
            reported_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_billing_usage_idempotency (idempotency_key),
            INDEX idx_billing_usage_tenant_period (tenant_id, period_key),
            INDEX idx_billing_usage_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function billing_ensure_usage_events_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db_query("
        CREATE TABLE IF NOT EXISTS billing_usage_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NULL,
            event_type VARCHAR(80) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            metadata_json TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_billing_usage_events_tenant_created (tenant_id, created_at),
            INDEX idx_billing_usage_events_type_created (event_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function billing_record_usage_event(?int $tenant_id, string $event_type, int $quantity = 1, array $metadata = []): void
{
    try {
        billing_ensure_usage_events_table();

        $event_type = trim($event_type);
        if ($event_type === '') {
            return;
        }
        if (function_exists('mb_substr')) {
            $event_type = mb_substr($event_type, 0, 80);
        } else {
            $event_type = substr($event_type, 0, 80);
        }

        $quantity = max(1, $quantity);
        $tenant_id = $tenant_id && $tenant_id > 0 ? $tenant_id : null;
        $metadata_json = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if (is_string($metadata_json) && strlen($metadata_json) > 4000) {
            $metadata_json = substr($metadata_json, 0, 4000);
        }

        db_insert('billing_usage_events', [
            'tenant_id' => $tenant_id,
            'event_type' => $event_type,
            'quantity' => $quantity,
            'metadata_json' => $metadata_json,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('billing_record_usage_event failed: ' . $e->getMessage());
    }
}

function billing_ensure_stripe_events_table(): void
{
    if (!function_exists('table_exists') || table_exists('billing_stripe_events')) {
        return;
    }

    db_query("
        CREATE TABLE IF NOT EXISTS billing_stripe_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(255) NOT NULL,
            event_type VARCHAR(120) NOT NULL,
            tenant_id INT NULL,
            status ENUM('pending', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'pending',
            error_message TEXT NULL,
            processed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_billing_stripe_event_id (event_id),
            INDEX idx_billing_stripe_events_tenant (tenant_id),
            INDEX idx_billing_stripe_events_status (status),
            INDEX idx_billing_stripe_events_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function billing_usage_period_window(?string $period_key = null): array
{
    $period_key = trim((string) $period_key);
    if ($period_key === '') {
        $timestamp = time();
    } elseif (preg_match('/^\d{4}-\d{2}$/', $period_key)) {
        $timestamp = strtotime($period_key . '-01 00:00:00') ?: time();
    } else {
        $timestamp = strtotime($period_key) ?: time();
    }

    $start = date('Y-m-01 00:00:00', $timestamp);
    $end = date('Y-m-d H:i:s', strtotime('+1 month', strtotime($start)));

    return [
        'start' => $start,
        'end' => $end,
    ];
}

function billing_storage_breakdown(int $tenant_id): array
{
    $empty = [
        'storage_bytes' => 0,
        'storage_local_bytes' => 0,
        'storage_r2_bytes' => 0,
        'storage_unknown_bytes' => 0,
        'storage_objects' => 0,
        'storage_local_objects' => 0,
        'storage_r2_objects' => 0,
        'storage_unknown_objects' => 0,
    ];

    if (!table_exists('attachments')) {
        return $empty;
    }

    if (!column_exists('attachments', 'storage_driver')) {
        $attachments = db_fetch_one("
            SELECT
                COALESCE(SUM(file_size), 0) AS bytes,
                COUNT(*) AS objects
            FROM attachments
            WHERE tenant_id = ?
        ", [$tenant_id]);

        $bytes = (int) ($attachments['bytes'] ?? 0);
        $objects = (int) ($attachments['objects'] ?? 0);
        return array_merge($empty, [
            'storage_bytes' => $bytes,
            'storage_local_bytes' => $bytes,
            'storage_objects' => $objects,
            'storage_local_objects' => $objects,
        ]);
    }

    $attachments = db_fetch_one("
        SELECT
            COALESCE(SUM(file_size), 0) AS total_bytes,
            COALESCE(SUM(CASE WHEN COALESCE(storage_driver, 'local') = 'local' THEN file_size ELSE 0 END), 0) AS local_bytes,
            COALESCE(SUM(CASE WHEN storage_driver = 'r2' THEN file_size ELSE 0 END), 0) AS r2_bytes,
            COALESCE(SUM(CASE WHEN COALESCE(storage_driver, 'local') NOT IN ('local', 'r2') THEN file_size ELSE 0 END), 0) AS unknown_bytes,
            COUNT(*) AS total_objects,
            COALESCE(SUM(CASE WHEN COALESCE(storage_driver, 'local') = 'local' THEN 1 ELSE 0 END), 0) AS local_objects,
            COALESCE(SUM(CASE WHEN storage_driver = 'r2' THEN 1 ELSE 0 END), 0) AS r2_objects,
            COALESCE(SUM(CASE WHEN COALESCE(storage_driver, 'local') NOT IN ('local', 'r2') THEN 1 ELSE 0 END), 0) AS unknown_objects
        FROM attachments
        WHERE tenant_id = ?
    ", [$tenant_id]);

    return [
        'storage_bytes' => (int) ($attachments['total_bytes'] ?? 0),
        'storage_local_bytes' => (int) ($attachments['local_bytes'] ?? 0),
        'storage_r2_bytes' => (int) ($attachments['r2_bytes'] ?? 0),
        'storage_unknown_bytes' => (int) ($attachments['unknown_bytes'] ?? 0),
        'storage_objects' => (int) ($attachments['total_objects'] ?? 0),
        'storage_local_objects' => (int) ($attachments['local_objects'] ?? 0),
        'storage_r2_objects' => (int) ($attachments['r2_objects'] ?? 0),
        'storage_unknown_objects' => (int) ($attachments['unknown_objects'] ?? 0),
    ];
}

function billing_volume_counters(int $tenant_id, ?string $period_key = null): array
{
    $window = billing_usage_period_window($period_key);
    $counters = [
        'usage_period_start' => $window['start'],
        'usage_period_end' => $window['end'],
        'api_requests' => 0,
        'outbound_email_sent' => 0,
        'outbound_email_failed' => 0,
        'outbound_email_skipped' => 0,
        'inbound_email_total' => 0,
        'inbound_email_processed' => 0,
        'inbound_email_skipped' => 0,
        'inbound_email_failed' => 0,
    ];

    try {
        billing_ensure_usage_events_table();
        $events = db_fetch_all("
            SELECT event_type, COALESCE(SUM(quantity), 0) AS quantity
            FROM billing_usage_events
            WHERE tenant_id = ?
              AND created_at >= ?
              AND created_at < ?
            GROUP BY event_type
        ", [$tenant_id, $window['start'], $window['end']]);

        foreach ($events as $event) {
            $quantity = (int) ($event['quantity'] ?? 0);
            switch ((string) ($event['event_type'] ?? '')) {
                case 'api.request':
                    $counters['api_requests'] = $quantity;
                    break;
                case 'email.sent':
                    $counters['outbound_email_sent'] = $quantity;
                    break;
                case 'email.failed':
                    $counters['outbound_email_failed'] = $quantity;
                    break;
                case 'email.skipped':
                    $counters['outbound_email_skipped'] = $quantity;
                    break;
            }
        }
    } catch (Throwable $e) {
        error_log('billing_volume_counters events failed: ' . $e->getMessage());
    }

    try {
        if (table_exists('email_ingest_logs')) {
            $has_log_tenant = column_exists('email_ingest_logs', 'tenant_id');
            $can_join_tickets = table_exists('tickets')
                && column_exists('email_ingest_logs', 'ticket_id')
                && column_exists('tickets', 'tenant_id');

            if ($has_log_tenant || $can_join_tickets) {
                $join = $can_join_tickets ? "LEFT JOIN tickets t ON t.id = l.ticket_id" : "";
                if ($has_log_tenant && $can_join_tickets) {
                    $tenant_expr = "COALESCE(l.tenant_id, t.tenant_id)";
                } elseif ($has_log_tenant) {
                    $tenant_expr = "l.tenant_id";
                } else {
                    $tenant_expr = "t.tenant_id";
                }

                $inbound = db_fetch_one("
                    SELECT
                        COUNT(*) AS total,
                        COALESCE(SUM(l.status = 'processed'), 0) AS processed,
                        COALESCE(SUM(l.status = 'skipped'), 0) AS skipped,
                        COALESCE(SUM(l.status = 'failed'), 0) AS failed
                    FROM email_ingest_logs l
                    {$join}
                    WHERE {$tenant_expr} = ?
                      AND l.created_at >= ?
                      AND l.created_at < ?
                ", [$tenant_id, $window['start'], $window['end']]);

                $counters['inbound_email_total'] = (int) ($inbound['total'] ?? 0);
                $counters['inbound_email_processed'] = (int) ($inbound['processed'] ?? 0);
                $counters['inbound_email_skipped'] = (int) ($inbound['skipped'] ?? 0);
                $counters['inbound_email_failed'] = (int) ($inbound['failed'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('billing_volume_counters inbound failed: ' . $e->getMessage());
    }

    return $counters;
}

function billing_tenant_usage(int $tenant_id): array
{
    ensure_tenant_baseline();

    $user_count = 0;
    $agent_count = 0;
    $client_count = 0;
    $ticket_count = 0;

    if (table_exists('users')) {
        $users = db_fetch_one(
            "SELECT
                COUNT(*) AS users,
                SUM(role IN ('admin', 'agent')) AS agents
             FROM users
             WHERE tenant_id = ? AND deleted_at IS NULL",
            [$tenant_id]
        );
        $user_count = (int) ($users['users'] ?? 0);
        $agent_count = (int) ($users['agents'] ?? 0);
    }

    if (table_exists('organizations')) {
        $clients = db_fetch_one("SELECT COUNT(*) AS c FROM organizations WHERE tenant_id = ? AND is_active = 1", [$tenant_id]);
        $client_count = (int) ($clients['c'] ?? 0);
    }

    if (table_exists('tickets')) {
        $tickets = db_fetch_one("SELECT COUNT(*) AS c FROM tickets WHERE tenant_id = ?", [$tenant_id]);
        $ticket_count = (int) ($tickets['c'] ?? 0);
    }

    $storage = billing_storage_breakdown($tenant_id);
    $volume = billing_volume_counters($tenant_id);
    $storage_bytes = (int) $storage['storage_bytes'];

    $included_bytes = billing_included_storage_bytes();
    $extra_bytes = max(0, $storage_bytes - $included_bytes);
    $extra_gb = $extra_bytes > 0 ? (int) ceil($extra_bytes / 1073741824) : 0;
    $overage_cents = $extra_gb * billing_storage_overage_price_cents();

    return array_merge([
        'users' => $user_count,
        'agents' => $agent_count,
        'clients' => $client_count,
        'tickets' => $ticket_count,
        'included_storage_bytes' => $included_bytes,
        'extra_storage_bytes' => $extra_bytes,
        'extra_storage_gb' => $extra_gb,
        'storage_overage_cents' => $overage_cents,
    ], $storage, $volume);
}

function billing_stripe_request(string $method, string $path, array $params = [], array $extra_headers = []): array
{
    $secret = trim((string) billing_env_or_constant('STRIPE_SECRET_KEY', ''));
    if ($secret === '') {
        throw new RuntimeException('Stripe secret key is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for Stripe billing.');
    }

    $method = strtoupper($method);
    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    if (!empty($params) && in_array($method, ['GET', 'DELETE'], true)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Authorization: Bearer ' . $secret,
            'Stripe-Version: 2026-02-25.clover',
        ], $extra_headers),
        CURLOPT_TIMEOUT => 20,
    ];

    if (!empty($params) && !in_array($method, ['GET', 'DELETE'], true)) {
        $options[CURLOPT_POSTFIELDS] = http_build_query($params);
    }

    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Stripe request failed: ' . $error);
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe returned an invalid response.');
    }

    if ($status < 200 || $status >= 300) {
        $message = $decoded['error']['message'] ?? ('Stripe request failed with HTTP ' . $status);
        throw new RuntimeException($message);
    }

    return $decoded;
}

function billing_record_meter_event(string $event_name, string $stripe_customer_id, int $value, string $identifier, ?int $timestamp = null): array
{
    if ($event_name === '') {
        throw new RuntimeException('Stripe storage meter event name is not configured.');
    }
    if ($stripe_customer_id === '') {
        throw new RuntimeException('Stripe customer id is missing.');
    }

    $params = [
        'event_name' => $event_name,
        'payload[value]' => (string) max(0, $value),
        'payload[stripe_customer_id]' => $stripe_customer_id,
        'identifier' => $identifier,
    ];
    if ($timestamp !== null) {
        $params['timestamp'] = (string) $timestamp;
    }

    return billing_stripe_request('POST', 'billing/meter_events', $params, [
        'Idempotency-Key: ' . $identifier,
    ]);
}

function billing_find_meter_by_event_name(string $event_name): ?array
{
    $event_name = trim($event_name);
    if ($event_name === '') {
        return null;
    }

    $params = [
        'status' => 'active',
        'limit' => 100,
    ];

    for ($page = 0; $page < 20; $page++) {
        $response = billing_stripe_request('GET', 'billing/meters', $params);
        $meters = is_array($response['data'] ?? null) ? $response['data'] : [];
        foreach ($meters as $meter) {
            if ((string) ($meter['event_name'] ?? '') === $event_name) {
                return $meter;
            }
        }

        if (empty($response['has_more']) || empty($meters)) {
            break;
        }

        $last = end($meters);
        if (empty($last['id'])) {
            break;
        }
        $params['starting_after'] = (string) $last['id'];
    }

    return null;
}

function billing_meter_event_summaries(string $meter_id, string $stripe_customer_id, int $start_time, int $end_time): array
{
    $start_time -= $start_time % 60;
    $end_time -= $end_time % 60;
    if ($end_time <= $start_time) {
        $end_time = $start_time + 60;
    }

    return billing_stripe_request('GET', 'billing/meters/' . rawurlencode($meter_id) . '/event_summaries', [
        'customer' => $stripe_customer_id,
        'start_time' => $start_time,
        'end_time' => $end_time,
    ]);
}

function billing_invoice_preview_for_customer(string $stripe_customer_id, ?string $stripe_subscription_id = null): array
{
    $params = ['customer' => $stripe_customer_id];
    if ($stripe_subscription_id !== null && $stripe_subscription_id !== '') {
        $params['subscription'] = $stripe_subscription_id;
    }

    return billing_stripe_request('POST', 'invoices/create_preview', $params);
}

function billing_report_storage_usage_for_tenant(int $tenant_id, ?string $period_key = null, bool $dry_run = false): array
{
    billing_ensure_usage_reports_table();

    $tenant = billing_get_tenant($tenant_id);
    if (!$tenant) {
        return ['tenant_id' => $tenant_id, 'status' => 'skipped', 'reason' => 'tenant_not_found'];
    }

    $stripe_customer_id = trim((string) ($tenant['stripe_customer_id'] ?? ''));
    if ($stripe_customer_id === '') {
        return ['tenant_id' => $tenant_id, 'status' => 'skipped', 'reason' => 'missing_stripe_customer'];
    }

    $event_name = billing_storage_meter_event_name();
    $period_key = $period_key ?: billing_usage_period_key();
    $usage = billing_tenant_usage($tenant_id);
    $quantity = (int) $usage['extra_storage_gb'];
    $identifier = 'foxdesk-storage-' . $tenant_id . '-' . $period_key;

    $existing = db_fetch_one(
        "SELECT * FROM billing_usage_reports WHERE idempotency_key = ? LIMIT 1",
        [$identifier]
    );
    $existing_status = $existing ? (string) $existing['status'] : '';
    if ($existing_status === 'reported') {
        return [
            'tenant_id' => $tenant_id,
            'status' => 'skipped',
            'reason' => 'already_reported',
            'quantity' => (int) $existing['quantity'],
            'period_key' => $period_key,
        ];
    }

    if (!$existing) {
        db_insert('billing_usage_reports', [
            'tenant_id' => $tenant_id,
            'stripe_customer_id' => $stripe_customer_id,
            'event_name' => $event_name,
            'period_key' => $period_key,
            'quantity' => $quantity,
            'idempotency_key' => $identifier,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } else {
        db_update('billing_usage_reports', [
            'stripe_customer_id' => $stripe_customer_id,
            'event_name' => $event_name,
            'quantity' => $quantity,
            'status' => 'pending',
            'error_message' => null,
        ], 'id = ?', [(int) $existing['id']]);
    }

    if ($dry_run) {
        db_update('billing_usage_reports', [
            'status' => 'dry_run',
            'reported_at' => date('Y-m-d H:i:s'),
            'error_message' => null,
        ], 'idempotency_key = ?', [$identifier]);
        return [
            'tenant_id' => $tenant_id,
            'status' => 'dry_run',
            'quantity' => $quantity,
            'period_key' => $period_key,
        ];
    }

    try {
        billing_record_meter_event($event_name, $stripe_customer_id, $quantity, $identifier, time());
        db_update('billing_usage_reports', [
            'status' => 'reported',
            'reported_at' => date('Y-m-d H:i:s'),
            'error_message' => null,
        ], 'idempotency_key = ?', [$identifier]);

        return [
            'tenant_id' => $tenant_id,
            'status' => 'reported',
            'quantity' => $quantity,
            'period_key' => $period_key,
        ];
    } catch (Throwable $e) {
        db_update('billing_usage_reports', [
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ], 'idempotency_key = ?', [$identifier]);

        return [
            'tenant_id' => $tenant_id,
            'status' => 'failed',
            'quantity' => $quantity,
            'period_key' => $period_key,
            'error' => $e->getMessage(),
        ];
    }
}

function billing_report_storage_usage_all(bool $dry_run = false, ?string $period_key = null): array
{
    billing_ensure_usage_reports_table();
    $summary = [
        'ok' => true,
        'reported' => 0,
        'dry_run' => 0,
        'skipped' => 0,
        'failed' => 0,
        'tenants' => [],
    ];

    $tenants = db_fetch_all("
        SELECT id
        FROM tenants
        WHERE status IN ('active', 'trialing', 'past_due')
        ORDER BY id ASC
    ");

    foreach ($tenants as $row) {
        $result = billing_report_storage_usage_for_tenant((int) $row['id'], $period_key, $dry_run);
        $status = (string) ($result['status'] ?? 'skipped');
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }
        if ($status === 'failed') {
            $summary['ok'] = false;
        }
        $summary['tenants'][] = $result;
    }

    return $summary;
}

function billing_get_tenant(int $tenant_id): ?array
{
    ensure_tenant_baseline();
    return db_fetch_one("SELECT * FROM tenants WHERE id = ? LIMIT 1", [$tenant_id]) ?: null;
}

function billing_current_tenant(): ?array
{
    return billing_get_tenant(current_tenant_id());
}

function billing_trial_ends_at_for_new_workspace(): string
{
    return date('Y-m-d H:i:s', strtotime('+' . billing_trial_days() . ' days'));
}

function billing_trial_days_remaining(?array $tenant = null): ?int
{
    $tenant = $tenant ?: billing_current_tenant();
    $trial_ends_at = trim((string) ($tenant['trial_ends_at'] ?? ''));
    if ($trial_ends_at === '') {
        return null;
    }

    $ends = strtotime($trial_ends_at);
    if (!$ends) {
        return null;
    }

    return max(0, (int) ceil(($ends - time()) / 86400));
}

function billing_datetime_add_days(?string $date_time, int $days): ?string
{
    $date_time = trim((string) $date_time);
    if ($date_time === '') {
        return null;
    }

    $timestamp = strtotime($date_time);
    if (!$timestamp) {
        return null;
    }

    return date('Y-m-d H:i:s', strtotime('+' . max(0, $days) . ' days', $timestamp));
}

function billing_trial_grace_ends_at(?array $tenant = null): ?string
{
    $tenant = $tenant ?: billing_current_tenant();
    if (!$tenant) {
        return null;
    }

    return billing_datetime_add_days($tenant['trial_ends_at'] ?? null, billing_trial_grace_days());
}

function billing_past_due_grace_ends_at(?array $tenant = null): ?string
{
    $tenant = $tenant ?: billing_current_tenant();
    if (!$tenant) {
        return null;
    }

    return billing_datetime_add_days($tenant['suspended_at'] ?? null, billing_past_due_grace_days());
}

function billing_expire_trials(?int $tenant_id = null): array
{
    ensure_tenant_baseline();

    $grace_days = billing_trial_grace_days();
    $where = "status = 'trialing' AND subscription_status = 'trialing' AND trial_ends_at IS NOT NULL AND DATE_ADD(trial_ends_at, INTERVAL {$grace_days} DAY) < NOW()";
    $params = [];
    if ($tenant_id !== null) {
        $where .= ' AND id = ?';
        $params[] = $tenant_id;
    }

    $expired = db_fetch_all("SELECT id FROM tenants WHERE {$where}", $params);
    if (!$expired) {
        return ['expired' => 0, 'tenant_ids' => []];
    }

    $ids = array_map(static fn($row) => (int) $row['id'], $expired);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    db_query(
        "UPDATE tenants
         SET status = 'trial_expired',
             subscription_status = 'trial_expired',
             blocked_at = COALESCE(blocked_at, NOW()),
             suspended_at = COALESCE(suspended_at, NOW())
         WHERE id IN ({$placeholders})",
        $ids
    );

    return ['expired' => count($ids), 'tenant_ids' => $ids];
}

function billing_suspend_past_due_tenants(?int $tenant_id = null): array
{
    ensure_tenant_baseline();

    $params = [];
    $scope = "status = 'past_due'";
    if ($tenant_id !== null) {
        $scope .= ' AND id = ?';
        $params[] = $tenant_id;
    }

    db_query(
        "UPDATE tenants
         SET suspended_at = COALESCE(suspended_at, NOW())
         WHERE {$scope}",
        $params
    );

    $grace_days = billing_past_due_grace_days();
    $where = "status = 'past_due' AND suspended_at IS NOT NULL AND DATE_ADD(suspended_at, INTERVAL {$grace_days} DAY) < NOW()";
    $select_params = [];
    if ($tenant_id !== null) {
        $where .= ' AND id = ?';
        $select_params[] = $tenant_id;
    }

    $suspended = db_fetch_all("SELECT id FROM tenants WHERE {$where}", $select_params);
    if (!$suspended) {
        return ['suspended' => 0, 'tenant_ids' => []];
    }

    $ids = array_map(static fn($row) => (int) $row['id'], $suspended);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    db_query(
        "UPDATE tenants
         SET status = 'suspended',
             blocked_at = COALESCE(blocked_at, NOW())
         WHERE id IN ({$placeholders})",
        $ids
    );

    return ['suspended' => count($ids), 'tenant_ids' => $ids];
}

function billing_ensure_trial_email_events_table(): void
{
    if (!function_exists('table_exists') || table_exists('billing_trial_email_events')) {
        return;
    }

    db_query("
        CREATE TABLE IF NOT EXISTS billing_trial_email_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            status ENUM('sent', 'skipped', 'failed') NOT NULL DEFAULT 'sent',
            error_message TEXT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_trial_email_event (tenant_id, event_type),
            INDEX idx_trial_email_tenant (tenant_id),
            INDEX idx_trial_email_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function billing_trial_email_event_exists(int $tenant_id, string $event_type): bool
{
    billing_ensure_trial_email_events_table();
    $row = db_fetch_one(
        "SELECT id FROM billing_trial_email_events WHERE tenant_id = ? AND event_type = ? LIMIT 1",
        [$tenant_id, $event_type]
    );

    return !empty($row);
}

function billing_trial_email_recipient(array $tenant): string
{
    $email = trim((string) ($tenant['billing_email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    $owner_id = (int) ($tenant['owner_user_id'] ?? 0);
    if ($owner_id > 0 && function_exists('db_fetch_one')) {
        $owner = db_fetch_one("SELECT email FROM users WHERE id = ? LIMIT 1", [$owner_id]);
        $email = trim((string) ($owner['email'] ?? ''));
    }

    return $email;
}

function billing_trial_email_copy(array $tenant, string $event_type): array
{
    $workspace = (string) ($tenant['name'] ?? 'your FoxDesk workspace');
    $days = billing_trial_days_remaining($tenant);
    $billing_url = rtrim((string) APP_URL, '/') . '/index.php?page=billing';
    $days_text = $days !== null ? (string) $days : '0';

    return match ($event_type) {
        'trial_started' => [
            'subject' => 'Your FoxDesk workspace is ready',
            'body' => "Hi,\n\n{$workspace} is ready. You have " . billing_trial_days() . " days to try FoxDesk, and no card is needed today.\n\nAdd billing before the trial ends to keep the workspace active:\n{$billing_url}\n\nFoxDesk",
        ],
        'trial_3_days_left' => [
            'subject' => 'FoxDesk trial ends in 3 days',
            'body' => "Hi,\n\nQuick heads up: {$workspace} has {$days_text} days left in the trial.\n\nAdd billing here to keep the workspace active:\n{$billing_url}\n\nFoxDesk",
        ],
        'trial_1_day_left' => [
            'subject' => 'FoxDesk trial ends tomorrow',
            'body' => "Hi,\n\nOne more day: {$workspace} has {$days_text} day left in the trial.\n\nAdd billing here to keep the workspace active:\n{$billing_url}\n\nFoxDesk",
        ],
        'trial_expired' => [
            'subject' => 'FoxDesk trial has ended',
            'body' => "Hi,\n\nThe trial for {$workspace} has ended. Add billing to restore access.\n\nOpen Billing here:\n{$billing_url}\n\nFoxDesk",
        ],
        default => [
            'subject' => 'FoxDesk trial update',
            'body' => "Hi,\n\nThere is an update for {$workspace}.\n\n{$billing_url}\n\nFoxDesk",
        ],
    };
}

function billing_send_trial_email_for_tenant(int $tenant_id, string $event_type): bool
{
    $tenant = billing_get_tenant($tenant_id);
    if (!$tenant || billing_trial_email_event_exists($tenant_id, $event_type)) {
        return false;
    }

    $recipient = billing_trial_email_recipient($tenant);
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        db_insert('billing_trial_email_events', [
            'tenant_id' => $tenant_id,
            'event_type' => $event_type,
            'recipient_email' => $recipient,
            'status' => 'skipped',
            'error_message' => 'Missing valid billing email.',
        ]);
        return false;
    }

    $copy = billing_trial_email_copy($tenant, $event_type);

    try {
        if (!function_exists('send_email')) {
            throw new RuntimeException('Mailer is not loaded.');
        }

        $sent = send_email($recipient, $copy['subject'], $copy['body'], false, true);
        db_insert('billing_trial_email_events', [
            'tenant_id' => $tenant_id,
            'event_type' => $event_type,
            'recipient_email' => $recipient,
            'status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : 'send_email returned false.',
        ]);

        return (bool) $sent;
    } catch (Throwable $e) {
        db_insert('billing_trial_email_events', [
            'tenant_id' => $tenant_id,
            'event_type' => $event_type,
            'recipient_email' => $recipient,
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
        return false;
    }
}

function billing_send_trial_reminders(): array
{
    ensure_tenant_baseline();
    billing_ensure_trial_email_events_table();

    $events = [
        'trial_3_days_left' => "t.status = 'trialing' AND t.subscription_status = 'trialing' AND t.trial_ends_at IS NOT NULL AND t.trial_ends_at > NOW() AND t.trial_ends_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)",
        'trial_1_day_left' => "t.status = 'trialing' AND t.subscription_status = 'trialing' AND t.trial_ends_at IS NOT NULL AND t.trial_ends_at > NOW() AND t.trial_ends_at <= DATE_ADD(NOW(), INTERVAL 1 DAY)",
        'trial_expired' => "t.status = 'trial_expired' AND t.subscription_status = 'trial_expired'",
    ];

    $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'events' => []];
    foreach ($events as $event_type => $where) {
        $tenants = db_fetch_all("
            SELECT t.*
            FROM tenants t
            LEFT JOIN billing_trial_email_events e ON e.tenant_id = t.id AND e.event_type = ?
            WHERE {$where} AND e.id IS NULL
            ORDER BY t.trial_ends_at ASC, t.id ASC
            LIMIT 100
        ", [$event_type]);

        foreach ($tenants as $tenant) {
            $before = db_fetch_one(
                "SELECT COUNT(*) AS c FROM billing_trial_email_events WHERE tenant_id = ? AND event_type = ?",
                [(int) $tenant['id'], $event_type]
            );
            $sent = billing_send_trial_email_for_tenant((int) $tenant['id'], $event_type);
            $after = db_fetch_one(
                "SELECT status FROM billing_trial_email_events WHERE tenant_id = ? AND event_type = ? LIMIT 1",
                [(int) $tenant['id'], $event_type]
            );
            $status = (string) ($after['status'] ?? ($sent ? 'sent' : 'skipped'));
            $result[$status] = (int) ($result[$status] ?? 0) + 1;
            $result['events'][] = [
                'tenant_id' => (int) $tenant['id'],
                'event_type' => $event_type,
                'status' => $status,
                'created' => ((int) ($before['c'] ?? 0)) === 0,
            ];
        }
    }

    return $result;
}

function billing_workspace_access_state(?array $tenant = null): array
{
    $tenant = $tenant ?: billing_current_tenant();
    if (!$tenant) {
        return ['allowed' => true, 'reason' => 'missing_tenant', 'message' => ''];
    }

    if ((string) ($tenant['status'] ?? '') === 'trialing' && (string) ($tenant['subscription_status'] ?? '') === 'trialing') {
        billing_expire_trials((int) $tenant['id']);
        $tenant = billing_get_tenant((int) $tenant['id']) ?: $tenant;
    }

    if ((string) ($tenant['status'] ?? '') === 'past_due') {
        billing_suspend_past_due_tenants((int) $tenant['id']);
        $tenant = billing_get_tenant((int) $tenant['id']) ?: $tenant;
    }

    $status = (string) ($tenant['status'] ?? '');
    $subscription_status = (string) ($tenant['subscription_status'] ?? '');
    if (billing_subscription_is_manual_access($subscription_status) && in_array($status, ['active', 'trialing'], true)) {
        return ['allowed' => true, 'reason' => $subscription_status, 'message' => ''];
    }

    if ($status === 'active' || ($status === 'trialing' && $subscription_status === 'trialing')) {
        return ['allowed' => true, 'reason' => $status, 'message' => ''];
    }
    if ($status === 'past_due') {
        return ['allowed' => true, 'reason' => 'past_due_grace', 'message' => ''];
    }

    $status_reasons = ['past_due', 'trial_expired', 'suspended', 'blocked', 'canceled'];
    $reason = in_array($status, $status_reasons, true)
        ? $status
        : ($subscription_status !== '' ? $subscription_status : $status);
    $message = match ($reason) {
        'trial_expired' => 'Your ' . billing_trial_days() . '-day trial has ended. Add billing to keep using FoxDesk.',
        'past_due' => 'We could not process payment. Update billing to keep using FoxDesk.',
        'suspended' => 'We could not process payment. Update billing to restore access.',
        'blocked' => 'This workspace is blocked. Contact support to restore access.',
        'canceled' => 'This plan was canceled. Start a new plan to keep using FoxDesk.',
        default => 'This workspace needs a billing review. Open Billing or ask a platform admin.',
    };

    return ['allowed' => false, 'reason' => $reason, 'message' => $message];
}

function billing_manual_access_subscription_statuses(): array
{
    return ['manual', 'free', 'comped'];
}

function billing_subscription_is_manual_access(string $subscription_status): bool
{
    return in_array($subscription_status, billing_manual_access_subscription_statuses(), true);
}

function billing_tenant_billing_action_state(?array $tenant = null, ?array $access_state = null): array
{
    $tenant = $tenant ?: billing_current_tenant();
    if (!$tenant) {
        return [
            'show_checkout' => false,
            'checkout_label' => 'Start plan',
            'show_portal' => false,
            'portal_label' => 'Manage billing',
            'notice_title' => 'Billing is unavailable',
            'notice_body' => 'We could not load billing for this workspace.',
            'notice_variant' => 'warning',
        ];
    }

    $status = (string) ($tenant['status'] ?? '');
    $subscription_status = (string) ($tenant['subscription_status'] ?? 'manual');
    $has_customer = trim((string) ($tenant['stripe_customer_id'] ?? '')) !== '';
    $has_subscription = trim((string) ($tenant['stripe_subscription_id'] ?? '')) !== '';
    $access_state = $access_state ?: billing_workspace_access_state($tenant);
    $reason = (string) ($access_state['reason'] ?? '');

    $state = [
        'show_checkout' => false,
        'checkout_label' => 'Start plan',
        'show_portal' => false,
        'portal_label' => 'Manage billing',
        'notice_title' => '',
        'notice_body' => '',
        'notice_variant' => 'info',
    ];

    if (billing_subscription_is_manual_access($subscription_status)) {
        $billing_enabled = billing_enabled();
        $state['show_portal'] = $billing_enabled;
        $state['portal_label'] = 'Manage billing details';
        $state['notice_title'] = 'All set';
        $state['notice_body'] = $billing_enabled
            ? 'This workspace has platform-approved access. Billing details, address, and VAT ID can still be managed in Stripe.'
            : 'This workspace has platform-approved access.';
        return $state;
    }

    if (!billing_enabled()) {
        $state['notice_title'] = 'Billing is off';
        $state['notice_body'] = 'Platform admins can enable billing from production settings.';
        return $state;
    }

    if ($subscription_status === 'active' || ($status === 'active' && $has_subscription)) {
        $state['show_portal'] = $has_customer;
        $state['notice_title'] = 'Your plan is active';
        $state['notice_body'] = $has_customer
            ? 'Manage invoices, payment method, and cancellation from Billing.'
            : 'No billing action is needed.';
        return $state;
    }

    if ($status === 'trialing' && $subscription_status === 'trialing') {
        $state['show_checkout'] = true;
        $state['checkout_label'] = 'Add billing';
        $state['show_portal'] = $has_customer && $has_subscription;
        $state['notice_title'] = 'Trial active';
        $state['notice_body'] = 'Add billing anytime before the trial ends.';
        return $state;
    }

    if ($status === 'past_due' || $subscription_status === 'past_due' || $reason === 'past_due_grace') {
        $state['show_portal'] = $has_customer;
        $state['show_checkout'] = !$has_customer;
        $state['checkout_label'] = 'Start plan';
        $state['portal_label'] = 'Update payment';
        $state['notice_title'] = 'We could not process payment';
        $state['notice_body'] = $has_customer
            ? 'Update payment to keep this workspace active.'
            : 'Start a plan to keep this workspace active.';
        $state['notice_variant'] = 'warning';
        return $state;
    }

    if ($status === 'trial_expired' || $subscription_status === 'trial_expired') {
        $state['show_checkout'] = true;
        $state['checkout_label'] = 'Start plan';
        $state['notice_title'] = 'Your trial has ended';
        $state['notice_body'] = 'Add billing to keep using FoxDesk.';
        $state['notice_variant'] = 'warning';
        return $state;
    }

    if ($status === 'suspended') {
        $state['show_portal'] = $has_customer;
        $state['show_checkout'] = !$has_customer;
        $state['checkout_label'] = 'Start plan';
        $state['portal_label'] = 'Update payment';
        $state['notice_title'] = 'Workspace suspended';
        $state['notice_body'] = $has_customer
            ? 'Update payment to restore access.'
            : 'Start a plan or contact support to restore access.';
        $state['notice_variant'] = 'warning';
        return $state;
    }

    if ($status === 'canceled' || $subscription_status === 'canceled') {
        $state['show_checkout'] = true;
        $state['checkout_label'] = 'Restart plan';
        $state['notice_title'] = 'Plan canceled';
        $state['notice_body'] = 'Restart the plan to restore access.';
        $state['notice_variant'] = 'warning';
        return $state;
    }

    if ($status === 'blocked') {
        $state['notice_title'] = 'Workspace blocked';
        $state['notice_body'] = 'Contact support to restore access.';
        $state['notice_variant'] = 'warning';
        return $state;
    }

    if ($status === 'active') {
        $state['notice_title'] = 'All set';
        $state['notice_body'] = 'No billing action needed.';
        return $state;
    }

    $state['notice_title'] = 'Needs review';
    $state['notice_body'] = 'Open Billing or ask a platform admin.';
    $state['notice_variant'] = 'warning';
    return $state;
}

function billing_create_or_get_customer(array $tenant): string
{
    $existing = trim((string) ($tenant['stripe_customer_id'] ?? ''));
    if ($existing !== '') {
        $updates = [
            'name' => $tenant['name'] ?? ('Tenant #' . (int) $tenant['id']),
            'metadata[tenant_id]' => (string) (int) $tenant['id'],
            'metadata[tenant_slug]' => (string) ($tenant['slug'] ?? ''),
        ];
        $billing_email = trim((string) ($tenant['billing_email'] ?? ''));
        if ($billing_email !== '') {
            $updates['email'] = $billing_email;
        }
        billing_stripe_request('POST', 'customers/' . rawurlencode($existing), $updates);
        return $existing;
    }

    $customer = billing_stripe_request('POST', 'customers', [
        'name' => $tenant['name'] ?? ('Tenant #' . (int) $tenant['id']),
        'email' => $tenant['billing_email'] ?? '',
        'metadata[tenant_id]' => (string) (int) $tenant['id'],
        'metadata[tenant_slug]' => (string) ($tenant['slug'] ?? ''),
    ]);

    $customer_id = (string) ($customer['id'] ?? '');
    if ($customer_id === '') {
        throw new RuntimeException('Stripe did not return a customer id.');
    }

    db_update('tenants', ['stripe_customer_id' => $customer_id], 'id = ?', [(int) $tenant['id']]);
    return $customer_id;
}

function billing_create_checkout_session(int $tenant_id, string $plan = 'cloud'): string
{
    if (!billing_enabled()) {
        throw new RuntimeException('Billing is not enabled.');
    }

    $tenant = billing_get_tenant($tenant_id);
    if (!$tenant) {
        throw new RuntimeException('Workspace not found.');
    }

    $price_id = billing_plan_price_id($plan);
    if ($price_id === '') {
        throw new RuntimeException('Stripe base price id is not configured.');
    }

    $customer_id = billing_create_or_get_customer($tenant);
    $return_urls = billing_checkout_return_urls($tenant_id);

    $params = [
        'mode' => 'subscription',
        'customer' => $customer_id,
        'client_reference_id' => (string) $tenant_id,
        'line_items[0][price]' => $price_id,
        'line_items[0][quantity]' => '1',
        'success_url' => $return_urls['success'],
        'cancel_url' => $return_urls['cancel'],
        'allow_promotion_codes' => 'true',
        'customer_update[name]' => 'auto',
        'customer_update[address]' => 'auto',
        'metadata[tenant_id]' => (string) $tenant_id,
        'metadata[tenant_slug]' => (string) ($tenant['slug'] ?? ''),
        'metadata[plan]' => billing_plan_code(),
        'subscription_data[metadata][tenant_id]' => (string) $tenant_id,
        'subscription_data[metadata][tenant_slug]' => (string) ($tenant['slug'] ?? ''),
        'subscription_data[metadata][plan]' => billing_plan_code(),
    ];

    $trial_end = billing_checkout_trial_end_timestamp($tenant);
    if ($trial_end !== null) {
        $params['subscription_data[trial_end]'] = (string) $trial_end;
        $params['subscription_data[trial_settings][end_behavior][missing_payment_method]'] = 'cancel';
    }

    $storage_price_id = billing_storage_overage_price_id();
    if ($storage_price_id !== '') {
        $params['line_items[1][price]'] = $storage_price_id;
    }

    if (billing_stripe_tax_enabled()) {
        $params['automatic_tax[enabled]'] = 'true';
    }

    if (billing_tax_id_collection_enabled()) {
        $params['billing_address_collection'] = 'required';
        $params['tax_id_collection[enabled]'] = 'true';
        $tax_id_required = billing_tax_id_collection_required();
        if ($tax_id_required !== '') {
            $params['tax_id_collection[required]'] = $tax_id_required;
        }
    }

    $session = billing_stripe_request('POST', 'checkout/sessions', $params);

    $url = (string) ($session['url'] ?? '');
    if ($url === '') {
        throw new RuntimeException('Stripe did not return a Checkout URL.');
    }

    return $url;
}

function billing_checkout_trial_end_timestamp(array $tenant): ?int
{
    $status = (string) ($tenant['status'] ?? '');
    $subscription_status = (string) ($tenant['subscription_status'] ?? '');
    $trial_ends_at = trim((string) ($tenant['trial_ends_at'] ?? ''));

    if ($status !== 'trialing' || $subscription_status !== 'trialing' || $trial_ends_at === '') {
        return null;
    }

    $trial_end = strtotime($trial_ends_at);
    if (!$trial_end || $trial_end <= time()) {
        return null;
    }

    return $trial_end;
}

function billing_create_portal_session(int $tenant_id): string
{
    if (!billing_enabled()) {
        throw new RuntimeException('Billing is not enabled.');
    }

    $tenant = billing_get_tenant($tenant_id);
    if (!$tenant) {
        throw new RuntimeException('Workspace not found.');
    }

    $customer_id = trim((string) ($tenant['stripe_customer_id'] ?? ''));
    if ($customer_id === '') {
        $customer_id = billing_create_or_get_customer($tenant);
    }

    $user = function_exists('current_user') ? current_user() : null;
    $return_url = is_array($user) && is_platform_admin($user)
        ? APP_URL . '/index.php?page=platform'
        : APP_URL . '/index.php?page=billing';
    $return_url = billing_append_query($return_url, ['tenant_id' => (string) $tenant_id]);

    $params = [
        'customer' => $customer_id,
        'return_url' => $return_url,
    ];

    $configuration_id = billing_portal_configuration_id();
    if ($configuration_id === '') {
        $configurations = billing_stripe_request('GET', 'billing_portal/configurations', [
            'active' => 'true',
            'limit' => '1',
        ]);
        $configuration_id = (string) ($configurations['data'][0]['id'] ?? '');
    }
    if ($configuration_id !== '') {
        $params['configuration'] = $configuration_id;
    }

    $session = billing_stripe_request('POST', 'billing_portal/sessions', $params);

    $url = (string) ($session['url'] ?? '');
    if ($url === '') {
        throw new RuntimeException('Stripe did not return a portal URL.');
    }

    return $url;
}

function billing_verify_stripe_signature(string $payload, string $signature_header, string $secret): bool
{
    if ($secret === '' || $signature_header === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signature_header) as $item) {
        [$key, $value] = array_pad(explode('=', trim($item), 2), 2, '');
        if ($key !== '' && $value !== '') {
            $parts[$key][] = $value;
        }
    }

    $timestamp = (string) ($parts['t'][0] ?? '');
    if ($timestamp === '' || abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);
    foreach ($parts['v1'] ?? [] as $candidate) {
        if (hash_equals($expected, $candidate)) {
            return true;
        }
    }

    return false;
}

function billing_subscription_status_from_stripe(string $stripe_status): string
{
    return match ($stripe_status) {
        'active' => 'active',
        'trialing' => 'trialing',
        'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => 'past_due',
        'canceled' => 'canceled',
        default => 'manual',
    };
}

function billing_tenant_status_from_subscription(string $subscription_status): string
{
    return match ($subscription_status) {
        'active' => 'active',
        'trialing' => 'trialing',
        'past_due' => 'past_due',
        'canceled' => 'canceled',
        'trial_expired' => 'trial_expired',
        default => 'active',
    };
}

function billing_find_tenant_id_for_stripe_object(array $object): ?int
{
    $tenant_id = (int) ($object['metadata']['tenant_id'] ?? 0);
    if ($tenant_id > 0) {
        return $tenant_id;
    }

    $subscription_id = (string) ($object['subscription'] ?? $object['id'] ?? '');
    if ($subscription_id !== '') {
        $tenant = db_fetch_one("SELECT id FROM tenants WHERE stripe_subscription_id = ? LIMIT 1", [$subscription_id]);
        $tenant_id = (int) ($tenant['id'] ?? 0);
        if ($tenant_id > 0) {
            return $tenant_id;
        }
    }

    $customer_id = (string) ($object['customer'] ?? '');
    if ($customer_id !== '') {
        $tenant = db_fetch_one("SELECT id FROM tenants WHERE stripe_customer_id = ? LIMIT 1", [$customer_id]);
        $tenant_id = (int) ($tenant['id'] ?? 0);
        if ($tenant_id > 0) {
            return $tenant_id;
        }
    }

    return null;
}

function billing_apply_subscription_update(array $subscription): ?int
{
    $tenant_id = billing_find_tenant_id_for_stripe_object($subscription);
    if (!$tenant_id) {
        return null;
    }

    $tenant = billing_get_tenant($tenant_id);
    $subscription_status = billing_subscription_status_from_stripe((string) ($subscription['status'] ?? ''));
    $tenant_status = billing_tenant_status_from_subscription($subscription_status);
    $trial_end = !empty($subscription['trial_end'])
        ? date('Y-m-d H:i:s', (int) $subscription['trial_end'])
        : null;
    $past_due_started_at = trim((string) ($tenant['suspended_at'] ?? ''));
    $now = date('Y-m-d H:i:s');

    $updates = [
        'stripe_subscription_id' => (string) ($subscription['id'] ?? ''),
        'subscription_status' => $subscription_status,
        'status' => $tenant_status,
        'trial_ends_at' => $trial_end,
        'suspended_at' => null,
        'blocked_at' => null,
    ];

    if ($tenant_status === 'past_due') {
        $updates['suspended_at'] = $past_due_started_at !== '' ? $past_due_started_at : $now;
    } elseif (in_array($tenant_status, ['trial_expired', 'blocked', 'canceled'], true)) {
        $updates['suspended_at'] = $now;
        $updates['blocked_at'] = $now;
    }

    if (!empty($subscription['customer'])) {
        $updates['stripe_customer_id'] = (string) $subscription['customer'];
    }

    db_update('tenants', $updates, 'id = ?', [$tenant_id]);

    return $tenant_id;
}

function billing_apply_invoice_payment_state(array $invoice, bool $paid): ?int
{
    $tenant_id = billing_find_tenant_id_for_stripe_object($invoice);
    if (!$tenant_id) {
        return null;
    }

    $tenant = billing_get_tenant($tenant_id);
    $past_due_started_at = trim((string) ($tenant['suspended_at'] ?? ''));
    $updates = [
        'subscription_status' => $paid ? 'active' : 'past_due',
        'status' => $paid ? 'active' : 'past_due',
        'suspended_at' => null,
        'blocked_at' => null,
    ];

    if (!$paid) {
        $updates['suspended_at'] = $past_due_started_at !== '' ? $past_due_started_at : date('Y-m-d H:i:s');
    }
    if (!empty($invoice['customer'])) {
        $updates['stripe_customer_id'] = (string) $invoice['customer'];
    }
    if (!empty($invoice['subscription'])) {
        $updates['stripe_subscription_id'] = (string) $invoice['subscription'];
    }

    db_update('tenants', $updates, 'id = ?', [$tenant_id]);
    return $tenant_id;
}

function billing_tenant_id_from_webhook_event(array $event): ?int
{
    $object = $event['data']['object'] ?? [];
    if (!is_array($object)) {
        return null;
    }

    $tenant_id = (int) ($object['metadata']['tenant_id'] ?? $object['client_reference_id'] ?? 0);
    if ($tenant_id > 0) {
        return $tenant_id;
    }

    return billing_find_tenant_id_for_stripe_object($object);
}

function billing_reserve_stripe_event(string $event_id, string $event_type, ?int $tenant_id = null): array
{
    billing_ensure_stripe_events_table();

    try {
        db_insert('billing_stripe_events', [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'tenant_id' => $tenant_id,
            'status' => 'pending',
        ]);
        return ['reserved' => true];
    } catch (Throwable $e) {
        $existing = db_fetch_one(
            "SELECT event_id, event_type, tenant_id, status, error_message FROM billing_stripe_events WHERE event_id = ? LIMIT 1",
            [$event_id]
        );
        if ($existing) {
            return ['reserved' => false, 'event' => $existing];
        }

        throw $e;
    }
}

function billing_finish_stripe_event(string $event_id, string $status, ?int $tenant_id = null, ?string $error_message = null): void
{
    billing_ensure_stripe_events_table();

    db_update('billing_stripe_events', [
        'tenant_id' => $tenant_id,
        'status' => $status,
        'error_message' => $error_message,
        'processed_at' => date('Y-m-d H:i:s'),
    ], 'event_id = ?', [$event_id]);
}

function billing_process_webhook_event(array $event): array
{
    $type = (string) ($event['type'] ?? '');
    $object = $event['data']['object'] ?? [];
    if (!is_array($object)) {
        return ['handled' => false, 'type' => $type];
    }

    if (in_array($type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
        $tenant_id = billing_apply_subscription_update($object);
        return ['handled' => $tenant_id !== null, 'tenant_id' => $tenant_id, 'type' => $type];
    }

    if ($type === 'checkout.session.completed' && !empty($object['subscription'])) {
        $tenant_id = (int) ($object['metadata']['tenant_id'] ?? $object['client_reference_id'] ?? 0);
        if ($tenant_id > 0) {
            db_update('tenants', [
                'stripe_customer_id' => (string) ($object['customer'] ?? ''),
                'stripe_subscription_id' => (string) $object['subscription'],
                'subscription_status' => 'active',
                'status' => 'active',
                'suspended_at' => null,
                'blocked_at' => null,
            ], 'id = ?', [$tenant_id]);
            return ['handled' => true, 'tenant_id' => $tenant_id, 'type' => $type];
        }
    }

    if (in_array($type, ['invoice.paid', 'invoice.payment_succeeded'], true)) {
        $tenant_id = billing_apply_invoice_payment_state($object, true);
        return ['handled' => $tenant_id !== null, 'tenant_id' => $tenant_id, 'type' => $type];
    }

    if ($type === 'invoice.payment_failed') {
        $tenant_id = billing_apply_invoice_payment_state($object, false);
        return ['handled' => $tenant_id !== null, 'tenant_id' => $tenant_id, 'type' => $type];
    }

    return ['handled' => false, 'type' => $type];
}

function billing_handle_webhook_event(array $event): array
{
    $event_id = trim((string) ($event['id'] ?? ''));
    $type = (string) ($event['type'] ?? '');

    if ($event_id === '') {
        $result = billing_process_webhook_event($event);
        return ['idempotent' => false] + $result;
    }

    $tenant_id = billing_tenant_id_from_webhook_event($event);
    $reservation = billing_reserve_stripe_event($event_id, $type, $tenant_id);
    if (empty($reservation['reserved'])) {
        $existing = is_array($reservation['event'] ?? null) ? $reservation['event'] : [];
        return [
            'handled' => false,
            'duplicate' => true,
            'event_id' => $event_id,
            'tenant_id' => isset($existing['tenant_id']) ? (int) $existing['tenant_id'] : null,
            'type' => (string) ($existing['event_type'] ?? $type),
            'status' => (string) ($existing['status'] ?? 'processed'),
        ];
    }

    try {
        $result = billing_process_webhook_event($event);
        $processed_tenant_id = isset($result['tenant_id']) ? (int) $result['tenant_id'] : $tenant_id;
        billing_finish_stripe_event($event_id, !empty($result['handled']) ? 'processed' : 'ignored', $processed_tenant_id);
        return ['event_id' => $event_id, 'duplicate' => false] + $result;
    } catch (Throwable $e) {
        billing_finish_stripe_event($event_id, 'failed', $tenant_id, $e->getMessage());
        throw $e;
    }
}
