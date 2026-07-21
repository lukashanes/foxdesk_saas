<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/report-page-source.php';

if (!function_exists('t')) {
    function t(string $value): string { return $value; }
}
if (!function_exists('is_admin')) {
    function is_admin(): bool { return true; }
}
require_once $root . '/includes/modules/reports/report-page-view-model.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        fwrite(STDERR, 'Unable to read ' . $path . PHP_EOL);
        exit(1);
    }
    return $contents;
};

$route = $read('pages/admin/reports.php');
$controller = $read('includes/modules/reports/report-page-controller.php');
$view_model = $read('includes/modules/reports/report-page-view-model.php');
$renderer = $read('includes/modules/reports/report-page-render.php');
$page_view = $read('includes/modules/reports/views/page.php');
$billing_view = $read('includes/modules/reports/views/billing.php');
$report_js = $read('assets/js/report-page.js');
$bootstrap = $read('includes/modules/bootstrap.php');

$route_lines = substr_count($route, "\n") + 1;
$assert($route_lines < 100, 'Reports route must remain a thin composition boundary.');
$assert(str_contains($route, 'report_admin_page_context($_GET, $_POST, $_SERVER)'), 'Reports route must delegate request orchestration.');
$assert(str_contains($route, 'report_render_admin_page($report_context)'), 'Reports route must delegate rendering.');

foreach ([
    'report_filter_state_from_request($request, is_admin())',
    'current_tenant_id()',
    'report_query_time_entries($report_filter_state',
    'report_ticket_detail_model($entries',
    'report_export_csv_if_requested($request',
    'reporting_flow_builder_url_from_filter_state($report_filter_state)',
] as $needle) {
    $assert(str_contains($controller, $needle), 'Report controller boundary missing: ' . $needle);
}
$assert(str_contains($controller, 'AND tenant_id = ?'), 'Agent reads must remain tenant-scoped.');
$assert(str_contains($view_model, 'function report_page_active_filters'), 'Filter presentation must live in the report view model.');
$assert(str_contains($view_model, 'function report_page_weekly_model'), 'Weekly presentation must live in the report view model.');
$assert(str_contains($renderer, "'filters', 'time', 'weekly', 'billing', 'worklog', 'rates', 'published', 'entry-modal'"), 'Report partials must be explicitly allowlisted.');

foreach ([
    'data-report-generation-card',
    'data-report-unified-workspace',
    'data-report-create-form',
    'data-report-client-select',
    'data-report-period-select',
] as $needle) {
    $assert(str_contains($page_view, $needle), 'Unified reporting flow missing: ' . $needle);
}
foreach ([
    'data-app-contract-surface="reporting-review"',
    'data-report-entry-row',
    'data-report-total="billable_amount"',
    '$show_money',
    '$has_cost_data',
    '$billing_ticket_details',
] as $needle) {
    $assert(str_contains($billing_view, $needle), 'Billing review behavior missing: ' . $needle);
}
foreach ([
    'initCreateLinks()',
    'initChipSelects()',
    'initColumnPicker()',
    'initBulkSelection()',
    'data-report-edit-entry',
] as $needle) {
    $assert(str_contains($report_js, $needle), 'Extracted report JS behavior missing: ' . $needle);
}

$surface = report_page_source_bundle($root);
$assert(!preg_match('/on(?:click|change|submit|input)=/i', $surface), 'Report views must not own inline browser handlers.');
$assert(!str_contains($route, 'db_fetch_'), 'Reports route must not query the database directly.');
$assert(!str_contains($page_view, 'db_fetch_'), 'Reports render coordinator must not query the database directly.');
$assert(str_contains($bootstrap, '/reports/report-page-controller.php'), 'Module bootstrap must load the report page controller.');
$assert(str_contains($bootstrap, '/reports/report-page-view-model.php'), 'Module bootstrap must load the report page view model.');
$assert(str_contains($bootstrap, '/reports/report-page-render.php'), 'Module bootstrap must load the report renderer.');

$selected_client = report_page_selected_client([0, 23], [
    ['id' => 23, 'name' => 'Aenze'],
    ['id' => 24, 'name' => 'FoxDesk'],
]);
$assert($selected_client === ['id' => 23, 'name' => 'Aenze'], 'Selected client model must ignore the unassigned sentinel and preserve one client.');
$assert(report_page_selected_client([23, 24], [])['id'] === null, 'Publishing flow must require exactly one selected client.');

$financial_columns = report_page_billing_columns(true, true, true, true);
$assert(isset($financial_columns['amount'], $financial_columns['cost'], $financial_columns['profit']), 'Admin financial visibility must preserve amount, cost and profit columns.');
$assert(report_page_billing_columns(false, true, true, true) === [], 'Non-admin report columns must not expose billing controls.');

echo "Report page extraction contract OK\n";
