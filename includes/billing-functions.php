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

function billing_stripe_request(string $method, string $path, array $params = []): array
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
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secret,
            'Stripe-Version: 2026-02-25.clover',
        ],
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
