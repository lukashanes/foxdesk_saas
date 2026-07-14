<?php

$root = dirname(__DIR__);
$scriptPath = $root . '/bin/ios-submission-gate.sh';
$mvpGatePath = $root . '/bin/ios-mvp-gate.sh';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$script = is_file($scriptPath) ? file_get_contents($scriptPath) : false;
$mvpGate = is_file($mvpGatePath) ? file_get_contents($mvpGatePath) : false;

$assert($script !== false, 'Missing iOS submission gate script.');
$assert($mvpGate !== false, 'Missing iOS MVP gate script.');

$contains = static fn (string $needle): bool => str_contains($script, $needle);

$assert($contains('source "$ROOT_DIR/bin/ios-release-env.sh"'), 'Submission gate must load ios-release-env.sh.');
$assert($contains('npm run ios:mvp:audit'), 'Submission gate must run the local iOS MVP audit.');
$assert($contains('npm run ios:beta:gate'), 'Submission gate must run the local beta readiness gate.');
$assert($contains('npm run ios:completion:audit'), 'Submission gate must run the iOS completion audit.');
$assert($contains('npm run ios:assets:check'), 'Submission gate must verify screenshot and privacy assets against the current iOS source.');

$assert($contains('DEMO_EVIDENCE="$ROOT_DIR/tmp/ios-demo-account-check/latest-live-demo-account.json"'), 'Submission gate must require demo-account evidence JSON.');
$assert($contains('API_READ_EVIDENCE="$ROOT_DIR/tmp/ios-api-smoke/latest-live-read-only.json"'), 'Submission gate must require live read-only API evidence JSON.');
$assert($contains('API_WRITE_EVIDENCE="$ROOT_DIR/tmp/ios-api-smoke/latest-live-write.json"'), 'Submission gate must require live write API evidence JSON.');
$assert($contains('APNS_DRY_EVIDENCE="$ROOT_DIR/tmp/ios-apns-smoke/latest-dry-run.json"'), 'Submission gate must require APNs dry-run evidence JSON.');
$assert($contains('APNS_SEND_EVIDENCE="$ROOT_DIR/tmp/ios-apns-smoke/latest-send.json"'), 'Submission gate must require live APNs send evidence JSON.');

foreach (['evidence_ready()', 'api_read_ready()', 'api_write_ready()', 'demo_write_ready()', 'apns_send_ready()', 'apns_dry_ready()', 'assert_evidence()'] as $function) {
    $assert($contains($function), "Submission gate is missing {$function}.");
}
$assert($contains('evidence_targets_production()'), 'Submission gate must distinguish production evidence from localhost smoke evidence.');
$assert($contains('hostname !== "app.foxdesk.net"'), 'Submission gate must only accept app.foxdesk.net as live API evidence.');

foreach ([
    '"create-ticket"',
    '"comment-with-time"',
    '"attachment-upload"',
    '"attachment-metadata"',
    '"attachment-download"',
    '"created-ticket-detail"',
] as $step) {
    $assert($contains($step), "Live write smoke evidence must require {$step}.");
}

$assert($contains('Number(download.bytes) <= 0'), 'Attachment download proof must include a positive byte count.');
$assert($contains('comment.exact_time_returned === true'), 'Live write smoke proof must require API response evidence for exact manual time.');
$assert($contains('detail.exact_time_visible === true'), 'Live write smoke proof must require reloaded ticket detail evidence for exact manual time.');
$assert($contains('detail.manual_date === comment.manual_date'), 'Live write smoke proof must compare manual date between comment and reload evidence.');
$assert($contains('detail.manual_start_time === comment.manual_start_time'), 'Live write smoke proof must compare manual start time between comment and reload evidence.');
$assert($contains('detail.manual_end_time === comment.manual_end_time'), 'Live write smoke proof must compare manual end time between comment and reload evidence.');
$assert($contains('comment_visible !== true'), 'Demo account proof must require the timed comment to be visible after reload.');
$assert($contains('linked_time_visible !== true'), 'Demo account proof must require linked time to be visible after reload.');
$assert($contains('manual_date'), 'Demo account proof must require manual_date in the timed comment evidence.');
$assert($contains('manual_start_time'), 'Demo account proof must require manual_start_time in the timed comment evidence.');
$assert($contains('manual_end_time'), 'Demo account proof must require manual_end_time in the timed comment evidence.');
$assert($contains('sent 2>/dev/null || true)" == "true"'), 'APNs proof must require sent=true, not only environment flags.');
$assert($contains('"due_date_reminder"'), 'APNs dry-run proof must cover due date reminders.');
$assert($contains('validated_payloads'), 'APNs dry-run proof must inspect validated payload content, not only command success.');
$assert($contains('payload.type !== type'), 'APNs dry-run proof must verify each payload type matches the expected notification type.');

