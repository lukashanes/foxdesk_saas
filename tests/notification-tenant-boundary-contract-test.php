<?php

$root = dirname(__DIR__);
$notifications = file_get_contents($root . '/includes/notification-functions.php');

function assert_notification_tenant_boundary($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Notification tenant boundary contract failed: {$message}\n");
        exit(1);
    }
}

assert_notification_tenant_boundary($notifications !== false, 'Notification functions source must be readable.');

assert_notification_tenant_boundary(
    str_contains($notifications, 'tenant_id INT NULL')
        && str_contains($notifications, 'idx_notifications_tenant_user (tenant_id, user_id)')
        && str_contains($notifications, 'ALTER TABLE notifications ADD COLUMN tenant_id INT NULL'),
    'Notifications table must store tenant_id and index tenant/user lookups.'
);

assert_notification_tenant_boundary(
    str_contains($notifications, 'function notification_tenant_id_for_insert')
        && str_contains($notifications, "\$insert['tenant_id'] = \$notification_tenant_id"),
    'Notification creation must persist the ticket/current tenant id.'
);

assert_notification_tenant_boundary(
    str_contains($notifications, '$notification_tenant_id')
        && str_contains($notifications, '$ticket_tenant_id')
        && str_contains($notifications, '$user_tenant_id')
        && strpos($notifications, '$ticket_tenant_id') < strpos($notifications, "if (function_exists('can_see_ticket')"),
    'Notification visibility must reject cross-tenant ticket notifications before role shortcuts.'
);

assert_notification_tenant_boundary(
    str_contains($notifications, "function_exists('get_ticket_unscoped')")
        && str_contains($notifications, 'get_ticket_unscoped($ticket_id)'),
    'Notification visibility must resolve the ticket tenant independently of the current session tenant.'
);

assert_notification_tenant_boundary(
    !str_contains($notifications, "if ((\$user['role'] ?? '') === 'admin') {\n        return array_values(\$notifications);\n    }"),
    'Admin notification lists must not bypass per-notification tenant visibility checks.'
);

assert_notification_tenant_boundary(
    str_contains($notifications, 'AND (n.tenant_id IS NULL OR n.tenant_id = ?)')
        && str_contains($notifications, 'AND (tenant_id IS NULL OR tenant_id = ?)'),
    'Notification list and unread count queries must prefer current-user tenant rows.'
);

echo "Notification tenant boundary contract passed\n";
