<?php

$root = dirname(__DIR__);

$pseudoCron = file_get_contents($root . '/includes/pseudo-cron.php');
$maintenance = file_get_contents($root . '/bin/run-maintenance.php');
$cronPage = file_get_contents($root . '/pages/cron.php');
$emailIngest = file_get_contents($root . '/includes/email-ingest-functions.php');
$releaseChecklist = file_get_contents($root . '/docs/SELF_HOSTED_RELEASE_CHECKLIST.md');

if ($pseudoCron === false || $maintenance === false || $cronPage === false || $emailIngest === false || $releaseChecklist === false) {
    fwrite(STDERR, "Unable to read pseudo-cron email files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

foreach ([
    "'pseudo_cron_last_email'       => 300",
    'pseudo_cron_schedule_inline_email_ingest($now)',
    'register_shutdown_function',
    'pseudo_cron_run_inline_email_ingest',
    'email_ingest_config()',
    'email_ingest_run()',
    "'pseudo_cron_email_inline_lock'",
    "save_setting('pseudo_cron_last_email'",
] as $needle) {
    $assert(str_contains($pseudoCron, $needle), "Pseudo-cron inline IMAP fallback missing: {$needle}");
}

foreach ([
    "require_once BASE_PATH . '/includes/email-ingest-functions.php'",
    '$ingest_cfg = email_ingest_config()',
    '$result[\'email_ingest\'] = email_ingest_run()',
    "'status' => 'disabled'",
    "'reason' => 'IMAP config missing'",
] as $needle) {
    $assert(str_contains($maintenance, $needle), "CLI maintenance IMAP behavior missing: {$needle}");
}

$assert(str_contains($cronPage, "!empty(\$cfg['enabled'])"), 'Cron page must respect disabled IMAP setting.');
$assert(str_contains($emailIngest, 'function email_ingest_run'), 'Email ingest runner is missing.');
$assert(str_contains($releaseChecklist, 'IMAP ingest works through'), 'Self-hosted checklist must require IMAP ingest checks.');
$assert(str_contains($releaseChecklist, 'pseudo-cron inline fallback'), 'Self-hosted checklist must require pseudo-cron fallback checks.');

echo "Pseudo-cron email contract OK\n";
