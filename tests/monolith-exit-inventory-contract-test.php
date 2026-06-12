<?php

$root = dirname(__DIR__);
$inventory_path = $root . '/docs/MONOLITH_EXIT_INVENTORY.md';
$inventory = file_get_contents($inventory_path);

if ($inventory === false) {
    fwrite(STDERR, "Unable to read monolith exit inventory.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

foreach ([
    'already modular',
    'needs module extraction',
    'SaaS-only platform page',
    'self-hosted migration/update page',
] as $status) {
    $assert(str_contains($inventory, $status), "Inventory must define status label: {$status}.");
}

$page_files = glob($root . '/pages/*.php') ?: [];
$admin_page_files = glob($root . '/pages/admin/*.php') ?: [];
$all_page_files = array_merge($page_files, $admin_page_files);
sort($all_page_files);

foreach ($all_page_files as $file) {
    $relative = str_replace($root . '/', '', $file);
    $assert(str_contains($inventory, "`{$relative}`"), "Inventory must include {$relative}.");
}

$priority_targets = [
    'pages/ticket-detail.php' => [
        'modules' => [
            'includes/modules/tickets/ticket-detail-context.php',
            'includes/modules/tickets/ticket-detail-timeline.php',
            'includes/modules/tickets/ticket-detail-sidebar.php',
            'includes/modules/tickets/ticket-detail-composer.php',
            'includes/modules/tickets/ticket-share-state.php',
            'assets/js/ticket-detail.js',
        ],
        'tests' => [
            'tests/ticket-detail-actions-test.php',
            'tests/ticket-detail-surface-contract-test.php',
            'tests/ticket-activity-surface-contract-test.php',
            'tests/ticket-composer-surface-contract-test.php',
            'tests/ticket-sidebar-surface-contract-test.php',
            'tests/ticket-detail-context-contract-test.php',
            'tests/ticket-detail-timeline-contract-test.php',
        ],
    ],
    'pages/admin/reports.php' => [
        'modules' => [
            'includes/modules/reports/report-filters.php',
            'includes/modules/reports/report-query.php',
            'includes/modules/reports/report-totals.php',
            'includes/modules/reports/report-adjustments.php',
            'includes/modules/reports/report-export.php',
            'assets/js/report-billing-review.js',
        ],
        'tests' => [
            'tests/reporting-flow-contract-test.php',
            'tests/billing-review-test.php',
            'tests/report-filter-contract-test.php',
            'tests/report-adjustment-contract-test.php',
            'tests/report-export-contract-test.php',
        ],
    ],
    'pages/admin/settings.php' => [
        'modules' => [
            'includes/modules/settings/settings-actions.php',
            'includes/modules/settings/settings-email.php',
            'includes/modules/settings/settings-updates.php',
            'includes/modules/settings/settings-workflow.php',
            'includes/modules/settings/settings-security.php',
            'includes/modules/settings/settings-view-model.php',
            'includes/components/admin-settings-tabs.php',
        ],
        'tests' => [
            'tests/admin-settings-surface-contract-test.php',
            'tests/security-debt-contract-test.php',
            'tests/email-routing-plus-address-contract-test.php',
            'tests/settings-action-contract-test.php',
            'tests/settings-email-contract-test.php',
            'tests/settings-update-contract-test.php',
        ],
    ],
];

foreach ($priority_targets as $page => $targets) {
    $assert(str_contains($inventory, "`{$page}`"), "Inventory must include priority page {$page}.");
    foreach ($targets['modules'] as $module) {
        $assert(str_contains($inventory, "`{$module}`"), "{$page} must name target module {$module}.");
    }
    foreach ($targets['tests'] as $test) {
        $assert(str_contains($inventory, "`{$test}`"), "{$page} must name contract test {$test}.");
    }
}

$assert(
    str_contains($inventory, 'Every extraction starts with a named contract test.'),
    'Inventory must enforce test-first extraction.'
);
$assert(
    str_contains($inventory, 'Route files keep request routing, authorization handoff, and final includes.'),
    'Inventory must define route-file responsibility.'
);

echo "Monolith exit inventory contract OK\n";
