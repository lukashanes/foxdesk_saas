<?php
$root = dirname(__DIR__);

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

$router = $read('includes/api/router.php');
$handler = $read('includes/api/ticket-handler.php');
$userFunctions = $read('includes/user-functions.php');
$footer = $read('assets/js/app-footer.js');
$detail = $read('assets/js/ticket-detail.js');
$page = $read('pages/ticket-detail.php');

$assert(str_contains($router, "'restore-time-entry' => 'api_restore_time_entry'"), 'Restore time endpoint must be routed.');
$assert(str_contains($router, "'restore-comment' => 'api_restore_comment'"), 'Restore comment endpoint must be routed.');

$assert(str_contains($handler, 'function api_ticket_store_undo_action'), 'Undo token storage helper is missing.');
$assert(str_contains($handler, 'function api_ticket_consume_undo_action'), 'Undo token consume helper is missing.');
$assert(str_contains($handler, "'expires_at' => time() + 300"), 'Undo actions must expire quickly.');
$assert(str_contains($handler, "'undo_token' => \$undo_token"), 'Delete endpoints must return undo tokens.');
$assert(str_contains($handler, "'undo_action' => 'restore-time-entry'"), 'Time delete must return restore-time-entry action.');
$assert(str_contains($handler, "'undo_action' => 'restore-comment'"), 'Comment delete must return restore-comment action.');
$assert(str_contains($handler, 'function api_restore_time_entry'), 'Restore time handler is missing.');
$assert(str_contains($handler, 'function api_restore_comment'), 'Restore comment handler is missing.');
$assert(str_contains($handler, "db_insert('ticket_time_entries', \$entry)"), 'Restore time must reinsert the deleted time row.');
$assert(str_contains($handler, "db_insert('comments', \$comment)"), 'Restore comment must reinsert the deleted comment row.');
$assert(str_contains($handler, "can_see_ticket(\$ticket, \$user)"), 'Restore handlers must re-check ticket visibility.');
$assert(str_contains($userFunctions, 'function can_manage_comment'), 'Shared comment manage permission helper is missing.');
$assert(str_contains($userFunctions, 'function can_manage_time_entry'), 'Shared time entry manage permission helper is missing.');
$assert(str_contains($handler, 'can_manage_comment($comment, $user)'), 'Comment edit/delete/restore must use shared user-aware permission helper.');
$assert(str_contains($handler, 'can_manage_time_entry($entry, $user)'), 'Time delete/restore must use shared user-aware permission helper.');
$assert(str_contains($handler, "function api_delete_comment()") && str_contains($handler, "if (!can_manage_comment(\$comment, \$user))"), 'Comment delete must be role-neutral and permission-based.');
$assert(str_contains($handler, "function api_restore_comment()") && str_contains($handler, "if (!can_manage_comment(\$comment, \$user))"), 'Comment restore must be role-neutral and permission-based.');
$assert(str_contains($handler, "function api_delete_time_entry()") && str_contains($handler, "if (!can_manage_time_entry(\$entry, \$user))"), 'Time delete must be role-neutral and permission-based.');
$assert(str_contains($handler, "function api_restore_time_entry()") && str_contains($handler, "if (!can_manage_time_entry(\$entry, \$user))"), 'Time restore must be role-neutral and permission-based.');

$assert(str_contains($footer, 'options.actionLabel'), 'Toast component must support action labels.');
$assert(str_contains($footer, 'options.onAction'), 'Toast component must support action callbacks.');
$assert(str_contains($detail, 'showUndoToast'), 'Ticket detail must show Undo toast after deletion.');
$assert(str_contains($detail, 'restoreDeletedItem'), 'Ticket detail must call restore endpoint from Undo.');
$assert(!str_contains($detail, 'confirmDeleteComment'), 'Comment delete must not use confirm().');
$assert(!str_contains($page, 'Delete this time entry?'), 'Time delete must not use confirm().');

foreach (['en', 'cs', 'de', 'es', 'it'] as $lang) {
    $langFile = $read("includes/lang/{$lang}.php");
    foreach ([
        'Undo',
        'Undo is no longer available.',
        'Time entry restored.',
        'Failed to restore time entry.',
        'Comment restored.',
        'Failed to restore comment.',
    ] as $key) {
        $assert(str_contains($langFile, "'{$key}' =>"), "Missing {$key} translation in {$lang}.");
    }
}

echo "Ticket delete undo contract OK\n";
