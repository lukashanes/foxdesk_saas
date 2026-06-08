<?php
define('BASE_PATH', dirname(__DIR__));

function assert_contract($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$mailer = file_get_contents(BASE_PATH . '/includes/mailer.php');
$cloudflare_email_test = file_get_contents(BASE_PATH . '/bin/test-cloudflare-email.php');
$ticket_handler = file_get_contents(BASE_PATH . '/includes/components/ticket-form-handlers.php');
$pseudo_cron = file_get_contents(BASE_PATH . '/includes/pseudo-cron.php');
$cron = file_get_contents(BASE_PATH . '/pages/cron.php');

assert_contract(strpos($mailer, 'function send_ticket_notification_email') !== false, 'Ticket notification email renderer wrapper is missing.');
assert_contract(strpos($mailer, 'foxdesk_render_ticket_email_html') !== false, 'Ticket notifications should use the shared HTML renderer when available.');
assert_contract(strpos($mailer, 'should_send_new_ticket_admin_email') !== false, 'New-ticket admin emails must use notification policy suppression.');
assert_contract(strpos($mailer, 'should_send_ticket_confirmation_email') !== false, 'Ticket confirmations must use notification policy suppression.');
assert_contract(strpos($mailer, 'should_send_ticket_assignment_email') !== false, 'Assignment emails must use notification policy suppression.');
assert_contract(strpos($mailer, "'eyebrow' => 'Ticket received'") !== false, 'Ticket confirmation should use the shared ticket email renderer payload.');
assert_contract(strpos($mailer, 'send_email($user[\'email\'], $subject, $body)') === false, 'Ticket confirmation must not use the legacy plain-text send_email path.');
assert_contract(strpos($mailer, 'function mailer_cloudflare_attachments') !== false, 'Cloudflare Email Sending should normalize attachment payloads.');
assert_contract(strpos($mailer, "\$payload['attachments']") !== false, 'Cloudflare Email Sending should include attachments in the REST payload.');
assert_contract(strpos($cloudflare_email_test, "'signup'") !== false, 'Cloudflare email test must include signup scenario.');
assert_contract(strpos($cloudflare_email_test, "'reset'") !== false, 'Cloudflare email test must include reset-password scenario.');
assert_contract(strpos($cloudflare_email_test, "'new-ticket'") !== false, 'Cloudflare email test must include new-ticket scenario.');
assert_contract(strpos($cloudflare_email_test, "'ticket-reply'") !== false, 'Cloudflare email test must include ticket-reply scenario.');
assert_contract(strpos($cloudflare_email_test, "'billing'") !== false, 'Cloudflare email test must include billing scenario.');
assert_contract(strpos($cloudflare_email_test, '--scenario=all') !== false, 'Cloudflare email test should support all scenarios in one run.');
assert_contract(strpos($cloudflare_email_test, '--direct-cloudflare') !== false, 'Cloudflare email test should support a direct API smoke without DB.');
assert_contract(strpos($cloudflare_email_test, 'This tests the') === false, 'Cloudflare email smoke scenarios should use real-life copy, not lab placeholder text.');
assert_contract(strpos($ticket_handler, '$will_send_public_comment_notification') !== false, 'Ticket form should detect public comment notifications before status dispatch.');
assert_contract(strpos($ticket_handler, '!$will_send_public_comment_notification') !== false, 'Status notifications should be suppressed when the same submit sends a public comment.');
assert_contract(strpos($pseudo_cron, 'pseudo_cron_schedule_inline_email_ingest') !== false, 'Inline email ingest fallback is missing.');
assert_contract(strpos($pseudo_cron, 'register_shutdown_function') !== false, 'Inline email ingest should run after page response shutdown.');
assert_contract(strpos($cron, '!empty($cfg[\'enabled\'])') !== false, 'Cron endpoint must respect disabled IMAP setting.');

echo "Email notification contract tests passed\n";
