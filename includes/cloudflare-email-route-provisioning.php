<?php
/**
 * Cloudflare Email Routing provisioning for workspace aliases.
 *
 * Cloudflare does not support routing Email Routing catch-all messages directly
 * to a Worker. Each friendly workspace alias therefore needs a routing rule.
 */

require_once __DIR__ . '/email-routing-functions.php';

function cloudflare_email_route_env(array $names, string $default = ''): string
{
    foreach ($names as $name) {
        $value = foxdesk_email_env_or_constant($name, '');
        if (trim($value) !== '') {
            return trim($value);
        }
    }

    return $default;
}

function cloudflare_email_route_bool(string $name, bool $default): bool
{
    $value = foxdesk_email_env_or_constant($name, '');
    if (trim($value) === '') {
        return $default;
    }

    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function cloudflare_email_route_config(): array
{
    $api_token = cloudflare_email_route_env([
        'CLOUDFLARE_EMAIL_ROUTING_API_TOKEN',
        'FOXDESK_CLOUDFLARE_EMAIL_ROUTING_API_TOKEN',
        'CLOUDFLARE_API_TOKEN',
        'CF_API_TOKEN',
    ]);
    $zone_id = cloudflare_email_route_env([
        'CLOUDFLARE_ZONE_ID',
        'FOXDESK_CLOUDFLARE_ZONE_ID',
        'CF_ZONE_ID',
    ]);
    $zone_name = cloudflare_email_route_env([
        'CLOUDFLARE_ZONE_NAME',
        'FOXDESK_CLOUDFLARE_ZONE_NAME',
    ], foxdesk_ticket_email_domain());
    $worker_name = cloudflare_email_route_env([
        'FOXDESK_EMAIL_ROUTER_WORKER',
        'CLOUDFLARE_EMAIL_ROUTER_WORKER',
    ], 'foxdesk-email-router');

    return [
        'enabled' => cloudflare_email_route_bool('FOXDESK_EMAIL_ROUTE_PROVISIONING_ENABLED', $api_token !== ''),
        'api_token' => $api_token,
        'zone_id' => $zone_id,
        'zone_name' => $zone_name,
        'worker_name' => $worker_name,
    ];
}

function cloudflare_email_route_api(string $method, string $path, ?array $body = null, ?array $config = null): array
{
    $config = $config ?: cloudflare_email_route_config();
    $token = (string) ($config['api_token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('Cloudflare Email Routing API token is not configured.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP curl extension is required for Cloudflare route provisioning.');
    }

    $url = 'https://api.cloudflare.com/client/v4/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $error !== '') {
        throw new RuntimeException('Cloudflare API request failed: ' . $error);
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Cloudflare API returned invalid JSON.');
    }

    if ($status < 200 || $status >= 300 || empty($decoded['success'])) {
        $messages = [];
        foreach (($decoded['errors'] ?? []) as $err) {
            if (is_array($err) && !empty($err['message'])) {
                $messages[] = (string) $err['message'];
            }
        }
        $message = $messages ? implode('; ', $messages) : ('HTTP ' . $status);
        throw new RuntimeException('Cloudflare API error: ' . $message);
    }

    return $decoded;
}

function cloudflare_email_route_zone_id(?array $config = null): string
{
    $config = $config ?: cloudflare_email_route_config();
    $zone_id = trim((string) ($config['zone_id'] ?? ''));
    if ($zone_id !== '') {
        return $zone_id;
    }

    $zone_name = trim((string) ($config['zone_name'] ?? ''));
    if ($zone_name === '') {
        throw new RuntimeException('Cloudflare zone name is not configured.');
    }

    $response = cloudflare_email_route_api(
        'GET',
        'zones?name=' . rawurlencode($zone_name) . '&per_page=1',
        null,
        $config
    );
    $zone = $response['result'][0] ?? null;
    $resolved = is_array($zone) ? trim((string) ($zone['id'] ?? '')) : '';
    if ($resolved === '') {
        throw new RuntimeException('Cloudflare zone was not found for ' . $zone_name . '.');
    }

    return $resolved;
}

function cloudflare_email_route_find_rule(string $address, ?array $config = null): ?array
{
    $config = $config ?: cloudflare_email_route_config();
    $zone_id = cloudflare_email_route_zone_id($config);

    $needle = strtolower(trim($address));
    $page = 1;
    $total_pages = 1;
    do {
        $response = cloudflare_email_route_api(
            'GET',
            'zones/' . rawurlencode($zone_id) . '/email/routing/rules?per_page=100&page=' . $page,
            null,
            $config
        );
        $result_info = is_array($response['result_info'] ?? null) ? $response['result_info'] : [];
        $total_pages = max(1, (int) ($result_info['total_pages'] ?? $total_pages));

        foreach (($response['result'] ?? []) as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            foreach (($rule['matchers'] ?? []) as $matcher) {
                if (!is_array($matcher)) {
                    continue;
                }
                $field = strtolower((string) ($matcher['field'] ?? ''));
                $value = strtolower(trim((string) ($matcher['value'] ?? '')));
                if ($field === 'to' && $value === $needle) {
                    return $rule;
                }
            }
        }
        $page++;
    } while ($page <= $total_pages);

    return null;
}

function cloudflare_email_route_rule_points_to_worker(array $rule, string $worker_name): bool
{
    foreach (($rule['actions'] ?? []) as $action) {
        if (!is_array($action)) {
            continue;
        }
        if (($action['type'] ?? '') !== 'worker') {
            continue;
        }
        $value = $action['value'] ?? [];
        $values = is_array($value) ? $value : [$value];
        if (in_array($worker_name, array_map('strval', $values), true)) {
            return true;
        }
    }

    return false;
}

function cloudflare_email_routing_provision_workspace_alias($tenant, bool $dry_run = false): array
{
    $config = cloudflare_email_route_config();
    $address = foxdesk_workspace_public_inbound_address($tenant);
    $worker_name = (string) ($config['worker_name'] ?? 'foxdesk-email-router');

    if ($address === '') {
        return ['ok' => true, 'status' => 'skipped', 'reason' => 'empty_alias'];
    }
    if (empty($config['enabled'])) {
        return ['ok' => true, 'status' => 'skipped', 'reason' => 'provisioning_disabled', 'address' => $address];
    }
    if (trim((string) ($config['api_token'] ?? '')) === '') {
        return ['ok' => true, 'status' => 'skipped', 'reason' => 'api_token_missing', 'address' => $address];
    }

    $existing = cloudflare_email_route_find_rule($address, $config);
    if ($existing) {
        $rule_id = (string) ($existing['id'] ?? '');
        if (cloudflare_email_route_rule_points_to_worker($existing, $worker_name)) {
            return ['ok' => true, 'status' => 'exists', 'address' => $address, 'rule_id' => $rule_id];
        }

        return [
            'ok' => false,
            'status' => 'conflict',
            'address' => $address,
            'rule_id' => $rule_id,
            'message' => 'A Cloudflare Email Routing rule already exists for this alias but does not point to the FoxDesk email router.',
        ];
    }

    if ($dry_run) {
        return ['ok' => true, 'status' => 'would_create', 'address' => $address, 'worker' => $worker_name];
    }

    $zone_id = cloudflare_email_route_zone_id($config);
    $body = [
        'name' => 'Route ' . $address . ' to FoxDesk email router',
        'enabled' => true,
        'matchers' => [[
            'type' => 'literal',
            'field' => 'to',
            'value' => $address,
        ]],
        'actions' => [[
            'type' => 'worker',
            'value' => [$worker_name],
        ]],
        'priority' => 0,
    ];

    $response = cloudflare_email_route_api(
        'POST',
        'zones/' . rawurlencode($zone_id) . '/email/routing/rules',
        $body,
        $config
    );
    $rule = $response['result'] ?? [];

    return [
        'ok' => true,
        'status' => 'created',
        'address' => $address,
        'rule_id' => is_array($rule) ? (string) ($rule['id'] ?? '') : '',
    ];
}

function cloudflare_email_routing_sync_workspace_aliases(bool $dry_run = false, int $tenant_id = 0): array
{
    if (!function_exists('db_fetch_all')) {
        throw new RuntimeException('Database helpers are not available.');
    }

    $params = [];
    $where = "slug <> 'default'";
    if ($tenant_id > 0) {
        $where .= ' AND id = ?';
        $params[] = $tenant_id;
    } else {
        $where .= " AND status IN ('active', 'trialing', 'past_due', 'trial_expired', 'suspended')";
    }

    $tenants = db_fetch_all("SELECT id, name, slug, status FROM tenants WHERE {$where} ORDER BY id", $params);
    $results = [];
    foreach ($tenants as $tenant) {
        try {
            $result = cloudflare_email_routing_provision_workspace_alias($tenant, $dry_run);
        } catch (Throwable $e) {
            $result = [
                'ok' => false,
                'status' => 'failed',
                'address' => function_exists('foxdesk_workspace_public_inbound_address') ? foxdesk_workspace_public_inbound_address($tenant) : '',
                'message' => $e->getMessage(),
            ];
        }
        $result['tenant_id'] = (int) ($tenant['id'] ?? 0);
        $result['tenant_slug'] = (string) ($tenant['slug'] ?? '');
        $results[] = $result;
    }

    $failed = array_values(array_filter($results, static fn($item) => empty($item['ok'])));
    return [
        'ok' => count($failed) === 0,
        'dry_run' => $dry_run,
        'count' => count($results),
        'failed' => count($failed),
        'results' => $results,
    ];
}
