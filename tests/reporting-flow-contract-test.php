<?php

$root = dirname(__DIR__);

$module = file_get_contents($root . '/includes/modules/reports/reporting-flow.php');
$billing = file_get_contents($root . '/includes/modules/reports/billing-review.php');
$bootstrap = file_get_contents($root . '/includes/modules/bootstrap.php');
$reports = file_get_contents($root . '/pages/admin/reports.php');
$billing_js = file_get_contents($root . '/assets/js/report-billing-review.js');
$builder = file_get_contents($root . '/pages/admin/report-builder.php');
$theme = file_get_contents($root . '/theme.css');

if ($module === false || $billing === false || $bootstrap === false || $reports === false || $builder === false || $theme === false) {
    fwrite(STDERR, "Unable to read reporting flow files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($bootstrap, '/reports/reporting-flow.php'), 'Module bootstrap must load reporting flow.');
$assert(str_contains($bootstrap, '/reports/billing-review.php'), 'Module bootstrap must load billing review.');
$assert(str_contains($module, 'function reporting_flow_steps'), 'Reporting flow steps helper is missing.');
$assert(str_contains($module, 'function reporting_flow_time_presets'), 'Reporting flow time presets helper is missing.');
$assert(str_contains($module, 'function reporting_flow_builder_url'), 'Reporting flow builder URL helper is missing.');
$assert(str_contains($billing, 'function billing_review_adjusted_rate'), 'Billing review adjustment helper is missing.');
$assert(str_contains($billing, 'function billing_review_rate_from_target_amount'), 'Billing review target total helper is missing.');
$assert(str_contains($billing, 'function billing_review_total_labels'), 'Billing review API total labels are missing.');
$assert(str_contains($billing, "'total_labels' => billing_review_total_labels"), 'Billing review payload must expose formatted total labels.');
$assert(str_contains($billing, "'discount_amount'"), 'Billing review must support amount discounts.');
$assert(str_contains($reports, 'reporting-flow-card reporting-flow-card--unified'), 'Reports page must render the unified report workspace.');
$assert(str_contains($reports, 'data-report-generation-card'), 'Reports page must mark the report generation form.');
$assert(str_contains($reports, 'data-report-unified-workspace'), 'Reports page must expose a single report workspace surface.');
$assert(str_contains($reports, 'data-report-preview'), 'Billing review must render a generated report preview surface.');
$assert(str_contains($reports, 'data-report-preview-empty'), 'Billing review must render a clear preview empty state before a client is selected.');
$assert(str_contains($reports, '$page_header_suppressed = true;'), 'Reports page must suppress the duplicate page header for laptop density.');
$assert(str_contains($reports, 'name="organizations[]"'), 'Billing review must submit the selected client as a report filter.');
$assert(str_contains($reports, 'name="tab" value="billing"'), 'Billing review must open the billing review mode.');
$assert(str_contains($reports, 'name="show_money" value="1"'), 'Billing review must show money columns.');
$assert(!str_contains($reports, 'reporting_flow_steps() as'), 'Reports page must not render a scattered side-step checklist.');
$assert(str_contains($reports, 'reporting_flow_builder_url('), 'Create report link must preserve selected client and period.');
$assert(str_contains($reports, 'class="report-preview-actions"'), 'Billing review actions must be aligned inside the report preview.');
$assert(str_contains($reports, 'class="report-preview-metrics"'), 'Billing review preview must show report metrics before publishing.');
$assert(str_contains($reports, 'billing_review_adjustment_actions()'), 'Detailed rows must use shared item adjustment actions.');
$assert(str_contains($reports, 'billing_review_bulk_adjustment_actions()'), 'Bulk adjustment form must use shared actions.');
$assert(str_contains($reports, 'name="bulk_discount_amount"'), 'Bulk billing review must allow amount discounts.');
$assert(str_contains($reports, 'data-entry-amount'), 'Detailed rows must expose row amounts for live totals.');
$assert(str_contains($reports, 'detail-billable-amount'), 'Detailed report must expose a live billable total.');
$assert(str_contains($reports, 'data-app-contract-surface="reporting-review"'), 'Detailed report must expose the reporting review contract surface.');
$assert(str_contains($reports, 'data-report-total="billable_amount"'), 'Detailed report must expose contract total mounts.');
$assert(str_contains($reports, 'data-report-entry-row'), 'Detailed report rows must expose contract row mounts.');
$assert(str_contains($reports, 'data-report-entry-field="rate"'), 'Detailed report rows must expose contract rate mounts.');
$assert($billing_js !== false && str_contains($billing_js, "selectedAction === 'discount_amount'"), 'Live totals must handle amount discounts.');
$assert(!str_contains($reports, 'class="report-page-toolbar report-page-toolbar--modes"'), 'Reports page must not render the old mode toolbar.');
$assert(!str_contains($reports, 'class="report-mode-link'), 'Reports page must not render report tabs as separate agendas.');
$assert(str_contains($reports, 'name="tab" value="billing"'), 'Unified report form must open the client report review path for admins.');
$assert(str_contains($reports, "elseif (\$tab === 'published')"), 'Old published report URL must remain compatible.');
$assert(str_contains($reports, "if (\$tab === 'time')"), 'Old time overview URL must remain compatible.');
$assert(str_contains($reports, 'class="report-filter-pills"'), 'Reports page must use shared filter pills.');
$assert(str_contains($reports, 'class="card-header report-filter-summary"'), 'Reports page must use the shared filter summary surface.');
$assert(str_contains($reports, 'class="report-summary-strip"'), 'Summary report totals must use the shared summary strip.');
$assert(str_contains($reports, 'class="report-detail-totals"'), 'Detailed report totals must use the shared detail totals strip.');
$assert(str_contains($reports, 'class="report-bulk-billing px-4 py-3 border-b"'), 'Bulk billing form must use the shared billing surface.');
$assert(str_contains($reports, 'class="range-preset-btn <?php echo $time_range === $preset_val ? \'is-active\' : \'\'; ?>"'), 'Range presets must use an active class, not inline styles.');
$assert(str_contains($bootstrap, '/reports/report-totals.php'), 'Module bootstrap must load report totals.');
$report_totals = file_get_contents($root . '/includes/modules/reports/report-totals.php');
$assert(str_contains($report_totals, 'function report_width_class'), 'Report totals module must normalize dynamic widths through CSS classes.');
$assert(str_contains($report_totals, 'function report_tone_class'), 'Report totals module must normalize chart colors through CSS classes.');
$assert(str_contains($report_totals, 'function report_billable_time_notice'), 'Report totals module must explain rounded billable time.');
$assert(str_contains($reports, '$is_client_report_review ? report_billable_time_notice'), 'Reports page must show billable time notice only in client report review.');
$assert(str_contains($reports, 'class="report-billing-note report-billing-note--'), 'Reports page must render the billable time notice near totals.');
$assert(str_contains($report_totals, "t('Why is billable time higher?')"), 'Billable time notice must explain higher billable time.');
$assert(str_contains($theme, '.report-billing-note'), 'Billable time notice styling is missing.');
$assert(str_contains($reports, 'report-mini-progress__bar--org <?php echo e(report_width_class($org_pct)); ?>'), 'Organization progress bars must use width classes.');
$assert(str_contains($reports, 'report-week-segment <?php echo e(report_width_class($seg_pct)); ?>'), 'Weekly stacked bars must use width classes.');
$assert(str_contains($reports, "cell.classList.toggle('is-hidden', !visible)"), 'Column picker must use CSS classes, not inline display writes.');
$assert(str_contains($builder, '$_GET[\'organization_id\']'), 'Report builder must accept a prefilled organization.');
$assert(str_contains($builder, '$_GET[\'date_from\']'), 'Report builder must accept a prefilled start date.');
$assert(str_contains($builder, '$_GET[\'date_to\']'), 'Report builder must accept a prefilled end date.');
$assert(str_contains($theme, '.reporting-flow-card'), 'Reporting flow styling is missing.');
$assert(str_contains($theme, '.report-preview-card'), 'Report preview styling is missing.');
$assert(str_contains($theme, '.report-preview-actions'), 'Report preview action styling is missing.');
$assert(str_contains($theme, '.report-preview-metrics'), 'Report preview metrics styling is missing.');
$assert(str_contains($theme, '.workflow-surface') && str_contains($theme, 'grid-template-columns: minmax(0, 1fr);'), 'Workflow surfaces must use a shrink-safe grid column.');
$assert(str_contains($theme, 'width: 100%;') && str_contains($theme, 'min-width: 0;'), 'Reporting flow card must shrink inside the workspace shell.');
$assert(str_contains($theme, '@media (max-width: 1280px)') && str_contains($theme, '.reporting-flow-side'), 'Reporting flow must collapse before it can overflow the workspace viewport.');
$assert(str_contains($theme, '.reporting-flow-step strong') && str_contains($theme, 'overflow-wrap: anywhere;'), 'Reporting flow step labels must wrap instead of pushing the card off screen.');
$assert(str_contains($theme, '.workflow-surface--reports.admin-legacy-page') && str_contains($theme, 'gap: 0.625rem;'), 'Reports surface must use the compact laptop density layer.');
$assert(str_contains($theme, '.workflow-surface--reports .report-filter-grid') && str_contains($theme, 'grid-template-columns: repeat(4, minmax(0, 1fr));'), 'Report filters must use a compact grid on desktop.');
$assert(str_contains($theme, '@media (min-width: 981px) and (max-width: 1280px)') && str_contains($theme, 'grid-template-columns: repeat(4, minmax(0, 1fr));'), 'Report flow steps must stay compact on MacBook-width screens.');
$assert(str_contains($theme, '.report-page-toolbar'), 'Report toolbar styling is missing.');
$assert(str_contains($theme, '.report-filter-summary'), 'Report filter summary styling is missing.');
$assert(str_contains($theme, '.report-metric__value'), 'Report metric styling is missing.');
$assert(str_contains($theme, '.report-bulk-billing'), 'Report bulk billing styling is missing.');
$assert(str_contains($theme, '.report-width--20'), 'Report width utility classes are missing.');
$assert(str_contains($theme, '.report-tone--7'), 'Report tone utility classes are missing.');
$assert(str_contains($theme, '.report-week-stack'), 'Report weekly stacked bar styling is missing.');
$assert(str_contains($theme, '.report-agent-dot--legend'), 'Report agent legend dots are missing.');
$assert(!str_contains($reports, 'style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;'), 'Report toolbar must not use inline layout styles.');
$assert(!str_contains($reports, 'style="display: inline-flex; align-items: center; gap: 3px; padding: 3px 8px;'), 'Report mini actions must use CSS classes.');
$assert(!str_contains($reports, 'style="display: flex; border: 1px solid var(--border-light); border-radius: 8px;'), 'Report total strips must use CSS classes.');
$assert(!str_contains($reports, 'btn.style.background'), 'Range preset state must use CSS classes.');
$assert(!str_contains($reports, 'reportCustomRange.style.display'), 'Custom date range visibility must use CSS classes.');
$assert(!str_contains($reports, 'style="'), 'Reports page must not use inline style attributes.');
$assert(!str_contains($reports, 'style.'), 'Reports page JS must not write inline styles.');
$assert(!str_contains($reports, 'weekly_agent_color_map'), 'Weekly report colors must use CSS tone classes.');

echo "Reporting flow contract OK\n";
