<?php
/**
 * Work page.
 *
 * Action-first view for daily support work. Dashboard keeps analytics; this page
 * shows the queues that need attention now.
 */

$page_title = t('Work');
$page = 'work';
$user = current_user();
$is_staff = is_admin() || is_agent();

$queue_key = trim((string) ($_GET['queue'] ?? 'mine'));
$queue_definitions = work_queue_definitions();
if (!isset($queue_definitions[$queue_key])) {
    $queue_key = 'mine';
}
if (($queue_definitions[$queue_key]['scope'] ?? '') === 'team' && !$is_staff) {
    $queue_key = 'mine';
}

$queue_summary = work_queue_summary($user, 6);
$active_queue = $queue_summary[$queue_key] ?? ($queue_summary['mine'] ?? reset($queue_summary));
$active_items = $active_queue['items'] ?? [];

$work_queue_url = static function (string $key): string {
    return url('work', $key === 'mine' ? [] : ['queue' => $key]);
};

$work_tickets_url = static function (string $key) use ($user): string {
    switch ($key) {
        case 'mine':
            return url('tickets', ['assigned_to' => (int) ($user['id'] ?? 0)]);
        case 'overdue':
            return url('tickets', ['due_date' => 'overdue']);
        case 'waiting':
            return url('tickets', ['work_view' => 'waiting']);
        case 'done_today':
            return url('tickets', ['work_view' => 'done', 'sort' => 'last_updated']);
        case 'unassigned':
        default:
            return url('tickets');
    }
};

require_once BASE_PATH . '/includes/header.php';
?>

<?php if (is_admin() && ($_GET['signup'] ?? '') === 'trial'): ?>
    <section class="db-onboarding db-onboarding--trial" data-signup-onboarding>
        <div class="db-onboarding__head">
            <div>
                <div class="db-onboarding__eyebrow"><?php echo e(t('Trial started')); ?></div>
                <h2 class="db-onboarding__title"><?php echo e(t('Your FoxDesk is ready')); ?></h2>
                <p class="db-onboarding__subtitle">
                    <?php echo e(t('Start with the essentials. You can change everything later.')); ?>
                </p>
            </div>
        </div>
        <div class="db-onboarding__steps">
            <article class="db-onboarding__step" data-step="workspace">
                <div class="db-onboarding__status" aria-hidden="true">
                    <?php echo get_icon('cog', 'w-4 h-4'); ?>
                </div>
                <div>
                    <h3 class="db-onboarding__step-title"><?php echo e(t('Workspace name')); ?></h3>
                    <p class="db-onboarding__step-text"><?php echo e(t('Make FoxDesk match your company.')); ?></p>
                </div>
                <a class="db-onboarding__link" href="<?php echo e(url('admin', ['section' => 'settings'])); ?>">
                    <?php echo e(t('Open settings')); ?>
                </a>
            </article>
            <article class="db-onboarding__step" data-step="team">
                <div class="db-onboarding__status" aria-hidden="true">
                    <?php echo get_icon('users', 'w-4 h-4'); ?>
                </div>
                <div>
                    <h3 class="db-onboarding__step-title"><?php echo e(t('Team')); ?></h3>
                    <p class="db-onboarding__step-text"><?php echo e(t('Invite your first teammate.')); ?></p>
                </div>
                <a class="db-onboarding__link" href="<?php echo e(url('admin', ['section' => 'users'])); ?>">
                    <?php echo e(t('Invite team')); ?>
                </a>
            </article>
            <article class="db-onboarding__step" data-step="billing">
                <div class="db-onboarding__status" aria-hidden="true">
                    <?php echo get_icon('credit-card', 'w-4 h-4'); ?>
                </div>
                <div>
                    <h3 class="db-onboarding__step-title"><?php echo e(t('Billing')); ?></h3>
                    <p class="db-onboarding__step-text"><?php echo e(t('Add billing before the trial ends.')); ?></p>
                </div>
                <a class="db-onboarding__link" href="<?php echo e(url('billing')); ?>">
                    <?php echo e(t('Add billing')); ?>
                </a>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php
workspace_render_queue_page([
    'title' => 'Work',
    'summary' => $queue_summary,
    'active_key' => $queue_key,
    'active_queue' => $active_queue,
    'items' => $active_items,
    'queue_url' => $work_queue_url,
    'view_all_url' => $work_tickets_url($queue_key),
    'primary_action' => workspace_surface_action(url('dashboard'), 'Analytics', 'chart-bar', 'btn btn-secondary btn-sm')
        . workspace_surface_action(url('new-ticket'), 'New ticket'),
    'row_options' => ['show_assignee' => true],
]);
?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
