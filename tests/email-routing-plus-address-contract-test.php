<?php
define('BASE_PATH', dirname(__DIR__));

function assert_email_routing_contract($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$helper = file_get_contents(BASE_PATH . '/includes/email-routing-functions.php');
$config = file_get_contents(BASE_PATH . '/config.production.example.php');
$docs = file_get_contents(BASE_PATH . '/docs/CLOUDFLARE_EMAIL.md');
$worker = file_get_contents(BASE_PATH . '/cloudflare/email-router/src/index.ts');
$handler = file_get_contents(BASE_PATH . '/includes/api/cloudflare-email-handler.php');
$archive_smoke = file_get_contents(BASE_PATH . '/bin/test-cloudflare-inbound-archive.php');

putenv('FOXDESK_TICKET_EMAIL_DOMAIN=foxdesk.net');
putenv('FOXDESK_TICKET_EMAIL_LOCAL_PART=tickets');
putenv('FOXDESK_EMAIL_ROUTE_SECRET=test-route-secret-for-contracts');

function current_tenant_id(): int
{
    return 3;
}

function table_exists(string $table): bool
{
    return in_array($table, ['tenants', 'tickets'], true);
}

function db_fetch_one(string $sql, array $params = []): array
{
    if (str_contains($sql, 'FROM tenants WHERE id = ?')) {
        return ['id' => (int) ($params[0] ?? 0), 'slug' => 'aenze-helpdesk'];
    }

    if (str_contains($sql, 'FROM tenants WHERE slug = ?')) {
        return ((string) ($params[0] ?? '') === 'aenze-helpdesk') ? ['id' => 3] : [];
    }

    if (str_contains($sql, 'FROM tickets')) {
        return ['tenant_id' => 3];
    }

    return [];
}

require_once BASE_PATH . '/includes/email-routing-functions.php';

assert_email_routing_contract(strpos($helper, 'function foxdesk_ticket_email_local_part') !== false, 'Ticket email local-part helper is missing.');
assert_email_routing_contract(strpos($helper, 'FOXDESK_TICKET_EMAIL_LOCAL_PART') !== false, 'Ticket email local-part config is not used.');
assert_email_routing_contract(strpos($helper, "return \$base_local . '+' . \$route_local . '@'") !== false, 'Ticket email helper must generate plus-addressed routes when a base local part is configured.');
assert_email_routing_contract(strpos($helper, 'str_starts_with($local, $base_local . \'+\')') !== false, 'Ticket email parser must accept plus-addressed routes.');
assert_email_routing_contract(strpos($config, 'FOXDESK_TICKET_EMAIL_LOCAL_PART') !== false, 'Production config example is missing ticket local-part config.');
assert_email_routing_contract(strpos($docs, 'tickets+tk-123-<token>@foxdesk.net') !== false, 'Cloudflare email docs must show the plus-addressed ticket reply format.');
assert_email_routing_contract(strpos($docs, 'aenze-helpdesk@foxdesk.net') !== false, 'Cloudflare email docs must show the friendly workspace alias.');
assert_email_routing_contract(strpos($docs, 'test-cloudflare-inbound-archive.php') !== false, 'Cloudflare email docs must include the inbound archive smoke.');
assert_email_routing_contract(strpos($worker, 'raw_archive_verified') !== false, 'Worker must mark raw email archive verification.');
assert_email_routing_contract(strpos($worker, 'archive_verified') !== false, 'Worker must mark attachment archive verification.');
assert_email_routing_contract(strpos($handler, 'api_cloudflare_email_verify_archive_payload') !== false, 'Backend must reject unverified email archive payloads.');
assert_email_routing_contract(strpos($handler, 'Email attachment archive was not verified') !== false, 'Backend must reject unverified archived attachment metadata.');
assert_email_routing_contract(strpos($archive_smoke, 'foxdesk-email-archive/') !== false, 'Inbound archive smoke must verify the Worker archive bucket.');
assert_email_routing_contract(strpos($archive_smoke, "'attachments' => [[") !== false, 'Inbound archive smoke must send a real attachment.');

$workspace_address = foxdesk_workspace_inbound_address(['id' => 3, 'slug' => 'aenze-helpdesk']);
assert_email_routing_contract(
    preg_match('/^tickets\+aenze-helpdesk-3-[a-f0-9]{14}@foxdesk\.net$/', $workspace_address) === 1,
    'Internal workspace inbound address must remain a signed plus-address: ' . $workspace_address
);
$workspace_route = foxdesk_parse_ticket_email_address('Aenze <' . $workspace_address . '>');
assert_email_routing_contract(is_array($workspace_route), 'Workspace inbound address must parse.');
assert_email_routing_contract(($workspace_route['kind'] ?? '') === 'workspace', 'Workspace route must identify workspace kind.');
assert_email_routing_contract((int) ($workspace_route['tenant_id'] ?? 0) === 3, 'Workspace route must identify tenant id 3.');

$workspace_public_address = foxdesk_workspace_public_inbound_address(['id' => 3, 'slug' => 'aenze-helpdesk']);
assert_email_routing_contract(
    $workspace_public_address === 'aenze-helpdesk@foxdesk.net',
    'Public workspace inbound address must be the friendly workspace alias: ' . $workspace_public_address
);
$workspace_public_route = foxdesk_parse_ticket_email_address('Aenze <' . $workspace_public_address . '>');
assert_email_routing_contract(is_array($workspace_public_route), 'Public workspace inbound address must parse.');
assert_email_routing_contract(($workspace_public_route['kind'] ?? '') === 'workspace', 'Public workspace route must identify workspace kind.');
assert_email_routing_contract((int) ($workspace_public_route['tenant_id'] ?? 0) === 3, 'Public workspace route must identify tenant id 3.');
assert_email_routing_contract(!empty($workspace_public_route['public_alias']), 'Public workspace route must be marked as public alias.');
assert_email_routing_contract(is_array(foxdesk_parse_ticket_email_address('tickets+aenze-helpdesk@foxdesk.net')), 'Friendly plus alias must also route.');
assert_email_routing_contract(foxdesk_parse_ticket_email_address('tickets@foxdesk.net') === null, 'The base mailbox must not route to a workspace by itself.');
assert_email_routing_contract(foxdesk_parse_ticket_email_address('tickets+unknown@foxdesk.net') === null, 'Unsigned plus-addresses must not route.');
assert_email_routing_contract(foxdesk_parse_ticket_email_address('unknown@foxdesk.net') === null, 'Unknown public workspace aliases must not route.');

$ticket_reply = foxdesk_ticket_reply_address(['id' => 123, 'tenant_id' => 3]);
assert_email_routing_contract(
    preg_match('/^tickets\+tk-123-[a-f0-9]{14}@foxdesk\.net$/', $ticket_reply) === 1,
    'Ticket reply address must be a signed plus-address: ' . $ticket_reply
);
$ticket_route = foxdesk_parse_ticket_email_address($ticket_reply);
assert_email_routing_contract(($ticket_route['kind'] ?? '') === 'ticket', 'Ticket reply route must identify ticket kind.');
assert_email_routing_contract((int) ($ticket_route['ticket_id'] ?? 0) === 123, 'Ticket reply route must identify ticket id.');

echo "Email routing plus-address contract tests passed\n";
