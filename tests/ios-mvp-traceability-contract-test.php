<?php

$root = dirname(__DIR__);
$trace = file_get_contents($root . '/docs/IOS_MVP_TRACEABILITY.md');
$handoff = file_get_contents($root . '/docs/IOS_HANDOFF.md');
$plan = file_get_contents($root . '/docs/IOS_APP_PLAN.md');
$launch = file_get_contents($root . '/docs/IOS_APP_LAUNCH_PLAN.md');
$scopeTest = file_get_contents($root . '/tests/ios-mvp-scope-contract-test.php');
$gate = file_get_contents($root . '/bin/ios-mvp-gate.sh');
$submissionGate = file_get_contents($root . '/bin/ios-submission-gate.sh');
$completionAudit = file_get_contents($root . '/bin/ios-completion-audit.sh');
$package = file_get_contents($root . '/package.json');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert($trace !== false, 'iOS MVP traceability document is missing.');
$assert($handoff !== false, 'iOS handoff document is missing.');
$assert($plan !== false, 'iOS app plan is missing.');
$assert($launch !== false, 'iOS launch plan is missing.');
$assert($scopeTest !== false, 'iOS MVP scope contract is missing.');
$assert($gate !== false, 'iOS MVP gate is missing.');
$assert($submissionGate !== false, 'iOS submission gate is missing.');
$assert($completionAudit !== false, 'iOS completion audit is missing.');
$assert($package !== false, 'package.json is missing.');

$requiredRows = [
    'Sign in to `app.foxdesk.net`' => ['LoginView', 'AppSession', 'KeychainTokenStore'],
    'Dashboard / work overview' => ['DashboardView', 'DashboardWorkedTimeView', 'DashboardWorkQueuesView'],
    'Agent ticket queues' => ['TicketsView', 'WorkQueueSections'],
    'New ticket from iPhone' => ['NewTicketView'],
    'Ticket detail' => ['TicketDetailView', 'TicketActivityView', 'TicketAttachmentsView', 'TicketTimerView'],
    'Admin ticket management' => ['TicketManageSheet', 'GET /api/mobile/v1/tickets/{id}/actions', 'POST /api/mobile/v1/tickets/{id}'],
    'Public reply / internal note' => ['CommentComposerSection'],
    'Comment with time' => ['comment-with-time', 'exact/manual time controls'],
    'Basic reply formatting' => ['MobileRichTextFormatter', 'MobileRichTextFormatterTests'],
    'Timer controls' => ['TimerControlSection', 'ActiveTimersSection'],
    'Photos, files, and previews' => ['AttachmentUploadSection', 'CameraCaptureView', 'AttachmentPreviewView'],
    'Push notifications' => ['PushRegistrationService', 'NotificationsView', 'PushNavigationRouter'],
    'Global search' => ['SearchView'],
    'Client context' => ['ClientContextView'],
    'Offline and speed fallback' => ['HomeFeedCacheStore', 'TicketListCacheStore', 'TicketDetailCacheStore', 'TicketCommentDraftStore', 'StagedAttachmentUploadState'],
    'Lightweight account/logout' => ['AccountView'],
];

foreach ($requiredRows as $label => $needles) {
    $assert(str_contains($trace, $label), "Traceability is missing MVP row: {$label}");
    foreach ($needles as $needle) {
        $assert(str_contains($trace, $needle), "Traceability row {$label} is missing evidence: {$needle}");
    }
}

$outOfScope = [
    'Stripe Checkout',
    'SaaS platform administration',
    'Full workspace settings',
    'Self-hosted server setup',
];

foreach ($outOfScope as $needle) {
    $assert(str_contains($trace, $needle), "Traceability must explicitly keep {$needle} out of iOS MVP.");
    $assert(str_contains($plan, $needle), "iOS app plan must explicitly keep {$needle} out of iOS MVP.");
}

$assert(str_contains($plan, 'not a copy of the full PHP web admin'), 'iOS app plan must reject copying the web admin.');
$assert(str_contains($plan, 'Manage ticket status, priority, and assignee'), 'iOS app plan must include lightweight admin ticket management.');
$assert(str_contains($plan, 'POST /tickets/{id}/comment-with-time'), 'iOS app plan must include comment-with-time in the mobile API contract.');
$assert(str_contains($plan, 'APNS_TEST_DEVICE_TOKEN'), 'iOS app plan must include real-device APNs smoke evidence.');
$assert(str_contains($handoff, 'IOS_MVP_TRACEABILITY.md'), 'iOS handoff must link the MVP traceability matrix.');
$assert(str_contains($handoff, 'IOS_APP_PLAN.md'), 'iOS handoff must link the MVP app plan.');
$assert(str_contains($launch, 'IOS_MVP_TRACEABILITY.md'), 'iOS launch plan must link the MVP traceability matrix.');
$assert(str_contains($trace, 'npm run ios:mvp:audit'), 'Traceability must start release evidence with the fast MVP audit.');
$assert(str_contains($trace, 'tmp/ios-mvp-local-audit/latest.md'), 'Traceability must document the MVP audit evidence report.');
$assert(str_contains($submissionGate, 'npm run ios:mvp:audit'), 'Submission gate must run the fast MVP audit before final checks.');
$assert(str_contains($submissionGate, 'npm run ios:completion:audit'), 'Submission gate must run the completion audit before final checks.');
$assert(str_contains($package, '"ios:next-actions": "./bin/ios-next-actions.sh"'), 'package.json must expose the iOS next-action handoff script as npm run ios:next-actions.');
$assert(str_contains($completionAudit, 'Create ticket from iPhone'), 'Completion audit must track native iPhone ticket creation separately.');
$assert(str_contains($completionAudit, 'NewTicketView'), 'Completion audit must cite NewTicketView as native create-ticket evidence.');
$assert(str_contains($completionAudit, 'POST /api/mobile/v1/tickets'), 'Completion audit must cite the mobile create-ticket API.');
$assert(str_contains($completionAudit, 'New ticket tab contract'), 'Completion audit must cite the primary New ticket tab contract.');
$assert(str_contains($completionAudit, 'Opt-in write smoke must create and reload a real ticket'), 'Completion audit must require live write proof for native ticket creation.');
$assert(str_contains($scopeTest, 'Forbidden iOS MVP visible term'), 'Scope contract must keep forbidden product surfaces out of iOS.');
$assert(str_contains($gate, 'tests/ios-mvp-traceability-contract-test.php'), 'iOS MVP gate must run the traceability contract.');

echo "iOS MVP traceability contract OK\n";
