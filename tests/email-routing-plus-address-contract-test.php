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

assert_email_routing_contract(strpos($helper, 'function foxdesk_ticket_email_local_part') !== false, 'Ticket email local-part helper is missing.');
assert_email_routing_contract(strpos($helper, 'FOXDESK_TICKET_EMAIL_LOCAL_PART') !== false, 'Ticket email local-part config is not used.');
assert_email_routing_contract(strpos($helper, "return \$base_local . '+' . \$route_local . '@'") !== false, 'Ticket email helper must generate plus-addressed routes when a base local part is configured.');
assert_email_routing_contract(strpos($helper, 'str_starts_with($local, $base_local . \'+\')') !== false, 'Ticket email parser must accept plus-addressed routes.');
assert_email_routing_contract(strpos($config, 'FOXDESK_TICKET_EMAIL_LOCAL_PART') !== false, 'Production config example is missing ticket local-part config.');
assert_email_routing_contract(strpos($docs, 'tickets+tk-123-<token>@foxdesk.net') !== false, 'Cloudflare email docs must show the plus-addressed ticket reply format.');
assert_email_routing_contract(strpos($docs, 'test-cloudflare-inbound-archive.php') !== false, 'Cloudflare email docs must include the inbound archive smoke.');
assert_email_routing_contract(strpos($worker, 'raw_archive_verified') !== false, 'Worker must mark raw email archive verification.');
assert_email_routing_contract(strpos($worker, 'archive_verified') !== false, 'Worker must mark attachment archive verification.');
assert_email_routing_contract(strpos($handler, 'api_cloudflare_email_verify_archive_payload') !== false, 'Backend must reject unverified email archive payloads.');
assert_email_routing_contract(strpos($handler, 'Email attachment archive was not verified') !== false, 'Backend must reject unverified archived attachment metadata.');
assert_email_routing_contract(strpos($archive_smoke, 'foxdesk-email-archive/') !== false, 'Inbound archive smoke must verify the Worker archive bucket.');
assert_email_routing_contract(strpos($archive_smoke, "'attachments' => [[") !== false, 'Inbound archive smoke must send a real attachment.');

echo "Email routing plus-address contract tests passed\n";
