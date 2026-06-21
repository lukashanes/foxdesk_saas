<?php

$root = dirname(__DIR__);

$router = file_get_contents($root . '/includes/api/router.php');
$handler = file_get_contents($root . '/includes/api/push-handler.php');
$web_push = file_get_contents($root . '/includes/web-push.php');
$tenant = file_get_contents($root . '/includes/tenant-functions.php');
$schema = file_get_contents($root . '/includes/schema.sql');
$footer = file_get_contents($root . '/includes/footer.php');
$notifications = file_get_contents($root . '/includes/notification-functions.php');

if (
    $router === false
    || $handler === false
    || $web_push === false
    || $tenant === false
    || $schema === false
    || $footer === false
    || $notifications === false
) {
    fwrite(STDERR, "Unable to read web push contract files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

foreach ([
    "'push-subscribe' => 'api_push_subscribe'",
    "'push-unsubscribe' => 'api_push_unsubscribe'",
    "'push-vapid-key' => 'api_push_vapid_key'",
    "'push-notifications' => 'api_push_notifications'",
] as $route) {
    $assert(str_contains($router, $route), 'Missing push route: ' . $route);
}

$public_actions = '';
if (preg_match('/\$public_actions\s*=\s*\[([\s\S]*?)\];/', $router, $matches)) {
    $public_actions = $matches[1];
}
$assert($public_actions !== '', 'Unable to inspect public API action list.');
$assert(!str_contains($public_actions, "'push-subscribe'"), 'Push subscribe must not be public.');
$assert(!str_contains($public_actions, "'push-unsubscribe'"), 'Push unsubscribe must not be public.');
$assert(!str_contains($public_actions, "'push-notifications'"), 'Push notifications must not be public.');

foreach ([
    'function api_push_subscribe',
    'function api_push_unsubscribe',
    'function api_push_vapid_key',
    'function api_push_notifications',
    'require_csrf_token(true)',
    'current_user()',
    'save_push_subscription((int) $user[\'id\']',
    'remove_push_subscription((int) $user[\'id\']',
    'tenant_sql_filter(\'notifications\', \'n\', $params)',
    'filter_notifications_for_user',
] as $needle) {
    $assert(str_contains($handler, $needle), 'Push handler missing behavior: ' . $needle);
}

foreach ([
    'CREATE TABLE IF NOT EXISTS push_subscriptions',
    'tenant_id INT NULL',
    'INDEX idx_push_tenant_user (tenant_id, user_id)',
] as $needle) {
    $assert(str_contains($schema, $needle), 'Schema missing push subscription tenant behavior: ' . $needle);
}

$assert(str_contains($tenant, "'push_subscriptions'"), 'SaaS tenant-owned table list must include push_subscriptions.');

foreach ([
    'function ensure_push_subscriptions_table',
    'tenant_id INT NULL',
    "ALTER TABLE push_subscriptions ADD COLUMN tenant_id INT NULL AFTER id",
    'function push_subscriptions_has_tenant_scope',
    'function push_subscription_tenant_filter',
    'current_tenant_id()',
    "\$data['tenant_id'] = current_tenant_id()",
    'DELETE FROM push_subscriptions WHERE {$where}',
    'SELECT * FROM push_subscriptions WHERE {$where}',
    'SELECT COUNT(*) as cnt FROM push_subscriptions WHERE {$where}',
    'send_web_push($sub[\'endpoint\'], 86400',
] as $needle) {
    $assert(str_contains($web_push, $needle), 'Web push helper missing tenant-safe behavior: ' . $needle);
}

foreach ([
    'serviceWorker' => 'Footer must register service worker.',
    'push-vapid-key' => 'Footer must fetch VAPID key.',
    'push-subscribe' => 'Footer must call push subscribe endpoint.',
    'push-unsubscribe' => 'Footer must call push unsubscribe endpoint.',
    'X-CSRF-Token' => 'Footer push API calls must include CSRF header.',
] as $needle => $message) {
    $assert(str_contains($footer, $needle), $message);
}

$assert(str_contains($notifications, 'dispatch_push_notifications($notified_user_ids)'), 'Notification dispatcher must trigger web push dispatch.');

echo "Web push contract OK\n";
