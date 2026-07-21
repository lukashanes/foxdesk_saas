<?php
// Unified reports page composition. Business data is prepared by report_admin_page_context().
$report_context = $context;
$report_partial = match ($tab) {
    'billing' => 'billing',
    'weekly' => 'weekly',
    'worklog' => 'worklog',
    'rates' => 'rates',
    'published' => 'published',
    default => 'time',
};
?>
<?php
$page_header_title = $page_title;
$page_header_suppressed = true;
include BASE_PATH . '/includes/components/page-header.php';
?>

<div class="workflow-surface workflow-surface--reports admin-legacy-page" data-core-workflow-surface="reports">
    <section class="reporting-flow-card reporting-flow-card--unified" data-report-generation-card data-report-unified-workspace>
        <div class="reporting-flow-main">
            <div class="reporting-flow-heading">
                <h2><?php echo e(t('Reports')); ?></h2>
                <p><?php echo e(t('Choose the data once, review ticket details, then share or export.')); ?></p>
            </div>
            <?php if (is_admin()): ?>
            <form method="GET" action="index.php" class="reporting-flow-form" data-report-create-form>
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="section" value="reports">
                <input type="hidden" name="tab" value="billing">
                <input type="hidden" name="show_money" value="1">
                <label>
                    <span><?php echo e(t('Client')); ?></span>
                    <select name="organizations[]" class="form-select" required data-report-client-select>
                        <option value="" disabled <?php echo empty($selected_orgs) ? 'selected' : ''; ?>>
                            <?php echo e(t('Choose client')); ?>
                        </option>
                        <?php foreach ($organizations as $organization): ?>
                            <option value="<?php echo (int) $organization['id']; ?>"
                                <?php echo in_array((int) $organization['id'], $selected_orgs, true) ? 'selected' : ''; ?>>
                                <?php echo e($organization['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php echo e(t('Period')); ?></span>
                    <select name="time_range" class="form-select" data-report-period-select>
                        <?php foreach (reporting_flow_time_presets() as $preset => $label): ?>
                            <option value="<?php echo e($preset); ?>" <?php echo $time_range === $preset ? 'selected' : ''; ?>>
                                <?php echo e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo get_icon('search', 'w-3.5 h-3.5'); ?><?php echo e(t('Update preview')); ?>
                </button>
                <?php if ($selected_flow_org !== null && !empty($entries)): ?>
                <a href="<?php echo e($report_builder_url); ?>" class="btn btn-secondary btn-sm" data-report-create-link>
                    <?php echo get_icon('file-text', 'w-3.5 h-3.5'); ?><?php echo e(t('Create client report')); ?>
                </a>
                <?php endif; ?>
                <a href="<?php echo url('admin', ['section' => 'reports-list']); ?>" class="btn btn-ghost btn-sm">
                    <?php echo get_icon('list', 'w-3.5 h-3.5'); ?><?php echo e(t('Report history')); ?>
                </a>
            </form>
            <?php else: ?>
            <div class="reporting-flow-agent-note">
                <?php echo e(t('Review your worked time by period, ticket, and client.')); ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (($tab === 'time' || $tab === 'billing') && !empty($entries)): ?>
    <div class="report-page-toolbar">
        <div class="report-actions">
            <a href="index.php?<?php echo http_build_query($report_export_params); ?>" class="report-mini-action" title="<?php echo e(t('Export CSV')); ?>">
                <?php echo get_icon('download', 'w-3 h-3 inline-block'); ?><?php echo e(t('Export CSV')); ?>
            </a>
            <button type="button" data-report-print class="report-mini-action" title="<?php echo e(t('Print')); ?>">
                <?php echo get_icon('print', 'w-3 h-3 inline-block'); ?><?php echo e(t('Print')); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$time_tracking_available): ?>
        <div class="card card-body text-theme-secondary">
            <?php echo e(t('Time tracking is not available.')); ?>
        </div>
    <?php else: ?>
        <?php if ($tab !== 'published'): ?>
            <?php report_render_partial('filters', $report_context); ?>
        <?php endif; ?>
        <?php report_render_partial($report_partial, $report_context); ?>
    <?php endif; ?>
</div>

<?php if ($tab === 'billing' || $tab === 'worklog'): ?>
    <?php report_render_partial('entry-modal', $report_context); ?>
<?php endif; ?>

<script>
window.FoxDeskReportPageConfig = <?php echo json_encode(
    $report_page_config,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
); ?>;
</script>
<script src="assets/js/chip-select.js"></script>
<script src="assets/js/report-page.js"></script>
<script src="assets/js/report-billing-review.js"></script>
<script src="assets/js/report-time-delete.js"></script>
