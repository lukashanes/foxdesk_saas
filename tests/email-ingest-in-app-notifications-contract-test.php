<?php

$source = file_get_contents(__DIR__ . '/../includes/email-ingest-functions.php');
$notifications = file_get_contents(__DIR__ . '/../includes/notification-functions.php');

function assert_email_ingest_notifications($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "Email ingest notifications contract failed: {$message}\n");
        exit(1);
    }
}

assert_email_ingest_notifications(
    str_contains($source, 'function email_ingest_dispatch_ticket_notifications'),
    'Inbound email ingest must have a shared in-app notification dispatcher.'
);

assert_email_ingest_notifications(
    substr_count($source, 'email_ingest_dispatch_ticket_notifications(') >= 3,
    'Both IMAP and Cloudflare inbound paths must dispatch ticket notifications after commit.'
);

assert_email_ingest_notifications(
    str_contains($source, "\$ticket_created ? 'new_ticket' : 'new_comment'"),
    'Inbound notification dispatcher must map new emails to new_ticket and replies to new_comment.'
);

assert_email_ingest_notifications(
    str_contains($source, "require_once \$notification_file"),
    'Inbound notification dispatcher must lazy-load notification-functions.php for CLI/Worker ingest.'
);

assert_email_ingest_notifications(
    str_contains($source, "function_exists('t')") && str_contains($source, "BASE_PATH . '/includes/functions.php'"),
    'Inbound notification dispatcher must lazy-load app helpers so notification preferences can translate labels.'
);

assert_email_ingest_notifications(
    str_contains($source, "function_exists('get_user')") && str_contains($source, "BASE_PATH . '/includes/auth.php'"),
    'Inbound notification dispatcher must lazy-load auth helpers so agent visibility checks can resolve users.'
);

assert_email_ingest_notifications(
    str_contains($source, "'source' => 'email'") && str_contains($source, "'attachment_count'"),
    'Inbound notifications should include email source and attachment metadata.'
);

assert_email_ingest_notifications(
    str_contains($notifications, 'function get_staff_user_ids') &&
    str_contains($notifications, "tenant_sql_filter('users'"),
    'Staff notification recipients must be scoped to the current tenant.'
);

assert_email_ingest_notifications(
    str_contains($notifications, "(\$notification['type'] ?? '') === 'new_ticket'") &&
    str_contains($notifications, "(\$data['source'] ?? '') === 'email'") &&
    str_contains($notifications, "empty(\$ticket['assignee_id'])"),
    'Agents must be able to see email-created unassigned ticket notifications for triage.'
);

echo "Email ingest in-app notifications contract passed\n";