$assert($contains('FOXDESK_IOS_ALLOW_PRODUCTION_WRITE_SMOKE'), 'Production write smoke must require an explicit acknowledgement.');
$assert($contains('[[ "${FOXDESK_IOS_SMOKE_WRITE:-}" == "1" ]] && smoke_base_is_production'), 'Production write smoke acknowledgement should only be required when write smoke is enabled.');
$assert($contains('staging.app.foxdesk.net'), 'Submission gate should recommend staging or a disposable workspace for write smoke.');

$assert($contains('npm run ios:demo:check -- --require-credentials --json'), 'Submission gate must run the App Review demo account check with credentials.');
$assert($contains('npm run ios:api:smoke -- --require-credentials --json'), 'Submission gate must run the live API smoke with credentials.');
$assert($contains('FOXDESK_IOS_SMOKE_WRITE=1 npm run ios:api:smoke -- --require-credentials --json'), 'Submission gate must run the opt-in live write smoke.');
$assert($contains('npm run ios:apns:smoke -- --json'), 'Submission gate must refresh APNs dry-run payload evidence before live send.');
$assert($contains('APNs dry-run payload coverage'), 'Submission gate must label APNs dry-run coverage separately from live send.');
$assert($contains('npm run ios:apns:smoke -- --send'), 'Submission gate must send a real APNs notification.');

$assert($contains('APP_STORE_CONNECT_APP_RECORD_READY'), 'Submission gate must require App Store Connect app record confirmation.');
$assert($contains('APPLE_DEVELOPER_BUNDLE_READY'), 'Submission gate must require Apple Developer bundle id and Push confirmation.');
$assert($contains('APPLE_BUSINESS_VERIFIED'), 'Submission gate must record Apple Business organization verification.');
$assert($contains('APP_STORE_PRIVACY_REVIEWED'), 'Submission gate must require App Store privacy review.');
$assert($contains('APP_STORE_PRICING_READY'), 'Submission gate must require app download pricing.');
$assert($contains('APP_STORE_AVAILABILITY_READY'), 'Submission gate must require App Store availability.');
$assert($contains('APP_STORE_CONTENT_RIGHTS_READY'), 'Submission gate must require Content Rights confirmation.');
$assert($contains('APP_STORE_AGREEMENTS_READY'), 'Submission gate must require agreement, tax, and banking readiness.');
$assert($contains('APP_STORE_UNTESTED_PLATFORMS_DISABLED'), 'Submission gate must require untested Apple platforms to be disabled.');
$assert($contains('APP_STORE_SELECTED_MARKETING_VERSION'), 'Submission gate must require the selected App Store marketing version.');
$assert($contains('APP_STORE_SELECTED_BUILD_NUMBER'), 'Submission gate must require the selected App Store build number.');
$assert($contains('project_setting MARKETING_VERSION'), 'Submission gate must compare the selected marketing version with project.yml.');
$assert($contains('project_setting CURRENT_PROJECT_VERSION'), 'Submission gate must compare the selected build with project.yml.');
$assert($contains('FOXDESK_IOS_DEMO_EMAIL'), 'Submission gate must require demo email.');
$assert($contains('FOXDESK_IOS_DEMO_PASSWORD'), 'Submission gate must require demo password.');
$assert($contains('APNS_TEST_DEVICE_TOKEN'), 'Submission gate must require a physical-device APNs token.');

$assert($contains('tmp/ios-app-store-screenshots'), 'Submission gate must require App Store screenshot evidence.');
$assert($contains('screenshot_count" -lt 8'), 'Submission gate must require the full populated screenshot set.');
$assert($contains('account.png'), 'Submission gate must require the account screenshot, not the old settings screenshot.');
$assert($contains('docs/IOS_DEMO_REVIEWER_ACCOUNT.md'), 'Submission gate must keep demo reviewer account docs linked.');

$assert(
    str_contains($mvpGate, 'tests/ios-submission-gate-contract-test.php'),
    'iOS MVP gate must run the submission gate contract test.'
);

echo "iOS submission gate contract OK\n";
