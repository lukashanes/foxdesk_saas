<?php
define('BASE_PATH', dirname(__DIR__));

function assert_cloudflare_route_contract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$provisioning = file_get_contents(BASE_PATH . '/includes/cloudflare-email-route-provisioning.php');
$tenant_functions = file_get_contents(BASE_PATH . '/includes/tenant-functions.php');
$sync_command = file_get_contents(BASE_PATH . '/bin/sync-cloudflare-email-routes.php');
$router_docs = file_get_contents(BASE_PATH . '/cloudflare/email-router/README.md');
$cloudflare_docs = file_get_contents(BASE_PATH . '/docs/CLOUDFLARE_EMAIL.md');
$package_json = file_get_contents(BASE_PATH . '/package.json');

assert_cloudflare_route_contract(str_contains($provisioning, 'function cloudflare_email_routing_provision_workspace_alias'), 'Workspace alias provisioning helper is missing.');
assert_cloudflare_route_contract(str_contains($provisioning, 'function cloudflare_email_routing_sync_workspace_aliases'), 'Workspace alias sync helper is missing.');
assert_cloudflare_route_contract(str_contains($provisioning, 'CLOUDFLARE_EMAIL_ROUTING_API_TOKEN'), 'Provisioning must support a dedicated Cloudflare Email Routing API token.');
assert_cloudflare_route_contract(str_contains($provisioning, 'FOXDESK_EMAIL_ROUTE_PROVISIONING_ENABLED'), 'Provisioning must be runtime-gated.');
assert_cloudflare_route_contract(str_contains($provisioning, "'type' => 'worker'"), 'Provisioning must create Worker actions.');
assert_cloudflare_route_contract(str_contains($provisioning, "'field' => 'to'"), 'Provisioning must match the recipient address.');
assert_cloudflare_route_contract(str_contains($provisioning, 'cloudflare_email_route_find_rule'), 'Provisioning must be idempotent and check existing rules.');
assert_cloudflare_route_contract(str_contains($provisioning, 'result_info'), 'Provisioning must paginate Cloudflare Email Routing rules.');

assert_cloudflare_route_contract(str_contains($tenant_functions, "require_once __DIR__ . '/cloudflare-email-route-provisioning.php'"), 'Tenant provisioning must load Cloudflare route provisioning.');
assert_cloudflare_route_contract(str_contains($tenant_functions, 'cloudflare_email_routing_provision_workspace_alias'), 'New workspace creation must attempt to provision the friendly email route.');
assert_cloudflare_route_contract(str_contains($tenant_functions, 'email_route'), 'New workspace creation must return email route provisioning status.');
assert_cloudflare_route_contract(str_contains($tenant_functions, 'error_log(\'FoxDesk workspace email route provisioning'), 'Provisioning failures must be logged without breaking workspace creation.');

assert_cloudflare_route_contract(str_contains($sync_command, 'cloudflare_email_routing_sync_workspace_aliases'), 'Sync CLI must call the shared sync helper.');
assert_cloudflare_route_contract(str_contains($sync_command, '--dry-run'), 'Sync CLI must support dry-run mode.');
assert_cloudflare_route_contract(str_contains($sync_command, '--tenant-id'), 'Sync CLI must support a single tenant run.');

assert_cloudflare_route_contract(str_contains($package_json, 'email:routes:sync'), 'package.json must expose email route sync.');
assert_cloudflare_route_contract(str_contains($package_json, 'email:routes:dry-run'), 'package.json must expose email route dry-run.');

foreach ([$router_docs, $cloudflare_docs] as $docs) {
    assert_cloudflare_route_contract(str_contains($docs, 'catch-all'), 'Docs must explain the catch-all limitation.');
    assert_cloudflare_route_contract(str_contains($docs, 'CLOUDFLARE_EMAIL_ROUTING_API_TOKEN'), 'Docs must explain the API token required for automatic provisioning.');
    assert_cloudflare_route_contract(str_contains($docs, 'sync-cloudflare-email-routes.php'), 'Docs must include the route sync command.');
}

echo "Cloudflare email route provisioning contract OK\n";
