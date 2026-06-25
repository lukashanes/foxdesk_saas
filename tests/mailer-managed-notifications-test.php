<?php
define('BASE_PATH', dirname(__DIR__));

$test_settings = [
    'email_notifications_enabled' => '0',
    'smtp_from_email' => 'notifications@example.test',
    'smtp_from_name' => 'FoxDesk Test',
];
$test_recipient = [
    'id' => 7,
    'email' => 'recipient@example.test',
    'email_notifications_enabled' => 1,
];

function get_settings(): array
{
    global $test_settings;
    return $test_settings;
}

function db_fetch_one(string $sql, array $params = []): array
{
    global $test_recipient;

    if (str_contains($sql, 'FROM users WHERE email = ?')) {
        return $test_recipient;
    }

    if (str_contains($sql, "FROM users WHERE role = 'admin'")) {
        return ['email' => 'admin@example.test'];
    }

    return [];
}

function assert_mailer_managed(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

putenv('MAIL_PROVIDER=log');
putenv('SMTP_FROM_EMAIL=notifications@example.test');
putenv('SMTP_FROM_NAME=FoxDesk Test');
putenv('FOXDESK_EDITION=saas');

require_once BASE_PATH . '/includes/mailer.php';

assert_mailer_managed(
    mailer_ticket_notification_setting_enabled($test_settings, 'notify_on_new_comment') === true,
    'Managed SaaS must default missing ticket comment notification settings to enabled.'
);

assert_mailer_managed(
    mailer_ticket_notification_setting_enabled($test_settings, 'notify_on_status_change') === true,
    'Managed SaaS must default missing ticket status notification settings to enabled.'
);

assert_mailer_managed(
    mailer_ticket_notification_setting_enabled($test_settings, 'notify_on_new_ticket') === true,
    'Managed SaaS must default missing new-ticket notification settings to enabled.'
);

assert_mailer_managed(
    send_email('recipient@example.test', 'Managed SaaS ticket update', 'Body') === true,
    'Managed SaaS must not let legacy workspace master setting block ticket email delivery.'
);

$test_settings['notify_on_new_comment'] = '0';
assert_mailer_managed(
    mailer_ticket_notification_setting_enabled($test_settings, 'notify_on_new_comment') === false,
    'Managed SaaS must still respect an explicit ticket comment email opt-out.'
);
unset($test_settings['notify_on_new_comment']);

$test_recipient['email_notifications_enabled'] = 0;
assert_mailer_managed(
    send_email('recipient@example.test', 'Managed SaaS recipient opt-out', 'Body') === false,
    'Managed SaaS must still respect recipient-level email opt-out.'
);

$test_recipient['email_notifications_enabled'] = 1;
putenv('FOXDESK_EDITION=self-hosted');
assert_mailer_managed(
    mailer_ticket_notification_setting_enabled($test_settings, 'notify_on_new_comment') === false,
    'Self-hosted must keep treating a missing ticket notification setting as disabled.'
);

$test_settings['notify_on_new_comment'] = '1';
assert_mailer_managed(
    mailer_ticket_notification_setting_enabled($test_settings, 'notify_on_new_comment') === true,
    'Self-hosted must send ticket comment emails when the setting is explicitly enabled.'
);
unset($test_settings['notify_on_new_comment']);

assert_mailer_managed(
    send_email('recipient@example.test', 'Self-hosted disabled master', 'Body') === false,
    'Self-hosted must keep respecting the workspace email notification master switch.'
);

putenv('FOXDESK_EDITION');
putenv('APP_MARKETING_HOST=foxdesk.net');
assert_mailer_managed(
    send_email('recipient@example.test', 'Managed host ticket update', 'Body') === true,
    'SaaS host configuration must also enable managed notification delivery.'
);

echo "Mailer managed notifications test passed\n";
