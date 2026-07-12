<?php

$root = dirname(__DIR__);

$feed = file_get_contents($root . '/includes/modules/app/app-feed.php');
$bootstrap = file_get_contents($root . '/includes/modules/bootstrap.php');
$router = file_get_contents($root . '/includes/api/router.php');
$handler = file_get_contents($root . '/includes/api/app-handler.php');
$docs = file_get_contents($root . '/docs/product-architecture-refactor.md');

if ($feed === false || $bootstrap === false || $router === false || $handler === false || $docs === false) {
    fwrite(STDERR, "Unable to read app home files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($bootstrap, '/app/app-feed.php'), 'Module bootstrap must load app feed.');
$assert(str_contains($router, "'app-home' => 'api_app_home'"), 'app-home route is not registered.');
$assert(str_contains($handler, 'function api_app_home()'), 'api_app_home handler is missing.');
$assert(str_contains($handler, 'app_shell_payload($user)'), 'api_app_home must include app shell.');
$assert(str_contains($handler, 'app_feed_payload($user, $limit, ['), 'api_app_home must delegate to app_feed_payload() with dashboard filters.');
$assert(str_contains($handler, "'period' => (string) (\$_GET['period'] ?? 'last_30_days')"), 'api_app_home must forward the selected dashboard period.');
$assert(str_contains($handler, "'time_scope' => (string) (\$_GET['time_scope'] ?? 'mine')"), 'api_app_home must forward the selected dashboard time scope.');
$assert(str_contains($feed, 'function app_feed_payload'), 'App feed payload helper is missing.');
$assert(str_contains($feed, 'function app_feed_ticket_card'), 'Ticket card formatter is missing.');
$assert(str_contains($feed, 'function app_feed_queue_sections'), 'Queue section formatter is missing.');
$assert(str_contains($feed, 'function app_feed_active_timers'), 'Active timer formatter is missing.');
$assert(str_contains($feed, 'function app_feed_time_activity'), 'Worked-time formatter is missing.');
$assert(str_contains($feed, 'function app_feed_team_time_member'), 'Team time member formatter is missing.');
$assert(str_contains($feed, 'time_activity_work_model($user'), 'App home must reuse the shared Work time model.');
$assert(str_contains($feed, "\$model['team']"), 'App home worked-time feed must expose team activity from the shared Work model.');
$assert(str_contains($feed, "'team' => \$team"), 'App home worked-time payload must include team activity.');
$assert(str_contains($feed, 'function app_feed_notifications'), 'Notification formatter is missing.');
$assert(str_contains($feed, 'get_user_notifications((int) ($user[\'id\'] ?? 0), 3, 0, true)'), 'App home must expose a compact recent-notification feed.');
$assert(str_contains($feed, "'items' => array_map("), 'App home notifications must include recent update items.');
$assert(str_contains($feed, 'app_contract_notification_summary_item($notification)'), 'App home notification items must reuse the native notification contract.');
$assert(str_contains($feed, 'work_queue_summary($user, $limit)'), 'App home must reuse Work queue module.');
$assert(str_contains($feed, 'inbox_summary($user, $limit)'), 'App home must reuse Inbox module.');
$assert(str_contains($feed, 'app_contract_schema_version()'), 'App home payload must use the shared schema version helper.');
$assert(str_contains($docs, 'seventh behavior change adds an authenticated `app-home` API contract'), 'Architecture docs must describe milestone 7.');

echo "App home contract OK\n";
