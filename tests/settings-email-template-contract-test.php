<?php

define('BASE_PATH', dirname(__DIR__));

function t($key, $replacements = [])
{
    foreach ((array) $replacements as $name => $value) {
        $key = str_replace('{' . $name . '}', (string) $value, $key);
    }
    return $key;
}

require_once BASE_PATH . '/includes/modules/settings/settings-templates.php';

$read = static function (string $path): string {
    $contents = file_get_contents(BASE_PATH . '/' . $path);
    if ($contents === false) {
        fwrite(STDERR, 'Unable to read ' . $path . PHP_EOL);
        exit(1);
    }
    return $contents;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$catalog = settings_email_template_catalog();
$required = settings_email_template_required_variables();

foreach (['status_change', 'new_comment', 'new_ticket', 'password_reset', 'ticket_confirmation', 'ticket_assignment', 'recurring_task_assignment', 'long_timer_alert', 'welcome_email'] as $key) {
    $assert(isset($catalog[$key]), 'Template catalog is missing ' . $key . '.');
    $assert(isset($required[$key]), 'Template required-variable map is missing ' . $key . '.');
    foreach ($required[$key] as $variable) {
        $assert(isset($catalog[$key]['variables'][$variable]), 'Required variable ' . $variable . ' must be listed for ' . $key . '.');
    }
}

$valid_reset = settings_validate_email_template_input(
    'password_reset',
    'Reset your password',
    "Open this link: {reset_link}\n{app_name}",
    'en'
);
$assert($valid_reset['valid'] === true, 'Password reset template with required variables must be valid.');
$assert($valid_reset['language'] === 'en', 'Valid language must be preserved.');

$missing_reset_link = settings_validate_email_template_input(
    'password_reset',
    'Reset your password',
    'Open FoxDesk to reset your password.',
    'en'
);
$assert($missing_reset_link['valid'] === false, 'Password reset template must require reset_link.');
$assert(in_array('Missing required template variable: {reset_link}', $missing_reset_link['errors'], true), 'Missing reset_link must be reported clearly.');

$unknown_variable = settings_validate_email_template_input(
    'ticket_assignment',
    'Assigned {ticket_code}',
    'Hello {agent_name}, open {ticket_url}. {unknown_value}',
    'en'
);
$assert($unknown_variable['valid'] === false, 'Templates must reject unknown placeholder variables.');
$assert(in_array('Unknown template variable: {unknown_value}', $unknown_variable['errors'], true), 'Unknown placeholder must be reported clearly.');

$bad_language = settings_validate_email_template_input(
    'new_ticket',
    'New {ticket_id}',
    'Open {ticket_url}: {ticket_title}',
    'xx'
);
$assert($bad_language['valid'] === false, 'Unsupported language must invalidate a template save.');
$assert($bad_language['language'] === 'en', 'Unsupported language must fall back to en for redirect/state.');

$empty_fields = settings_validate_email_template_input('new_comment', '', '', 'en');
$assert($empty_fields['valid'] === false, 'Empty subject/body must be rejected.');
$assert(in_array('Email subject is required.', $empty_fields['errors'], true), 'Empty subject must be reported.');
$assert(in_array('Email body is required.', $empty_fields['errors'], true), 'Empty body must be reported.');

$unknown_key = settings_validate_email_template_input('not_a_real_template', 'Subject', 'Body', 'en');
$assert($unknown_key['valid'] === false, 'Unknown template keys must be rejected.');
$assert(in_array('Unknown email template.', $unknown_key['errors'], true), 'Unknown template key must be reported.');

$actions = $read('includes/modules/settings/settings-actions.php');
$page = $read('pages/admin/settings.php');
$templates = $read('includes/modules/settings/settings-templates.php');

$assert(str_contains($actions, 'settings_validate_email_template_input'), 'Settings action handler must validate templates before saving.');
$assert(str_contains($actions, "if (!empty(\$validation['valid']))"), 'Settings action handler must save only valid templates.');
$assert(str_contains($actions, "flash(implode(' ', (array) (\$validation['errors']"), 'Settings action handler must show validation errors.');
$assert(str_contains($page, 'settings_email_template_required_variables'), 'Settings template UI must know required variables.');
$assert(str_contains($page, "t('Required')"), 'Settings template UI must label required variables.');
$assert(str_contains($templates, 'function settings_email_template_supported_languages'), 'Settings templates module must own supported language list.');
$assert(str_contains($templates, 'function settings_validate_email_template_input'), 'Settings templates module must own validation logic.');

echo "Settings email template contract OK\n";
