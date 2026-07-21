<?php

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/includes/modules/agent/operating-instructions.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$requiredSourceKeys = [
    'Agent instructions: FoxDesk tickets',
    'Use only the FoxDesk Agent API. Never use a web browser.',
    'At the start of every session, call agent-docs and then verify your identity with agent-me.',
    'Before changing an existing ticket, always read its current state with agent-get-ticket.',
    'Every POST request must include a unique Idempotency-Key.',
    'Use agent-add-update for a comment without tracked time.',
    'Use agent-add-work-entry when the work must count toward tracked or billable time.',
    'Each tracked work comment has a matching time entry with a non-null comment_id.',
    'total_time_minutes matches the sum of saved time entries.',
    'Cancel an incorrect ticket only after the correct replacement has been created and verified.',
];

foreach (foxdesk_agent_instruction_languages() as $language) {
    $catalog = require BASE_PATH . '/includes/lang/' . $language . '.php';
    foreach ($requiredSourceKeys as $key) {
        $assert(isset($catalog[$key]) && trim((string) $catalog[$key]) !== '', "{$language} is missing agent instruction: {$key}");
    }

    $instructions = foxdesk_agent_operating_instructions($language, ['language' => $language]);
    $assert($instructions['language'] === $language, "{$language} instructions use the wrong language.");
    $assert($instructions['machine_rules']['required_first_actions'] === ['agent-docs', 'agent-me'], 'Agent session bootstrap is incorrect.');
    $assert($instructions['machine_rules']['read_before_write_action'] === 'agent-get-ticket', 'Read-before-write action is incorrect.');
    $assert($instructions['machine_rules']['post_requires_unique_idempotency_key'] === true, 'POST idempotency is not mandatory.');
    $assert($instructions['schema_version'] === 2, 'Agent instruction schema is stale.');
    $assert($instructions['machine_rules']['comment_only_action'] === 'agent-add-update', 'Comment-only action is incorrect.');
    $assert($instructions['machine_rules']['tracked_work_action'] === 'agent-add-work-entry', 'Tracked-work action is incorrect.');
    $assert($instructions['machine_rules']['tracked_work_requires_linked_comment_id'] === true, 'Tracked work does not require a linked comment.');
    $assert($instructions['machine_rules']['expected_total_time_rule'] === 'sum(time_entries.duration_minutes)', 'Tracked time total rule is incorrect.');
    $assert(str_contains($instructions['daily_entries']['example_html'], '<ul>'), 'Daily comment example must preserve HTML list formatting.');

    $markdown = foxdesk_agent_operating_instructions_markdown($language, ['language' => $language]);
    $assert(str_contains($markdown, 'agent-get-ticket'), "{$language} Markdown omits verification action.");
    $assert(str_contains($markdown, 'Idempotency-Key'), "{$language} Markdown omits idempotency.");
}

$handler = file_get_contents(BASE_PATH . '/includes/api/agent-handler.php');
require_once BASE_PATH . '/tests/support/settings-source.php';
$settings = settings_source_bundle(BASE_PATH);
$connect = file_get_contents(BASE_PATH . '/pages/admin/agent-connect.php');
$workflow = file_get_contents(BASE_PATH . '/docs/AGENT_TICKET_WORKFLOW.md');

$assert(str_contains($handler, "'operating_instructions' => \$operating_instructions"), 'agent-docs does not expose structured operating instructions.');
$assert(str_contains($handler, "'operating_instructions_markdown'"), 'agent-docs does not expose readable Markdown instructions.');
$assert(str_contains($handler, "\$_GET['instruction_language']"), 'agent-docs does not accept an explicit instruction language.');
$assert(str_contains($handler, "'action' => 'agent-add-update'"), 'Comment-only action is not documented.');
$assert(str_contains($handler, "'required_scopes' => ['tickets:read', 'comments:write']"), 'Comment availability does not evaluate all required scopes.');
$assert(str_contains($handler, "'required_scopes' => ['tickets:read', 'comments:write', 'time:write']"), 'Comment-with-time availability does not evaluate all required scopes.');
$assert(str_contains($handler, "'action' => 'agent-delete-ticket-permanently'"), 'Permanent-delete action is missing from live docs.');
$assert(str_contains($settings, 'foxdesk_agent_operating_instructions_markdown'), 'Settings does not render the canonical instructions.');
$assert(str_contains($connect, '$canonical_agent_instructions'), 'Agent Connect does not reuse the canonical instructions.');
$assert(str_contains($workflow, 'non-null `comment_id`'), 'Canonical workflow does not require linked tracked work.');
$assert(!str_contains($workflow, '`total_time_minutes` is `0`'), 'Canonical workflow still requires zero tracked time.');

echo "Agent operating instructions contract OK\n";
