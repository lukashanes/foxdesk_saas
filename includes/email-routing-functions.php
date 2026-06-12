<?php
/**
 * Mailbox-less ticket email routing helpers for FoxDesk Cloud.
 *
 * The SaaS path uses deterministic HMAC-protected local parts. Cloudflare Email
 * Routing can route a single base address with plus addressing, for example:
 * tickets+tk-123-token@foxdesk.net.
 */

function foxdesk_email_env_or_constant(string $name, string $default = ''): string
{
    if (defined($name)) {
        return (string) constant($name);
    }

    $value = getenv($name);
    return $value !== false ? (string) $value : $default;
}

function foxdesk_email_env_bool(string $name, bool $default = false): bool
{
    if (defined($name)) {
        return (bool) constant($name);
    }

    $value = getenv($name);
    if ($value === false || trim((string) $value) === '') {
        return $default;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function foxdesk_app_edition_value(): string
{
    $edition = foxdesk_email_env_or_constant('FOXDESK_EDITION', '');
    if ($edition === '') {
        $edition = foxdesk_email_env_or_constant('FOXDESK_APP_EDITION', '');
    }

    return strtolower(trim($edition));
}

function foxdesk_cloudflare_email_ingest_enabled(): bool
{
    $flag = getenv('FOXDESK_CLOUDFLARE_EMAIL_INGEST_ENABLED');
    if (defined('FOXDESK_CLOUDFLARE_EMAIL_INGEST_ENABLED') || ($flag !== false && trim((string) $flag) !== '')) {
        return foxdesk_email_env_bool('FOXDESK_CLOUDFLARE_EMAIL_INGEST_ENABLED', false);
    }

    $edition = foxdesk_app_edition_value();
    if ($edition !== '') {
        return in_array($edition, ['saas', 'cloud', 'managed'], true);
    }

    return defined('APP_MARKETING_HOST');
}

function foxdesk_ticket_email_domain(): string
{
    $domain = strtolower(trim(foxdesk_email_env_or_constant('FOXDESK_TICKET_EMAIL_DOMAIN', 'tickets.foxdesk.net')));
    $domain = preg_replace('/[^a-z0-9.-]/', '', $domain);
    return $domain !== '' ? $domain : 'tickets.foxdesk.net';
}

function foxdesk_ticket_email_local_part(): string
{
    $local = strtolower(trim(foxdesk_email_env_or_constant('FOXDESK_TICKET_EMAIL_LOCAL_PART', '')));
    $local = preg_replace('/[^a-z0-9._-]/', '', $local);
    return trim((string) $local, '.-_');
}

function foxdesk_ticket_route_address(string $route_local): string
{
    $route_local = strtolower(trim($route_local));
    $route_local = preg_replace('/[^a-z0-9._+-]/', '', $route_local);
    $route_local = trim((string) $route_local, '.-_+');
    if ($route_local === '') {
        return '';
    }

    $base_local = foxdesk_ticket_email_local_part();
    if ($base_local !== '') {
        return $base_local . '+' . $route_local . '@' . foxdesk_ticket_email_domain();
    }

    return $route_local . '@' . foxdesk_ticket_email_domain();
}

function foxdesk_ticket_route_local_from_address_local(string $local): string
{
    $local = strtolower(trim($local));
    if ($local === '') {
        return '';
    }

    $base_local = foxdesk_ticket_email_local_part();
    if ($base_local !== '' && str_starts_with($local, $base_local . '+')) {
        return substr($local, strlen($base_local) + 1);
    }

    return $local;
}

function foxdesk_email_route_secret(): string
{
    $secret = foxdesk_email_env_or_constant('FOXDESK_EMAIL_ROUTE_SECRET', '');
    $allow_secret_key_fallback = defined('FOXDESK_EMAIL_ALLOW_SECRET_KEY_FALLBACK')
        && (bool) FOXDESK_EMAIL_ALLOW_SECRET_KEY_FALLBACK;
    if ($secret === '' && $allow_secret_key_fallback && defined('SECRET_KEY')) {
        $secret = (string) SECRET_KEY;
    }
    return $secret;
}

function foxdesk_email_route_token(string $kind, int $id, int $tenant_id = 0, int $length = 14): string
{
    $secret = foxdesk_email_route_secret();
    if ($secret === '') {
        return '';
    }

    $material = $kind . ':' . $tenant_id . ':' . $id;
    return substr(hash_hmac('sha256', $material, $secret), 0, max(10, $length));
}

function foxdesk_ticket_route_tenant_id(int $ticket_id): int
{
    if ($ticket_id <= 0) {
        return 0;
    }

    if (function_exists('db_fetch_one')) {
        try {
            if (!function_exists('table_exists') || table_exists('tickets')) {
                if (!function_exists('column_exists') || column_exists('tickets', 'tenant_id')) {
                    $row = db_fetch_one("SELECT tenant_id FROM tickets WHERE id = ? LIMIT 1", [$ticket_id]);
                    $tenant_id = (int) ($row['tenant_id'] ?? 0);
                    if ($tenant_id > 0) {
                        return $tenant_id;
                    }
                } else {
                    $row = db_fetch_one("SELECT id FROM tickets WHERE id = ? LIMIT 1", [$ticket_id]);
                    if ($row) {
                        return function_exists('current_tenant_id') ? current_tenant_id() : 0;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall through to session/default tenant below.
        }
    }

    return function_exists('current_tenant_id') ? current_tenant_id() : 0;
}

function foxdesk_ticket_reply_address($ticket): string
{
    $ticket_id = is_array($ticket) ? (int) ($ticket['id'] ?? 0) : (int) $ticket;
    if ($ticket_id <= 0) {
        return '';
    }

    $tenant_id = is_array($ticket) && isset($ticket['tenant_id'])
        ? (int) $ticket['tenant_id']
        : foxdesk_ticket_route_tenant_id($ticket_id);

    $token = foxdesk_email_route_token('ticket', $ticket_id, $tenant_id);
    if ($token === '') {
        return '';
    }

    return foxdesk_ticket_route_address('tk-' . $ticket_id . '-' . $token);
}

function foxdesk_workspace_inbound_address($tenant = null): string
{
    $tenant_id = 0;
    $slug = 'workspace';

    if (is_array($tenant)) {
        $tenant_id = (int) ($tenant['id'] ?? 0);
        $slug = (string) ($tenant['slug'] ?? $slug);
    } elseif (is_numeric($tenant)) {
        $tenant_id = (int) $tenant;
    }

    if ($tenant_id <= 0 && function_exists('current_tenant_id')) {
        $tenant_id = current_tenant_id();
    }
    if ($tenant_id <= 0) {
        return '';
    }

    if ($slug === 'workspace' && function_exists('db_fetch_one')) {
        try {
            $row = db_fetch_one("SELECT slug FROM tenants WHERE id = ? LIMIT 1", [$tenant_id]);
            if (!empty($row['slug'])) {
                $slug = (string) $row['slug'];
            }
        } catch (Throwable $e) {
            $slug = 'workspace';
        }
    }

    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string) $slug, '-');
    $slug = $slug !== '' ? substr($slug, 0, 48) : 'workspace';

    $token = foxdesk_email_route_token('workspace', $tenant_id, $tenant_id);
    if ($token === '') {
        return '';
    }

    return foxdesk_ticket_route_address($slug . '-' . $tenant_id . '-' . $token);
}

function foxdesk_parse_ticket_email_address(string $address): ?array
{
    $address = strtolower(trim($address));
    if ($address === '') {
        return null;
    }

    if (preg_match('/<([^>]+)>/', $address, $matches)) {
        $address = strtolower(trim($matches[1]));
    }

    $parts = explode('@', $address, 2);
    if (count($parts) !== 2 || $parts[1] !== foxdesk_ticket_email_domain()) {
        return null;
    }

    $local = foxdesk_ticket_route_local_from_address_local($parts[0]);
    if (preg_match('/^tk-([0-9]+)-([a-f0-9]{10,64})$/', $local, $matches)) {
        $ticket_id = (int) $matches[1];
        $provided = $matches[2];
        $tenant_id = foxdesk_ticket_route_tenant_id($ticket_id);
        $expected = foxdesk_email_route_token('ticket', $ticket_id, $tenant_id, strlen($provided));
        if ($expected !== '' && hash_equals($expected, $provided)) {
            return [
                'kind' => 'ticket',
                'ticket_id' => $ticket_id,
                'tenant_id' => $tenant_id,
            ];
        }
        return null;
    }

    if (preg_match('/^(.+)-([0-9]+)-([a-f0-9]{10,64})$/', $local, $matches)) {
        $tenant_id = (int) $matches[2];
        $provided = $matches[3];
        $expected = foxdesk_email_route_token('workspace', $tenant_id, $tenant_id, strlen($provided));
        if ($expected !== '' && hash_equals($expected, $provided)) {
            return [
                'kind' => 'workspace',
                'tenant_id' => $tenant_id,
            ];
        }
    }

    return null;
}

function foxdesk_first_routable_ticket_address(array $addresses): ?array
{
    foreach ($addresses as $address) {
        $parsed = foxdesk_parse_ticket_email_address((string) $address);
        if ($parsed !== null) {
            $parsed['address'] = strtolower(trim((string) $address));
            return $parsed;
        }
    }
    return null;
}
