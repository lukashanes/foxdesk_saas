<?php

$root = dirname(__DIR__);
$router = file_get_contents($root . '/includes/api/router.php');
$appHandler = file_get_contents($root . '/includes/api/app-handler.php');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($router, 'function api_enforce_workspace_access(string $action): void'), 'API router must define a workspace access guard.');
$assert(str_contains($router, 'billing_workspace_access_state($tenant)'), 'Workspace access guard must use the canonical billing lifecycle decision.');
$assert(str_contains($router, "api_error('Workspace access is paused. Ask a workspace admin to restore access.', 402)"), 'Paused workspaces must receive a stable API error.');
$assert(str_contains($router, 'api_enforce_workspace_access((string) $action)'), 'Authenticated API requests must invoke the workspace access guard.');
$guardPosition = strpos($router, 'api_enforce_workspace_access((string) $action)');
$idempotencyPosition = strpos($router, 'api_idempotency_replay_if_available($action)');
$assert(
    $guardPosition !== false && $idempotencyPosition !== false && $guardPosition < $idempotencyPosition,
    'Workspace access must be checked before an idempotency key can be reserved or replayed.'
);
$assert(str_contains($router, "'app-tenant-state'"), 'Tenant state must remain available so native clients can explain the pause.');
$assert(str_contains($router, "'mobile-me'"), 'Mobile identity must remain available for account and sign-out flows.');
$assert(str_contains($router, "'agent-docs'"), 'Static agent documentation must remain available to authenticated agents.');
$assert(str_contains($router, 'is_platform_admin($user)'), 'Platform operators must not be blocked by workspace billing state.');
$assert(str_contains($appHandler, "'access' => \$access_state"), 'Tenant state response must expose the access decision to native clients.');

if ($failures) {
    fwrite(STDERR, "API workspace access contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "API workspace access contract OK\n";
