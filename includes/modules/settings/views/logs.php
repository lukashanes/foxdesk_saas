<?php
/** Focused settings section partial. Variables are supplied by settings-page-view-model.php. */
?>
        <?php
        $page_num = max(1, (int) ($_GET['p'] ?? 1));
        $per_page = 50;
        $offset = ($page_num - 1) * $per_page;

        $debug_log_available = false;
        $total_logs = 0;
        $total_pages = 1;
        $logs = [];
        try {
            $debug_log_available = (bool) db_fetch_one("SHOW TABLES LIKE 'debug_log'");
            if ($debug_log_available) {
                $total_logs = (int) (db_fetch_one("SELECT COUNT(*) as c FROM debug_log")['c'] ?? 0);
                $total_pages = (int) ceil(max(1, $total_logs) / $per_page);
                $logs = db_fetch_all("
                SELECT l.*, u.first_name, u.last_name, u.email
                FROM debug_log l
                LEFT JOIN users u ON l.user_id = u.id
                ORDER BY l.created_at DESC
                LIMIT ? OFFSET ?
            ", [(int) $per_page, (int) $offset]);
            }
        } catch (Throwable $e) {
            $debug_log_available = false;
        }

        $security_log_available = security_log_table_exists();
        $security_logs = [];
        if ($security_log_available) {
            $security_logs = db_fetch_all("
            SELECT s.*, u.first_name, u.last_name, u.email
            FROM security_log s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.created_at DESC
            LIMIT 100
        ");
        }
        ?>
        <div class="space-y-3">
            <div class="admin-list-card admin-table">
                <div class="px-4 py-2 border-b flex justify-between items-center">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-muted">
                            <?php echo e(t('System Logs')); ?>
                        </h3>
                        <p class="text-[11px] text-theme-muted">
                            <?php echo e(t('Shows system and background process events.')); ?>
                        </p>
                    </div>
                    <form method="post"
                        onsubmit="return confirm('<?php echo e(t('Are you sure you want to clear all logs?')); ?>');">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="clear_logs" class="text-sm text-red-600 hover:text-red-800">
                            <?php echo get_icon('trash', 'mr-1'); ?>     <?php echo e(t('Clear all logs')); ?>
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase border-b bg-theme-secondary text-theme-muted">
                                <th class="px-6 py-3 font-medium"><?php echo e(t('Time')); ?></th>
                                <th class="px-6 py-3 font-medium"><?php echo e(t('Level')); ?></th>
                                <th class="px-6 py-3 font-medium"><?php echo e(t('Channel')); ?></th>
                                <th class="px-6 py-3 font-medium"><?php echo e(t('User')); ?></th>
                                <th class="px-6 py-3 font-medium"><?php echo e(t('Message')); ?></th>
                                <th class="px-6 py-3 font-medium w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php if (!$debug_log_available): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-theme-muted">
                                        <?php echo e(t('Debug log table is not available in this installation yet.')); ?>
                                    </td>
                                </tr>
                            <?php elseif (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-theme-muted">
                                        <?php echo e(t('No logs found.')); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="tr-hover text-sm">
                                        <td class="px-6 py-3 whitespace-nowrap text-theme-muted">
                                            <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php
                                            $badge_color = 'bg-gray-100 text-gray-800';
                                            switch ($log['level']) {
                                                case 'error':
                                                    $badge_color = 'bg-red-100 text-red-800';
                                                    break;
                                                case 'warning':
                                                    $badge_color = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'info':
                                                    $badge_color = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'debug':
                                                    $badge_color = 'bg-purple-100 text-purple-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 py-1 fd-rounded-pill text-xs font-medium <?php echo $badge_color; ?>">
                                                <?php echo strtoupper($log['level']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-theme-secondary">
                                            <?php echo e($log['channel']); ?>
                                        </td>
                                        <td class="px-6 py-3 text-theme-secondary">
                                            <?php if ($log['user_id']): ?>
                                                <span title="<?php echo e($log['email']); ?>">
                                                    <?php echo e(trim((string) (($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')))); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-theme-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 max-w-md truncate text-theme-primary"
                                            title="<?php echo e($log['message']); ?>">
                                            <?php echo e($log['message']); ?>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <?php if (!empty($log['context']) && $log['context'] !== '[]'): ?>
                                                <button onclick="showLogContext(this)" data-context="<?php echo e($log['context']); ?>"
                                                    class="text-blue-600 hover:text-blue-800">
                                                    <?php echo get_icon('eye'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($debug_log_available && $total_pages > 1): ?>
                    <div class="px-6 py-3 border-t flex justify-between items-center bg-theme-secondary">
                        <div class="text-xs text-theme-muted">
                            <?php echo t('Showing {start} to {end} of {total} entries', [
                                'start' => $offset + 1,
                                'end' => min($offset + $per_page, $total_logs),
                                'total' => $total_logs
                            ]); ?>
                        </div>
                        <div class="flex space-x-1">
                            <?php if ($page_num > 1): ?>
                                <a href="<?php echo url('admin', ['section' => 'settings', 'tab' => 'logs', 'p' => $page_num - 1]); ?>"
                                    class="settings-page-link px-3 py-1 border fd-rounded-control text-sm">
                                    &laquo; <?php echo e(t('Prev')); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($page_num < $total_pages): ?>
                                <a href="<?php echo url('admin', ['section' => 'settings', 'tab' => 'logs', 'p' => $page_num + 1]); ?>"
                                    class="settings-page-link px-3 py-1 border fd-rounded-control text-sm">
                                    <?php echo e(t('Next')); ?> &raquo;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($security_log_available): ?>
                <div class="admin-list-card admin-table">
                    <div class="px-6 py-3 border-b flex justify-between items-center">
                        <div>
                            <h3 class="font-semibold text-theme-primary">
                                <?php echo e(t('Security Audit Log')); ?>
                            </h3>
                            <p class="text-xs mt-1 text-theme-muted">
                                <?php echo e(t('Tracks who did what in sensitive operations.')); ?>
                            </p>
                        </div>
                        <form method="post" onsubmit="return confirm('<?php echo e(t('Clear security logs?')); ?>');">
                            <?php echo csrf_field(); ?>
                            <button type="submit" name="clear_security_logs" class="text-sm text-red-600 hover:text-red-800">
                                <?php echo get_icon('trash', 'mr-1'); ?>         <?php echo e(t('Clear security logs')); ?>
                            </button>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs uppercase border-b bg-theme-secondary text-theme-muted">
                                    <th class="px-6 py-3 font-medium"><?php echo e(t('Time')); ?></th>
                                    <th class="px-6 py-3 font-medium"><?php echo e(t('Event')); ?></th>
                                    <th class="px-6 py-3 font-medium"><?php echo e(t('User')); ?></th>
                                    <th class="px-6 py-3 font-medium"><?php echo e(t('IP Address')); ?></th>
                                    <th class="px-6 py-3 font-medium w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php if (empty($security_logs)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-theme-muted">
                                            <?php echo e(t('No security log entries yet.')); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($security_logs as $security_log): ?>
                                        <tr class="tr-hover text-sm">
                                            <td class="px-6 py-3 whitespace-nowrap text-theme-muted">
                                                <?php echo date('Y-m-d H:i:s', strtotime($security_log['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-3 text-theme-secondary">
                                                <?php echo e($security_log['event_type']); ?>
                                            </td>
                                            <td class="px-6 py-3 text-theme-secondary">
                                                <?php if (!empty($security_log['user_id'])): ?>
                                                    <?php
                                                    $security_user_name = trim((string) (($security_log['first_name'] ?? '') . ' ' . ($security_log['last_name'] ?? '')));
                                                    if ($security_user_name === '') {
                                                        $security_user_name = (string) ($security_log['email'] ?? ('#' . $security_log['user_id']));
                                                    }
                                                    ?>
                                                    <span
                                                        title="<?php echo e($security_log['email']); ?>"><?php echo e($security_user_name); ?></span>
                                                <?php else: ?>
                                                    <span class="text-theme-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-3 text-theme-secondary">
                                                <?php echo e($security_log['ip_address'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                <?php if (!empty($security_log['context'])): ?>
                                                    <button onclick="showLogContext(this)"
                                                        data-context="<?php echo e($security_log['context']); ?>"
                                                        class="text-blue-600 hover:text-blue-800">
                                                        <?php echo get_icon('eye'); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card card-body">
                    <h3 class="font-semibold mb-2 text-theme-primary"><?php echo e(t('Security Audit Log')); ?>
                    </h3>
                    <p class="text-sm text-theme-muted">
                        <?php echo e(t('Security log table is not available in this installation yet.')); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>


    <script>
        function showLogContext(btn) {
            try {
                var ctx = btn.getAttribute('data-context');
                var parsed = JSON.parse(ctx);
                alert(JSON.stringify(parsed, null, 2));
            } catch (e) {
                alert(btn.getAttribute('data-context'));
            }
        }
    </script>
