<?php

$root = dirname(__DIR__);
$tenant = file_get_contents($root . '/includes/tenant-functions.php');
$recurring = file_get_contents($root . '/includes/recurring-task-functions.php');
$reports = file_get_contents($root . '/includes/report-functions.php');
$recurringCli = file_get_contents($root . '/bin/process-recurring-tasks.php');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($tenant, 'function tenant_run_in_context'), 'Shared tenant background context helper is missing.');
$assert(str_contains($tenant, 'finally'), 'Tenant background context must be restored in a finally block.');
$assert(str_contains($recurring, 'SELECT DISTINCT tenant_id') && str_contains($recurring, 'process_recurring_tasks($row_tenant_id)'), 'Recurring tasks are not orchestrated per tenant.');
$assert(substr_count($recurring, "tenant_filter = ' AND tenant_id = ?'") >= 2, 'Recurring due and resume queries must both bind tenant_id.');
$assert(str_contains($recurring, "user_tenant_filter = ' AND tenant_id = ?'"), 'Recurring fallback admin lookup is not tenant-scoped.');
$assert(str_contains($reports, 'process_scheduled_reports($row_tenant_id)'), 'Scheduled reports are not orchestrated per tenant.');
$assert(str_contains($reports, "tenant_filter = ' AND rt.tenant_id = ?'"), 'Scheduled report reads are not tenant-scoped.');
$assert(str_contains($reports, 'WHERE rt.id = ?{$tenant_filter}'), 'Internal report template lookup is not tenant-scoped.');
$assert(str_contains($recurringCli, "require_once BASE_PATH . '/includes/tenant-functions.php';"), 'Recurring CLI does not load SaaS tenant helpers.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Background job tenant isolation contract OK\n";
