<?php

$root = dirname(__DIR__);

$files = [
    'schema' => $root . '/includes/schema.sql',
    'database' => $root . '/includes/database.php',
    'auth' => $root . '/includes/auth.php',
    'router' => $root . '/includes/api/router.php',
    'agent' => $root . '/includes/api/agent-handler.php',
    'app' => $root . '/includes/api/app-handler.php',
    'upload' => $root . '/includes/api/upload-handler.php',
    'profile' => $root . '/pages/profile.php',
    'settings' => $root . '/pages/admin/settings.php',
    'teamComponent' => $root . '/includes/components/team-ai-agents-tab.php',
    'docs' => $root . '/docs/AGENT_API_CONTROL.md',
];

$contents = [];
foreach ($files as $key => $path) {
    $contents[$key] = file_get_contents($path);
    if ($contents[$key] === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

foreach ([
    'scopes_json TEXT NULL',
    'revoked_at DATETIME NULL',
    'last_used_ip VARCHAR(45) NULL',
    'CREATE TABLE IF NOT EXISTS api_token_audit_logs',
    'CREATE TABLE IF NOT EXISTS api_idempotency_keys',
    'UNIQUE KEY uniq_api_idempotency_token_key',
] as $needle) {
    $assert(str_contains($contents['schema'], $needle), 'Agent API schema missing: ' . $needle);
}

foreach ([
    'function column_exists_uncached',
    'function index_exists',
] as $needle) {
    $assert(str_contains($contents['database'], $needle), 'Database schema helper missing: ' . $needle);
}

foreach ([
    'function ensure_api_token_schema',
    'CREATE TABLE api_tokens',
    'ALTER TABLE api_tokens ADD COLUMN tenant_id',
    'ALTER TABLE api_tokens ADD COLUMN scopes_json',
    'ALTER TABLE api_tokens ADD COLUMN revoked_at',
    'ALTER TABLE api_tokens ADD COLUMN last_used_ip',
    'ALTER TABLE api_tokens ADD COLUMN last_used_user_agent',
    "ALTER TABLE api_tokens ADD INDEX idx_tenant_id",
    "column_exists_uncached('api_tokens', 'scopes_json')",
    "column_exists_uncached('api_tokens', 'tenant_id')",
    'WHERE expires_at IS NULL',
] as $needle) {
    $assert(str_contains($contents['auth'], $needle), 'API token schema self-healing missing: ' . $needle);
}

foreach ([
    'function api_token_scope_catalog',
    'function api_token_allowed_scopes_for_user',
    'function api_token_normalize_scopes',
    'function api_token_has_scope',
    'function api_token_required_scope_for_action',
    'function api_token_enforce_action_scope',
    'function api_token_rate_limit_check',
    'function api_token_log_action',
    'function api_idempotency_replay_if_available',
    'function api_idempotency_store_success',
    '$GLOBALS[\'api_token_row\']',
    '$_SESSION[\'api_token_id\']',
    'scopes_json',
    'revoked_at IS NULL',
    '$scopes = null',
] as $needle) {
    $assert(str_contains($contents['auth'], $needle), 'Agent API auth missing: ' . $needle);
}

foreach ([
    'str_starts_with($action, \'app-\')',
    'api_token_enforce_action_scope($action)',
    'api_token_rate_limit_check($action)',
    'api_idempotency_replay_if_available($action)',
    "'agent-docs' => 'api_agent_docs'",
    "'app-create-ticket' => 'api_app_create_ticket'",
    "'app-log-time' => 'api_app_log_time'",
] as $needle) {
    $assert(str_contains($contents['router'], $needle), 'Agent API router missing: ' . $needle);
}

foreach ([
    'function api_agent_docs',
    'function api_agent_docs_tools',
    'function api_agent_docs_scope_allowed',
    "'action' => 'agent-docs'",
    "'available' => \$available",
    "'missing_scope' => \$available ? null : \$scope",
    'Authorization: Bearer $FOXDESK_API_TOKEN',
    'Use this endpoint at the start of every agent session',
    'Idempotency-Key',
] as $needle) {
    $assert(str_contains($contents['agent'], $needle), 'Agent live docs endpoint missing: ' . $needle);
}

foreach ([
    "'agent-docs' => null",
    "\$action === 'agent-docs'",
] as $needle) {
    $assert(str_contains($contents['auth'], $needle), 'Agent docs must be available to every valid API token: ' . $needle);
}

foreach ([
    'empty($GLOBALS[\'is_api_token_auth\'])',
    'function api_app_create_ticket',
    'function api_app_log_time',
    'api_app_contract_success',
    'can_user_create_ticket_for',
    'add_manual_time_entry',
] as $needle) {
    $assert(str_contains($contents['app'], $needle), 'Agent API app handler missing: ' . $needle);
}

$assert(str_contains($contents['upload'], "empty(\$GLOBALS['is_api_token_auth'])"), 'Upload endpoint must allow scoped API-token writes without browser CSRF.');
$assert(str_contains($contents['settings'], 'create_api_token'), 'Settings API & agents must allow admins to create scoped API keys.');
$assert(str_contains($contents['settings'], 'revoke_api_token'), 'Settings API & agents must allow admins to revoke scoped API keys.');
$assert(str_contains($contents['settings'], 'api_token_scope_catalog'), 'Settings API & agents must render the shared scope catalog.');
$assert(str_contains($contents['settings'], 'data-agent-docs-instructions'), 'Settings API & agents must explain the live agent-docs self-check.');
$assert(str_contains($contents['settings'], 'data-api-access-builder'), 'Settings API & agents must expose one unified access builder.');
$assert(str_contains($contents['settings'], 'data-api-access-panel="user"'), 'Settings API & agents must keep normal API keys in the unified builder.');
$assert(str_contains($contents['settings'], 'data-api-access-panel="agent"'), 'Settings API & agents must keep AI worker keys in the unified builder.');
$assert(str_contains($contents['settings'], 'data-api-permission-presets'), 'Settings API & agents must expose permission presets before key creation.');
$assert(str_contains($contents['settings'], "'read_only' =>"), 'Settings API & agents must offer a Read only preset.');
$assert(str_contains($contents['settings'], "'read_write' =>"), 'Settings API & agents must offer a Read & write preset.');
$assert(str_contains($contents['settings'], "'all' =>"), 'Settings API & agents must offer an All preset.');
$assert(!str_contains($contents['settings'], 'value="never"'), 'Settings API keys must not offer never-expiring tokens.');
$assert(str_contains($contents['auth'], "\$expires_at = date('Y-m-d H:i:s', time() + (90 * 86400));"), 'API tokens must always receive a default expiration when callers omit one.');
$assert(str_contains($contents['teamComponent'], 'ai-token-permission-preset'), 'AI agent edit flow must use simple permission presets.');
$assert(!str_contains($contents['teamComponent'], 'name="api_token_scope_groups[]"'), 'AI agent UI must not expose low-level scope checkbox groups.');
$assert(str_contains($contents['settings'], '$ai_agent_hide_create_form = true'), 'Settings API & agents must hide the legacy duplicate AI agent create form.');
$assert(!str_contains($contents['settings'], "t('Create API access')"), 'Settings API & agents must not show a second Create API access path.');
$assert(!str_contains($contents['settings'], "t('Create AI agent access')"), 'Settings API & agents must not show a second Create AI agent access path.');
$assert(str_contains($contents['profile'], 'Open API & agents'), 'Profile must link to Settings API & agents.');
$assert(!str_contains($contents['profile'], 'data-api-token-create-form'), 'Profile must not remain the API token management surface.');
$assert(str_contains($contents['docs'], 'idempotency'), 'Agent API docs must describe idempotency.');
$assert(str_contains($contents['docs'], 'inherits the creator'), 'Agent API docs must describe permission inheritance.');

echo "Agent API control contract OK\n";
