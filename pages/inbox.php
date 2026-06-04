<?php
/**
 * Inbox page.
 *
 * Triage surface for new, replied, and email-created tickets. This is separate
 * from the ticket registry and from analytics/dashboard.
 */

if (!is_admin() && !is_agent()) {
    redirect('work');
}

$page_title = t('Inbox');
$page = 'inbox';
$user = current_user();

$queue_key = trim((string) ($_GET['queue'] ?? 'triage'));
$queue_definitions = inbox_queue_definitions();
if (!isset($queue_definitions[$queue_key])) {
    $queue_key = 'triage';
}

$inbox_summary = inbox_summary($user, 12);
$active_queue = $inbox_summary[$queue_key] ?? ($inbox_summary['triage'] ?? reset($inbox_summary));
$active_items = $active_queue['items'] ?? [];

$inbox_queue_url = static function (string $key): string {
    return url('inbox', $key === 'triage' ? [] : ['queue' => $key]);
};

$inbox_ticket_list_url = static function (string $key): string {
    switch ($key) {
        case 'customer_replies':
            return url('tickets', ['sort' => 'last_updated']);
        case 'email_imports':
        case 'triage':
        default:
            return url('tickets', ['work_view' => 'open']);
    }
};

require_once BASE_PATH . '/includes/header.php';
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="queue-page-kicker"><?php echo e(t('Triage')); ?></div>
            <h1 class="queue-page-title"><?php echo e(t('Inbox')); ?></h1>
            <p class="queue-page-copy"><?php echo e(t('Decide what should be assigned, started, merged, or closed.')); ?></p>
        </div>
        <a href="<?php echo e(url('new-ticket')); ?>" class="btn btn-primary btn-sm">
            <?php echo get_icon('plus', 'w-4 h-4 mr-1'); ?><?php echo e(t('New ticket')); ?>
        </a>
    </div>

    <div class="inbox-shell">
        <nav class="inbox-list" aria-label="<?php echo e(t('Inbox queues')); ?>">
            <?php foreach ($inbox_summary as $key => $queue): ?>
                <?php $definition = $queue['definition']; ?>
                <a href="<?php echo e($inbox_queue_url($key)); ?>" class="inbox-queue <?php echo $key === $queue_key ? 'is-active' : ''; ?>" <?php echo $key === $queue_key ? 'aria-current="page"' : ''; ?>>
                    <div>
                        <div class="inbox-queue__title"><?php echo e(t($definition['label'])); ?></div>
                        <div class="inbox-queue__desc"><?php echo e(t($definition['description'])); ?></div>
                    </div>
                    <span class="inbox-queue__count"><?php echo (int) ($queue['count'] ?? 0); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="inbox-panel">
            <div class="inbox-panel__head">
                <div>
                    <div class="inbox-panel__eyebrow"><?php echo e(t('Current inbox')); ?></div>
                    <div class="inbox-panel__title"><?php echo e(t($active_queue['definition']['label'] ?? 'Triage')); ?></div>
                    <div class="queue-panel-copy"><?php echo e(t($active_queue['definition']['description'] ?? 'New or unassigned tickets that need a decision.')); ?></div>
                </div>
                <a href="<?php echo e($inbox_ticket_list_url($queue_key)); ?>" class="btn btn-secondary btn-sm"><?php echo e(t('View all')); ?></a>
            </div>

            <?php if (empty($active_items)): ?>
                <div class="inbox-empty">
                    <div class="queue-empty-title"><?php echo e(t('Inbox is clear')); ?></div>
                    <div class="text-sm mt-1"><?php echo e(t('No ticket needs triage in this queue.')); ?></div>
                </div>
            <?php else: ?>
                <div>
                    <?php foreach ($active_items as $ticket): ?>
                        <?php
                        $status_color = $ticket['status_color'] ?? '#64748b';
                        $org = trim((string) ($ticket['organization_name'] ?? ''));
                        $source = trim((string) ($ticket['source'] ?? ''));
                        ?>
                        <a href="<?php echo e(ticket_url($ticket)); ?>" class="inbox-ticket">
                            <div class="min-w-0">
                                <div class="inbox-ticket__meta">
                                    <span class="inbox-ticket__dot" style="background: <?php echo e($status_color); ?>"></span>
                                    <span><?php echo e(get_ticket_code((int) $ticket['id'])); ?></span>
                                    <span><?php echo e($ticket['status_name'] ?? ''); ?></span>
                                    <?php if ($org !== ''): ?><span><?php echo e($org); ?></span><?php endif; ?>
                                    <?php if ($source !== ''): ?><span><?php echo e($source); ?></span><?php endif; ?>
                                </div>
                                <div class="inbox-ticket__title"><?php echo e($ticket['title'] ?? ''); ?></div>
                            </div>
                            <div class="inbox-ticket__side">
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
