<?php

$root = dirname(__DIR__);

$files = [
    'router' => $root . '/includes/api/router.php',
    'auth' => $root . '/includes/auth.php',
    'app' => $root . '/includes/api/app-handler.php',
    'agent' => $root . '/includes/api/agent-handler.php',
    'comments' => $root . '/includes/ticket-crud-functions.php',
    'time' => $root . '/includes/ticket-time-functions.php',
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
    "'app-add-comment' => 'api_app_add_comment'",
    "'app-add-comment-with-time' => 'api_app_add_comment_with_time'",
    "'app-delete-comment' => 'api_app_delete_comment'",
    "'app-delete-time-entry' => 'api_app_delete_time_entry'",
] as $needle) {
    $assert(str_contains($contents['router'], $needle), 'Missing API route: ' . $needle);
}

foreach ([
    "'app-add-comment' => 'comments:write'",
    "'app-add-comment-with-time' => 'comments:write'",
    "'app-delete-comment' => 'comments:write'",
    "'app-delete-time-entry' => 'time:write'",
] as $needle) {
    $assert(str_contains($contents['auth'], $needle), 'Missing API token action scope: ' . $needle);
}

foreach ([
    'function api_app_require_api_token_scope',
    "api_app_require_api_token_scope('tickets:read')",
    "api_app_require_api_token_scope('comments:write')",
    "api_app_require_api_token_scope('time:write')",
    'function api_app_comment_time_requested',
    'function api_app_resolve_comment_time_input',
    "'manual_date'",
    "'manual_start_time'",
    "'manual_end_time'",
    "'started_at'",
    "'ended_at'",
    "'is_billable'",
] as $needle) {
    $assert(str_contains($contents['app'], $needle), 'Comment-with-time input/scope handling missing: ' . $needle);
}

foreach ([
    'function api_app_add_comment()',
    "'time_spent' => (int) (\$time_payload['duration_minutes'] ?? 0)",
    "'comment_id' => (int) \$comment_id",
    "'started_at' => \$time_payload['started_at']",
    "'ended_at' => \$time_payload['ended_at']",
    "'duration_minutes' => (int) \$time_payload['duration_minutes']",
    "log_activity(\$ticket_id, (int) \$user['id'], 'commented'",
    "log_activity(\$ticket_id, (int) \$user['id'], 'time_manual'",
    "\$response['time_entry_id'] = (int) \$time_entry_id",
    "\$response['duration_minutes'] = (int) \$time_payload['duration_minutes']",
    "\$response['started_at'] = (string) \$time_payload['started_at']",
    "\$response['ended_at'] = (string) \$time_payload['ended_at']",
    "api_app_bool(\$input, 'skip_notification', false)",
] as $needle) {
    $assert(str_contains($contents['app'], $needle), 'app-add-comment must create a linked comment/time record: ' . $needle);
}

foreach ([
    'function api_app_add_comment_with_time()',
    "\$GLOBALS['api_app_comment_time_required'] = true",
    "api_error('Time fields are required for this endpoint.'",
] as $needle) {
    $assert(str_contains($contents['app'], $needle), 'app-add-comment-with-time must require time fields: ' . $needle);
}

foreach ([
    'function api_app_delete_comment()',
    'can_manage_comment($comment, $user)',
    "db_delete('comments', 'id = ?', [\$comment_id])",
    'function api_app_delete_time_entry()',
    'can_manage_time_entry($entry, $user)',
    'delete_time_entry($entry_id)',
] as $needle) {
    $assert(str_contains($contents['app'], $needle), 'Delete endpoint contract missing: ' . $needle);
}

foreach ([
    "'time_spent' => max(0, (int) (\$options['time_spent'] ?? 0))",
    "'comment_id' => !empty(\$data['comment_id']) ? (int) \$data['comment_id'] : null",
] as $needle) {
    $source = str_contains($needle, 'time_spent') ? 'comments' : 'time';
    $assert(str_contains($contents[$source], $needle), 'Storage layer missing linked comment-time support: ' . $needle);
}

foreach ([
    "'action' => 'app-add-comment-with-time'",
    "'scope' => 'tickets:read + comments:write + time:write'",
    'comment_with_time',
    'Idempotency-Key',
    'creates linked ticket_time_entries.comment_id',
] as $needle) {
    $assert(str_contains($contents['agent'], $needle), 'Agent docs missing comment-with-time guidance: ' . $needle);
}

foreach ([
    'function api_agent_add_comment()',
    'api_app_resolve_comment_time_input($input, $user, $comment_created_at)',
    "'comment_id' => (int) \$comment_id",
    "\$response['time_entry_id'] = (int) \$time_entry_id",
] as $needle) {
    $assert(str_contains($contents['agent'], $needle), 'Agent add-comment endpoint must support linked time records: ' . $needle);
}

$assert(str_contains($contents['auth'], 'function api_idempotency_replay_if_available'), 'API idempotency replay helper is required.');
$assert(str_contains($contents['auth'], 'function api_idempotency_store_success'), 'API idempotency success helper is required.');
$assert(str_contains($contents['router'], 'api_idempotency_replay_if_available($action)'), 'Router must replay idempotent API token requests before dispatch.');

echo "App comment-with-time contract OK\n";
