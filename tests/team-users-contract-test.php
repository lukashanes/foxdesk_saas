<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/settings-source.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$page = file_get_contents($root . '/pages/admin/users.php');
$aiAgentsComponent = file_get_contents($root . '/includes/components/team-ai-agents-tab.php');
$usersComponent = file_get_contents($root . '/includes/components/team-users-tab.php');
$agentConnectPage = file_get_contents($root . '/pages/admin/agent-connect.php');
$profilePage = file_get_contents($root . '/pages/profile.php');
$settingsPage = settings_source_bundle($root);
$bootstrap = file_get_contents($root . '/includes/modules/bootstrap.php');
$module = file_get_contents($root . '/includes/modules/team/team-users.php');
$schemaMigration = file_get_contents($root . '/migrations/2026072003_runtime_feature_columns.php');

$assert($page !== false && $aiAgentsComponent !== false && $usersComponent !== false && $agentConnectPage !== false && $profilePage !== false && $settingsPage !== false && $bootstrap !== false && $module !== false && $schemaMigration !== false, 'Team users contract files must be readable.');
$assert(str_contains($bootstrap, '/team/team-users.php'), 'Module bootstrap must load team users helpers.');
$teamUiSurface = $page . "\n" . $aiAgentsComponent . "\n" . $usersComponent;

foreach ([
    'team_users_table_capabilities()',
    'team_users_filter_state($_GET)',
    'team_users_valid_organization_ids($organizations)',
    'team_users_normalize_organization_assignment(',
    'team_users_permission_payload(',
    'team_users_fetch($filter_state, $user_table_capabilities)',
    'team_users_time_totals($range_start, $range_end)',
    'team_ai_agents_fetch($deleted_at_column_exists)',
    'team_ai_agent_tokens_fetch($ai_agents)',
] as $needle) {
    $assert(str_contains($page, $needle), 'Users page must delegate team behavior: ' . $needle);
}

foreach ([
    'id="aiAddAgentForm"',
    'id="editAiAgentForm"',
    'name="ticket_scope"',
    'name="scope_organization_ids[]"',
    'name="api_permission_preset"',
    'ai-token-permission-preset',
    'save_and_generate_agent_token',
    "'permissions' => \$permissions_data !== null ? json_encode(\$permissions_data) : null",
    "'organization_id' => \$organization_id",
    'team_ai_agent_token_scopes_from_input(',
    'team_ai_agent_revoke_active_tokens(',
    'setAiAgentAccess(agent)',
    'setAiAgentTokenScopeGroups(token)',
    'bindAiAgentScope(',
    'data-ai-agent-create',
    'data-ai-agent-key-ready',
    'data-ai-agent-key-copy',
    'copyGeneratedAgentKey(',
    'Create AI agent access',
    'Create agent and key',
] as $needle) {
    $assert(str_contains($teamUiSurface, $needle), 'AI agent access management must stay in the AI agents UI: ' . $needle);
}

$assert(!str_contains($page, 'data-admin-api-access-card'), 'Users page must not duplicate API access management.');
$assert(!str_contains($teamUiSurface, "url('profile'); ?>#api-access"), 'AI agent UI must not send admins to profile API access.');
$assert(!str_contains($teamUiSurface, 'Connect Codex, Claude, or another assistant'), 'AI agent UI must not keep the old duplicate intro card.');
$assert(str_contains($profilePage, 'id="api-access"'), 'Profile page must keep the legacy API access anchor.');
$assert(str_contains($profilePage, 'Open API & agents'), 'Profile API anchor must point users to Settings API & agents.');

foreach ([
    "'api' => 'api.php'",
    'data-settings-api-access',
    'data-api-token-create-form',
    'data-api-tester',
    'data-api-test-response',
    'team_ai_agents_fetch(!empty($api_user_table_capabilities',
    "url('admin', ['section' => 'users', 'tab' => 'ai_agents'])",
] as $needle) {
    $assert(str_contains($settingsPage, $needle), 'Settings API & agents section missing: ' . $needle);
}

