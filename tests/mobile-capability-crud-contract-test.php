<?php

$root = dirname(__DIR__);
$handler = file_get_contents($root . '/includes/api/app-handler.php');
$router = file_get_contents($root . '/includes/api/router.php');
$mobileRouter = file_get_contents($root . '/includes/api/mobile-v1-router.php');
$auth = file_get_contents($root . '/includes/auth.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

foreach ([
    'api_app_update_comment',
    'api_app_update_time_entry',
    'api_app_delete_attachment',
    'api_app_restore_attachment',
] as $function) {
    $assert(str_contains($handler, 'function ' . $function . '('), $function . ' is missing.');
}

foreach ([
    'app-update-comment',
    'app-update-time-entry',
    'app-delete-attachment',
    'app-restore-attachment',
] as $action) {
    $assert(str_contains($router, "'{$action}'"), $action . ' dispatcher route is missing.');
    $assert(str_contains($auth, "'{$action}'"), $action . ' scope mapping is missing.');
    $assert(str_contains($mobileRouter, "'{$action}'"), $action . ' mobile route is missing.');
}

$assert(str_contains($handler, "array_key_exists('is_archived', \$input)"), 'Mobile ticket update must support archive and restore.');
$assert(str_contains($handler, 'can_archive_tickets($user)'), 'Archive and restore must enforce the archive permission.');
$assert(str_contains($handler, 'ticket_transition_status('), 'Mobile status changes must use the atomic transition helper.');
$assert(str_contains($handler, "'timer_stopped'"), 'Mobile status response must disclose timer stop state.');
$assert(str_contains($handler, "api_app_require_api_token_scope('delete:write')"), 'Destructive app operations must require delete:write.');
$assert(str_contains($handler, "ticket_undo_stage('attachment'"), 'Attachment deletion must support delayed finalization and Undo.');

echo "Mobile capability CRUD contract OK\n";
