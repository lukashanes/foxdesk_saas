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
    return max(0, (int) billing_env_or_constant('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 79));
}

function billing_included_storage_bytes(): int
{
    return max(0, (int) billing_env_or_constant('BILLING_INCLUDED_STORAGE_BYTES', 1073741824));
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
    return trim((string) billing_env_or_constant('STRIPE_STORAGE_METER_EVENT_NAME', 'foxdesk_storage_extra_gb'));
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

function billing_tenant_usage(int $tenant_id): array
{
    ensure_tenant_baseline();

    $user_count = 0;
    $agent_count = 0;
    $client_count = 0;
    $ticket_count = 0;
    $storage_bytes = 0;

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

    if (table_exists('attachments')) {
        $attachments = db_fetch_one("SELECT COALESCE(SUM(file_size), 0) AS bytes FROM attachments WHERE tenant_id = ?", [$tenant_id]);
        $storage_bytes = (int) ($attachments['bytes'] ?? 0);
    }

    $included_bytes = billing_included_storage_bytes();
    $extra_bytes = max(0, $storage_bytes - $included_bytes);
    $extra_gb = $extra_bytes > 0 ? (int) ceil($extra_bytes / 1073741824) : 0;
    $overage_cents = $extra_gb * billing_storage_overage_price_cents();

    return [
        'users' => $user_count,
        'agents' => $agent_count,
        'clients' => $client_count,
        'tickets' => $ticket_count,
        'storage_bytes' => $storage_bytes,
        'included_storage_bytes' => $included_bytes,
        'extra_storage_bytes' => $extra_bytes,
        'extra_storage_gb' => $extra_gb,
        'storage_overage_cents' => $overage_cents,
    ];
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

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $method = strtoupper($method);

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

    if (!empty($params)) {
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
    if ($existing && in_array((string) $existing['status'], ['reported', 'dry_run'], true)) {
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

function billing_report_storage_usage_all(bool $dry_run = false): array
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
        $result = billing_report_storage_usage_for_tenant((int) $row['id'], null, $dry_run);
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

function billing_create_or_get_customer(array $tenant): string
{
    $existing = trim((string) ($tenant['stripe_customer_id'] ?? ''));
    if ($existing !== '') {
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
    $params = [
        'mode' => 'subscription',
        'customer' => $customer_id,
        'line_items[0][price]' => $price_id,
        'line_items[0][quantity]' => '1',
        'success_url' => (string) billing_env_or_constant('STRIPE_SUCCESS_URL', APP_URL . '/index.php?page=platform&billing=success'),
        'cancel_url' => (string) billing_env_or_constant('STRIPE_CANCEL_URL', APP_URL . '/index.php?page=platform&billing=cancelled'),
        'metadata[tenant_id]' => (string) $tenant_id,
        'subscription_data[metadata][tenant_id]' => (string) $tenant_id,
        'subscription_data[metadata][plan]' => billing_plan_code(),
    ];

    $storage_price_id = billing_storage_overage_price_id();
    if ($storage_price_id !== '') {
        $params['line_items[1][price]'] = $storage_price_id;
    }

    $session = billing_stripe_request('POST', 'checkout/sessions', $params);

    $url = (string) ($session['url'] ?? '');
    if ($url === '') {
        throw new RuntimeException('Stripe did not return a Checkout URL.');
    }

    return $url;
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

    $session = billing_stripe_request('POST', 'billing_portal/sessions', [
        'customer' => $customer_id,
        'return_url' => APP_URL . '/index.php?page=platform',
    ]);

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
        default => 'active',
    };
}

function billing_apply_subscription_update(array $subscription): ?int
{
    $tenant_id = (int) ($subscription['metadata']['tenant_id'] ?? 0);
    if ($tenant_id <= 0 && !empty($subscription['customer'])) {
        $tenant = db_fetch_one("SELECT id FROM tenants WHERE stripe_customer_id = ? LIMIT 1", [(string) $subscription['customer']]);
        $tenant_id = (int) ($tenant['id'] ?? 0);
    }
    if ($tenant_id <= 0) {
        return null;
    }

    $subscription_status = billing_subscription_status_from_stripe((string) ($subscription['status'] ?? ''));
    $tenant_status = billing_tenant_status_from_subscription($subscription_status);
    $trial_end = !empty($subscription['trial_end'])
        ? date('Y-m-d H:i:s', (int) $subscription['trial_end'])
        : null;

    $updates = [
        'stripe_subscription_id' => (string) ($subscription['id'] ?? ''),
        'subscription_status' => $subscription_status,
        'status' => $tenant_status,
        'trial_ends_at' => $trial_end,
        'suspended_at' => $tenant_status === 'suspended' ? date('Y-m-d H:i:s') : null,
    ];

    if (!empty($subscription['customer'])) {
        $updates['stripe_customer_id'] = (string) $subscription['customer'];
    }

    db_update('tenants', $updates, 'id = ?', [$tenant_id]);

    return $tenant_id;
}

function billing_handle_webhook_event(array $event): array
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
        $tenant_id = (int) ($object['metadata']['tenant_id'] ?? 0);
        if ($tenant_id > 0) {
            db_update('tenants', [
                'stripe_customer_id' => (string) ($object['customer'] ?? ''),
                'stripe_subscription_id' => (string) $object['subscription'],
                'subscription_status' => 'active',
                'status' => 'active',
            ], 'id = ?', [$tenant_id]);
            return ['handled' => true, 'tenant_id' => $tenant_id, 'type' => $type];
        }
    }

    return ['handled' => false, 'type' => $type];
}
