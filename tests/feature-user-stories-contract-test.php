<?php

$root = dirname(__DIR__);
$spreadsheet = $root . '/docs/feature-user-stories.csv';

if (!is_file($spreadsheet)) {
    fwrite(STDERR, "Missing canonical feature user story spreadsheet.\n");
    exit(1);
}

$handle = fopen($spreadsheet, 'rb');
if (!$handle) {
    fwrite(STDERR, "Unable to open canonical feature user story spreadsheet.\n");
    exit(1);
}

$expected_header = [
    'feature_id',
    'edition',
    'area',
    'feature',
    'role',
    'user_story',
    'expected_behavior',
    'entry_points',
    'code_evidence',
    'test_evidence',
    'feature_status',
    'test_status',
    'error_status',
    'fix_status',
    'retest_status',
    'priority',
    'notes',
    'last_reviewed',
];

$header = fgetcsv($handle);
if ($header !== $expected_header) {
    fwrite(STDERR, "Feature story spreadsheet header changed unexpectedly.\n");
    exit(1);
}

$allowed_feature_status = [
    'story_drafted',
    'testing',
    'tested_pass',
    'tested_fail',
    'fixing',
    'fixed',
    'retested_pass',
    'retested_fail',
    'deprecated',
];

$allowed_test_status = [
    'needs_test',
    'needs_e2e',
    'needs_runtime_test',
    'needs_visual_test',
    'needs_external_smoke',
    'needs_self_hosted_smoke',
    'covered_contract',
    'covered_smoke',
    'covered_e2e',
    'tested_pass',
    'tested_fail',
    'retested_pass',
    'retested_fail',
];

$allowed_priorities = ['P0', 'P1', 'P2', 'P3'];
$required_ids = [
    'PUBLIC-001',
    'SIGNUP-001',
    'SIGNUP-002',
    'AUTH-001',
    'HOST-001',
    'WORK-001',
    'INBOX-001',
    'DASH-001',
    'TICKET-001',
    'TICKET-005',
    'SEARCH-001',
    'CLIENT-001',
    'TEAM-002',
    'AGENT-001',
    'APP-001',
    'MOBILE-001',
    'REPORT-002',
    'BILLING-001',
    'PLATFORM-001',
    'MIGRATION-001',
    'EMAIL-001',
    'EMAIL-003',
    'SETTINGS-004',
    'STORAGE-001',
    'INSTALL-001',
    'UPGRADE-001',
    'SECURITY-001',
    'VISUAL-001',
];

$required_areas = [
    'Public web',
    'Legal',
    'Signup',
    'Authentication',
    'Host separation',
    'Work',
    'Inbox',
    'Dashboard',
    'Tickets',
    'Time tracking',
    'Attachments',
    'Search',
    'Clients',
    'Team',
    'Agent API',
    'App contract',
    'Mobile API',
    'Reports',
    'Billing',
    'Platform',
    'Migration',
    'Email',
    'Notifications',
    'Settings',
    'Workflow admin',
    'Automation',
    'Storage',
    'Operations',
    'Profile',
    'Sharing',
    'Self-hosted',
    'Security',
    'Visual system',
];

$ids = [];
$areas = [];
$editions = [];
$rows = 0;

while (($row = fgetcsv($handle)) !== false) {
    $rows++;
    if (count($row) !== count($expected_header)) {
        fwrite(STDERR, "Row {$rows} has " . count($row) . " columns, expected " . count($expected_header) . ".\n");
        exit(1);
    }

    $record = array_combine($expected_header, $row);
    if (!$record) {
        fwrite(STDERR, "Row {$rows} could not be mapped to headers.\n");
        exit(1);
    }

    foreach (['feature_id', 'edition', 'area', 'feature', 'role', 'user_story', 'expected_behavior', 'entry_points', 'code_evidence', 'test_evidence', 'feature_status', 'test_status', 'priority', 'last_reviewed'] as $required_column) {
        if (trim((string) $record[$required_column]) === '') {
            fwrite(STDERR, "Row {$rows} has an empty required column: {$required_column}.\n");
            exit(1);
        }
    }

    $id = (string) $record['feature_id'];
    if (isset($ids[$id])) {
        fwrite(STDERR, "Duplicate feature_id in spreadsheet: {$id}.\n");
        exit(1);
    }
    if (!preg_match('/^[A-Z]+-[0-9]{3}$/', $id)) {
        fwrite(STDERR, "Feature id {$id} does not match the canonical pattern.\n");
        exit(1);
    }

    if (!in_array($record['feature_status'], $allowed_feature_status, true)) {
        fwrite(STDERR, "Feature {$id} uses unsupported feature_status {$record['feature_status']}.\n");
        exit(1);
    }
    if (!in_array($record['test_status'], $allowed_test_status, true)) {
        fwrite(STDERR, "Feature {$id} uses unsupported test_status {$record['test_status']}.\n");
        exit(1);
    }
    if (!in_array($record['priority'], $allowed_priorities, true)) {
        fwrite(STDERR, "Feature {$id} uses unsupported priority {$record['priority']}.\n");
        exit(1);
    }
    if (!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/', $record['last_reviewed'])) {
        fwrite(STDERR, "Feature {$id} has invalid last_reviewed date {$record['last_reviewed']}.\n");
        exit(1);
    }

    $ids[$id] = true;
    $areas[$record['area']] = true;
    $editions[$record['edition']] = true;
}

fclose($handle);

if ($rows < 50) {
    fwrite(STDERR, "Feature story spreadsheet is too small for app-wide coverage: {$rows} rows.\n");
    exit(1);
}

foreach ($required_ids as $required_id) {
    if (!isset($ids[$required_id])) {
        fwrite(STDERR, "Missing required feature story {$required_id}.\n");
        exit(1);
    }
}

foreach ($required_areas as $required_area) {
    if (!isset($areas[$required_area])) {
        fwrite(STDERR, "Missing required feature area {$required_area}.\n");
        exit(1);
    }
}

foreach (['shared', 'saas', 'self-hosted', 'legacy'] as $edition_fragment) {
    $found = false;
    foreach (array_keys($editions) as $edition) {
        if (str_contains($edition, $edition_fragment)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        fwrite(STDERR, "Spreadsheet must include {$edition_fragment} feature coverage.\n");
        exit(1);
    }
}

echo "Feature user stories spreadsheet contract OK ({$rows} rows)\n";
