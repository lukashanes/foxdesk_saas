<?php
$root = dirname(__DIR__);
require_once __DIR__ . '/support/ticket-detail-source.php';

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }
    return $content;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$functions = $read('includes/functions.php');
$crud = $read('includes/ticket-crud-functions.php');
$newTicket = new_ticket_composed_source($root);
$composer = $read('includes/components/ticket-detail-composer.php');
$handlers = $read('includes/components/ticket-form-handlers.php');
$agentApi = $read('includes/api/agent-handler.php');
$appApi = $read('includes/api/app-handler.php');

$assert(str_contains($functions, 'function foxdesk_can_backdate_records'), 'Backdated records permission helper is missing.');
$assert(str_contains($functions, "in_array(\$role, ['admin', 'agent'], true)"), 'Only admins and agents should be allowed to backdate records.');
$assert(str_contains($functions, 'function foxdesk_normalize_backdated_datetime_input'), 'Backdated datetime normalization helper is missing.');
$assert(str_contains($functions, '$dt->getTimestamp() > time() + 60'), 'Backdated datetime helper must reject future dates.');

$assert(str_contains($crud, "'allow_backdated_created_at'"), 'create_ticket must require an explicit backdate opt-in.');
$assert(str_contains($crud, "foxdesk_normalize_backdated_datetime_input(\$data['created_at'])"), 'create_ticket must normalize backdated created_at.');
$assert(str_contains($crud, "'updated_at' => \$created_at"), 'Backdated ticket creation must align updated_at with created_at.');
$assert(str_contains($crud, 'function add_comment($ticket_id, $user_id, $content, $is_internal = 0, array $options = [])'), 'add_comment must accept backdate options.');
$assert(str_contains($crud, "array_key_exists('created_at', \$options)"), 'add_comment must support created_at option.');
$assert(str_contains($crud, "foxdesk_normalize_backdated_datetime_input(\$options['created_at'])"), 'add_comment must normalize created_at option.');

$assert(str_contains($newTicket, 'name="created_at"'), 'New ticket form must expose Created at for staff.');
$assert(str_contains($newTicket, 'foxdesk_can_backdate_records($user)'), 'New ticket submission must permission-check backdating.');
$assert(str_contains($newTicket, "foxdesk_normalize_backdated_datetime_input(\$created_at_input)"), 'New ticket submission must normalize created_at.');
$assert(str_contains($newTicket, "\$create_data['allow_backdated_created_at'] = true"), 'New ticket submission must explicitly allow backdated create_ticket.');
$assert(str_contains($newTicket, "\$end_dt = \$created_at !== null ? new DateTime(\$created_at) : new DateTime()"), 'Quick time on new tickets should end at the backdated created_at.');

$assert(str_contains($composer, 'name="comment_created_at"'), 'Ticket composer must expose Created at for staff comments.');
$assert(str_contains($handlers, "\$comment_created_at_input"), 'Comment handler must read comment_created_at.');
$assert(str_contains($handlers, 'foxdesk_can_backdate_records($user)'), 'Comment handler must permission-check backdating.');
$assert(str_contains($handlers, "foxdesk_normalize_backdated_datetime_input(\$comment_created_at_input)"), 'Comment handler must normalize comment_created_at.');
$assert(str_contains($handlers, "'created_at' => \$comment_created_at"), 'Comment insert must use comment_created_at.');
$assert(str_contains($handlers, "db_update('tickets', ['updated_at' => \$comment_created_at]"), 'Backdated comments must align ticket updated_at.');
$assert(str_contains($handlers, "new DateTime(\$comment_created_at)"), 'Quick time on comments should end at the backdated comment time.');

$assert(str_contains($agentApi, "array_key_exists('created_at', \$input)"), 'Agent API must accept optional created_at.');
$assert(str_contains($agentApi, "foxdesk_normalize_backdated_datetime_input(\$input['created_at'])"), 'Agent API must normalize created_at.');
$assert(str_contains($agentApi, "\$data['allow_backdated_created_at'] = true"), 'Agent API ticket creation must explicitly opt in to backdating.');
$assert(str_contains($agentApi, "add_comment(\$ticket_id, \$user['id'], \$input['content'], \$is_internal, ["), 'Agent API comments must pass created_at into add_comment.');
$assert(str_contains($agentApi, "'created_at' => \$comment_created_at"), 'Agent API comments must use comment_created_at.');
$assert(str_contains($agentApi, "'created_at' => 'optional historical datetime, admin/agent only'"), 'Agent docs must document created_at.');

$assert(str_contains($appApi, "array_key_exists('created_at', \$input)"), 'App API must accept optional created_at.');
$assert(str_contains($appApi, "foxdesk_can_backdate_records(\$user)"), 'App API must permission-check backdating.');
$assert(str_contains($appApi, "foxdesk_normalize_backdated_datetime_input(\$input['created_at'])"), 'App API must normalize created_at.');
$assert(str_contains($appApi, "\$data['allow_backdated_created_at'] = true"), 'App API ticket creation must explicitly opt in to backdating.');
$assert(str_contains($appApi, "add_comment(\$ticket_id, (int) \$user['id'], \$content, \$is_internal ? 1 : 0, ["), 'App API comments must pass created_at into add_comment.');

foreach (['en', 'cs', 'de', 'es', 'it'] as $lang) {
    $langFile = $read("includes/lang/{$lang}.php");
    foreach ([
        'Created at',
        'Leave empty to use now.',
        'Only admins and agents can set historical dates.',
        'Invalid created date.',
    ] as $key) {
        $assert(str_contains($langFile, "'{$key}' =>"), "Missing {$key} translation in {$lang}.");
    }
}

echo "Backdated records contract tests passed\n";
