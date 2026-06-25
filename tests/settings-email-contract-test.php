<?php

$root = dirname(__DIR__);
putenv('FOXDESK_EDITION=saas');
require_once $root . '/includes/modules/settings/settings-email.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$keys = settings_email_action_keys();
foreach (['save_email', 'save_template'] as $key) {
    $assert(in_array($key, $keys, true), 'Email settings action key missing: ' . $key);
    $assert(settings_is_email_action([$key => '1']), 'Email settings action detector failed: ' . $key);
}
foreach (['test_smtp', 'test_imap', 'run_imap_now'] as $key) {
    $assert(!in_array($key, $keys, true), 'Managed SaaS email settings must not expose transport action: ' . $key);
    $assert(!settings_is_email_action([$key => '1']), 'Managed SaaS email detector must reject transport action: ' . $key);
}
$self_hosted_keys = settings_email_action_keys('self_hosted');
foreach (['test_smtp', 'test_imap', 'run_imap_now'] as $key) {
    $assert(in_array($key, $self_hosted_keys, true), 'Self-hosted email settings action key missing: ' . $key);
}
$assert(settings_email_surface_type() === 'managed', 'SaaS repository must default to managed email settings surface.');
$assert(settings_email_has_transport_action(['test_imap' => '1']), 'Transport action detector must catch IMAP actions.');
$assert(!settings_is_email_action(['save_general' => '1']), 'Email detector must not match non-email actions.');

echo "Settings email contract OK\n";