foreach ([
    'team_ai_agent_token_scopes_from_input($_POST)',
    'SELECT id, token_prefix, scopes_json, is_active, created_at, last_used_at FROM api_tokens',
    '$ai_agent_permission_presets',
    'name="api_permission_preset"',
    'Choose the broad access level first. All is the only level that can delete records.',
] as $needle) {
    $assert(str_contains($agentConnectPage, $needle), 'Agent Connect token generation must expose permission presets: ' . $needle);
}

$assert(!str_contains($agentConnectPage, 'name="api_token_scope_groups[]"'), 'Agent Connect must not expose low-level token scope checkbox groups.');

$assert(!str_contains($agentConnectPage, 'team_ai_agent_token_scopes_from_input([])'), 'Agent Connect must not generate AI tokens with hardcoded default scopes.');

foreach ([
    'SELECT u.*, o.name as organization_name',
    'SELECT user_id, SUM({$dur})',
    'SELECT tte.user_id, SUM({$dur})',
    'SELECT * FROM api_tokens WHERE tenant_id = ?',
    '$filter_tenant_organization_ids',
    '$scope_organization_ids = array_map',
    '$scope_organization_ids = $filter_tenant_organization_ids',
    '$effective_organization_ids = normalize_organization_ids(array_merge(',
] as $needle) {
    $assert(!str_contains($page, $needle), 'Users page must not own extracted team logic: ' . $needle);
}

foreach ([
    'function team_users_table_capabilities',
    'function team_users_ensure_ai_agent_schema',
    'function team_users_schema_column_exists',
    'function team_users_filter_state',
    'function team_users_tenant_filter',
    'function team_users_normalize_organization_assignment',
    'function team_users_permission_payload',
    'function team_users_fetch',
    'function team_users_time_totals',
    'function team_ai_agents_fetch',
    'function team_ai_agent_tokens_fetch',
    'function team_ai_agent_token_scope_groups',
    'function team_ai_agent_token_default_scope_groups',
    'function team_ai_agent_token_scopes_from_input',
    'function team_ai_agent_revoke_active_tokens',
    'tenant_sql_filter($table, $alias, $params)',
] as $needle) {
    $assert(str_contains($module, $needle), 'Team users module missing: ' . $needle);
}

foreach ([
    'team_users_schema_column_exists(\'users\', \'is_ai_agent\')',
] as $needle) {
    $assert(str_contains($module, $needle), 'Team users capability check missing: ' . $needle);
}
foreach (['CREATE TABLE', 'ALTER TABLE'] as $runtimeDdl) {
    $assert(!str_contains($module, $runtimeDdl), 'Team users request module must not mutate schema: ' . $runtimeDdl);
}
foreach ([
    "'is_ai_agent' => 'TINYINT(1) NOT NULL DEFAULT 0'",
    "'ai_model' => 'VARCHAR(100) NULL DEFAULT NULL'",
] as $needle) {
    $assert(str_contains($schemaMigration, $needle), 'Team users versioned migration missing: ' . $needle);
}

foreach ([
    "'tickets_read'",
    "'tickets_write'",
    "'comments_write'",
    "'time_read'",
    "'time_write'",
    "'attachments_read'",
    "'attachments_write'",
    "'reports_read'",
    "'reports_write'",
    "'notifications_read'",
    "'notifications_write'",
] as $needle) {
    $assert(str_contains($module, $needle), 'AI agent token scopes must expose granular read/write controls: ' . $needle);
}

$assert(!str_contains($module, "'ticket_work'"), 'AI agent token scopes must not combine ticket read/write/comment permissions into one group.');
$assert(!str_contains($module, "'time_tracking'"), 'AI agent token scopes must not combine time read/write permissions into one group.');
$assert(!str_contains($module, "'attachments' => ["), 'AI agent token scopes must not combine attachment read/write permissions into one group.');

echo "Team users contract OK\n";
