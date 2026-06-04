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

<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="queue-page-kicker"><?php echo e(t('Work queues')); ?></div>
            <h1 class="queue-page-title"><?php echo e(t('Work')); ?></h1>
            <p class="queue-page-copy"><?php echo e(t('Start with the queue that needs attention now.')); ?></p>
        </div>
        <a href="<?php echo e(url('new-ticket')); ?>" class="btn btn-primary btn-sm">
            <?php echo get_icon('plus', 'w-4 h-4 mr-1'); ?><?php echo e(t('New ticket')); ?>
        </a>
    </div>

    <div class="work-shell">
        <nav class="work-queue-list" aria-label="<?php echo e(t('Work queues')); ?>">
            <?php foreach ($queue_summary as $key => $queue): ?>
                <?php $definition = $queue['definition']; ?>
                <a href="<?php echo e($work_queue_url($key)); ?>" class="work-queue-link <?php echo $key === $queue_key ? 'is-active' : ''; ?>" <?php echo $key === $queue_key ? 'aria-current="page"' : ''; ?>>
                    <div>
                        <div class="work-queue-title"><?php echo e(t($definition['label'])); ?></div>
                        <div class="work-queue-desc"><?php echo e(t($definition['description'])); ?></div>
                    </div>
                    <span class="work-queue-count"><?php echo (int) ($queue['count'] ?? 0); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="work-panel">
            <div class="work-panel__head">
                <div>
                    <div class="work-panel__eyebrow"><?php echo e(t('Current queue')); ?></div>
                    <div class="work-panel__title"><?php echo e(t($active_queue['definition']['label'] ?? 'My work')); ?></div>
                    <div class="queue-panel-copy"><?php echo e(t($active_queue['definition']['description'] ?? 'Tickets assigned to the current user.')); ?></div>
                </div>
                <a href="<?php echo e($work_tickets_url($queue_key)); ?>" class="btn btn-secondary btn-sm"><?php echo e(t('View all')); ?></a>
            </div>

            <?php if (empty($active_items)): ?>
                <div class="work-empty">
                    <div class="queue-empty-title"><?php echo e(t('No tickets here')); ?></div>
                    <div class="text-sm mt-1"><?php echo e(t('This queue is clear.')); ?></div>
                </div>
            <?php else: ?>
                <div class="work-ticket-list">
                    <?php foreach ($active_items as $ticket): ?>
                        <?php
                        $status_color = $ticket['status_color'] ?? '#64748b';
                        $assignee = trim((string) (($ticket['assignee_first_name'] ?? '') . ' ' . ($ticket['assignee_last_name'] ?? '')));
                        $org = trim((string) ($ticket['organization_name'] ?? ''));
                        ?>
                        <a href="<?php echo e(ticket_url($ticket)); ?>" class="work-ticket">
                            <div class="min-w-0">
                                <div class="work-ticket__meta">
                                    <span class="work-ticket__dot" style="background: <?php echo e($status_color); ?>"></span>
                                    <span><?php echo e(get_ticket_code((int) $ticket['id'])); ?></span>
                                    <span><?php echo e($ticket['status_name'] ?? ''); ?></span>
                                    <?php if ($org !== ''): ?><span><?php echo e($org); ?></span><?php endif; ?>
                                </div>
                                <div class="work-ticket__title"><?php echo e($ticket['title'] ?? ''); ?></div>
                            </div>
                            <div class="work-ticket__side">
                                <?php if ($assignee !== ''): ?>
                                    <div><?php echo e($assignee); ?></div>
                                <?php endif; ?>
                                <div><?php echo e(format_date($ticket['updated_at'] ?? $ticket['created_at'] ?? '', 'd.m.Y')); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
