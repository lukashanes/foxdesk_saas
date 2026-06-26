<?php
/**
 * Dashboard Page v2 — Classic Grid Dashboard with Full Interactivity
 * v0.3.13 — 3-column CSS grid, all stats clickable, widgets split into individual panels
 */

$page_title = t('Dashboard');
$page = 'dashboard';
$user = current_user();

$is_admin = is_admin();
$is_agent = is_agent();
$is_staff = $is_admin || $is_agent;

require_once BASE_PATH . '/includes/dashboard-functions.php';

$dashboard_tags = dashboard_tags_from_query($_GET);
$dashboard_data = get_dashboard_data($user, $dashboard_tags);
extract($dashboard_data);
$get_started = dashboard_get_started_state($user);

// Max items displayed per widget list (default 5). "View all" shown when exceeded.
$db_list_limit = 5;

$selected_agent_activity = dashboard_selected_agent_activity((int) ($_GET['agent_id'] ?? 0), $is_admin);
$selected_agent_id = $selected_agent_activity['selected_agent_id'];
$selected_agent = $selected_agent_activity['agent'];
$selected_agent_totals = $selected_agent_activity['totals'];
$selected_agent_entries = $selected_agent_activity['entries'];

require_once BASE_PATH . '/includes/header.php';
?>



<div class="flex items-center justify-between mb-3">
    <div></div>
    <div class="relative" id="dashboard-config-wrapper">
        <button type="button" id="dashboard-config-btn" class="btn btn-ghost btn-sm flex items-center gap-1.5 text-xs"
            onclick="toggleDashboardConfig()" title="<?php echo e(t('Customize dashboard')); ?>">
            <?php echo get_icon('cog', 'w-4 h-4'); ?>
            <span class="hidden sm:inline"><?php echo e(t('Customize')); ?></span>
        </button>
        <div id="dashboard-config-panel"
            class="hidden absolute right-0 top-full mt-2 w-72 py-3 fd-rounded-card shadow-lg z-40 theme-panel">
            <div class="px-4 pb-2 mb-2 border-b theme-border">
                <h4 class="text-sm font-semibold theme-text">
                    <?php echo e(t('Dashboard widgets')); ?></h4>
                <p class="text-xs mt-0.5 theme-text-muted">
                    <?php echo e(t('Show or hide sections. Drag to reorder.')); ?></p>
            </div>
            <div class="space-y-0.5 px-2" id="dashboard-config-list">
                <?php foreach ($section_order as $sec_id):
                    if (!in_array($sec_id, $default_order))
                        continue;
                    $is_hidden = in_array($sec_id, $hidden_sections);
                    $label = $section_labels[$sec_id] ?? $sec_id;
                    $cur_size = $widget_sizes[$sec_id] ?? 'full';
                    $can_resize = !in_array($sec_id, ['overview', 'recent']); // these stay full
                    ?>
                    <label class="flex items-center gap-3 px-2 py-2 fd-rounded-card cursor-pointer transition theme-text-secondary"
                        data-config-section="<?php echo e($sec_id); ?>">
                        <input type="checkbox" class="db-config-toggle fd-rounded-control" data-section="<?php echo e($sec_id); ?>"
                            <?php echo $is_hidden ? '' : 'checked'; ?> onchange="toggleDashboardWidget(this)">
                        <span class="text-sm flex-1"><?php echo e($label); ?></span>
                        <?php if ($can_resize): ?>
                            <button type="button" class="db-size-toggle" data-size-widget="<?php echo e($sec_id); ?>"
                                data-current-size="<?php echo e($cur_size); ?>"
                                onclick="event.preventDefault(); event.stopPropagation(); toggleWidgetSize('<?php echo e($sec_id); ?>')"
                                title="<?php echo $cur_size === 'half' ? t('1 column') : t('Full width'); ?>">
                                <?php echo $cur_size === 'half' ? '⇔' : '▣'; ?>
                            </button>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($get_started['visible'])): ?>
    <section class="db-onboarding" id="get-started" data-onboarding>
        <div class="db-onboarding__head">
            <div>
                <div class="db-onboarding__eyebrow"><?php echo e(t('Get started')); ?></div>
                <h2 class="db-onboarding__title"><?php echo e(t('Set up your FoxDesk workspace')); ?></h2>
                <p class="db-onboarding__subtitle">
                    <?php echo e(t('Finish the essentials first: ticket flow, email, team access, and billing state.')); ?>
                </p>
            </div>
            <div class="db-onboarding__progress">
                <button type="button" class="db-onboarding__dismiss" onclick="dismissGetStarted()" aria-label="<?php echo e(t('Hide get started')); ?>">
                    <?php echo e(t('Hide')); ?>
                </button>
                <div class="mt-2">
                    <?php echo e(t('{completed} of {total} done', [
                        'completed' => (string) ($get_started['completed'] ?? 0),
                        'total' => (string) ($get_started['total'] ?? 0),
                    ])); ?>
                </div>
                <div class="db-onboarding__bar" aria-hidden="true">
                    <span class="<?php echo e(dashboard_width_class($get_started['progress'] ?? 0)); ?>"></span>
                </div>
            </div>
        </div>
        <div class="db-onboarding__steps">
            <?php foreach (($get_started['steps'] ?? []) as $step): ?>
                <?php $step_done = !empty($step['done']); ?>
                <article class="db-onboarding__step" data-step="<?php echo e($step['key'] ?? ''); ?>">
                    <div class="db-onboarding__status <?php echo $step_done ? 'is-done' : ''; ?>" aria-hidden="true">
                        <?php echo $step_done ? get_icon('check', 'w-4 h-4') : get_icon('chevron-right', 'w-4 h-4'); ?>
                    </div>
                    <div>
                        <h3 class="db-onboarding__step-title"><?php echo e($step['label'] ?? ''); ?></h3>
                        <p class="db-onboarding__step-text"><?php echo e($step['description'] ?? ''); ?></p>
                    </div>
                    <a class="db-onboarding__link" href="<?php echo e($step['href'] ?? url('dashboard')); ?>">
                        <?php echo e($step['cta'] ?? t('Open')); ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($is_admin && $_foxdesk_update_info): ?>
    <!-- Update Available Card -->
    <div class="foxdesk-dashboard-update-card">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🚀</span>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold theme-text">
                    FoxDesk <?php echo e($_foxdesk_update_info['version']); ?>     <?php echo e(t('is available')); ?>
                </h3>
                <?php if (!empty($_foxdesk_update_info['released_at'])): ?>
                    <p class="text-xs mt-0.5 theme-text-muted">
                        <?php echo e(t('Released')); ?>: <?php echo e($_foxdesk_update_info['released_at']); ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($_foxdesk_update_info['changelog'])): ?>
                    <ul class="mt-1.5 text-xs space-y-0.5 theme-text-secondary">
                        <?php foreach (array_slice($_foxdesk_update_info['changelog'], 0, 5) as $change): ?>
                            <li>– <?php echo e($change); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <a href="<?php echo url('admin', ['section' => 'settings', 'tab' => 'system']); ?>#updates"
                class="btn btn-primary btn-sm flex-shrink-0">
                <?php echo e(t('Update now')); ?>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php if ($is_admin && $selected_agent_id > 0): ?>
    <section class="db-agent-activity" id="agent-activity">
        <?php if ($selected_agent): ?>
            <?php
            $agent_name = trim((string) (($selected_agent['first_name'] ?? '') . ' ' . ($selected_agent['last_name'] ?? '')));
            $agent_name = $agent_name !== '' ? $agent_name : (string) ($selected_agent['email'] ?? t('Agent'));
            ?>
            <div class="db-agent-activity__head">
                <div class="db-agent-activity__person">
                    <?php echo render_user_avatar($selected_agent, 'sm', 'db-avatar'); ?>
                    <div class="min-w-0">
                        <h2 class="db-agent-activity__title"><?php echo e($agent_name); ?></h2>
                        <div class="db-agent-activity__meta">
                            <?php echo e($selected_agent['email'] ?? ''); ?> · <?php echo e(ucfirst((string) ($selected_agent['role'] ?? 'agent'))); ?>
                            · <a href="<?php echo e(url('dashboard')); ?>" class="db-inline-link"><?php echo e(t('Close')); ?></a>
                        </div>
                    </div>
                </div>
                <div class="db-agent-activity__totals">
                    <div class="db-agent-activity__total"><span><?php echo e(t('Today')); ?></span><strong><?php echo format_duration_minutes($selected_agent_totals['today']); ?></strong></div>
                    <div class="db-agent-activity__total"><span><?php echo e(t('This week')); ?></span><strong><?php echo format_duration_minutes($selected_agent_totals['week']); ?></strong></div>
                    <div class="db-agent-activity__total"><span><?php echo e(t('This Month')); ?></span><strong><?php echo format_duration_minutes($selected_agent_totals['month']); ?></strong></div>
                </div>
            </div>
            <?php if (!empty($selected_agent_entries)): ?>
                <div class="overflow-x-auto">
                    <table class="db-agent-activity__table">
                        <thead>
                            <tr>
                                <th><?php echo e(t('What')); ?></th>
                                <th><?php echo e(t('Time')); ?></th>
                                <th><?php echo e(t('When')); ?></th>
                                <th><?php echo e(t('Source')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selected_agent_entries as $entry): ?>
                                <?php
                                $started_ts = strtotime((string) ($entry['started_at'] ?? ''));
                                $ended_ts = !empty($entry['ended_at']) ? strtotime((string) $entry['ended_at']) : null;
                                $ticket_link = ticket_url(['id' => (int) $entry['ticket_id'], 'hash' => $entry['ticket_hash'] ?? null]);
                                $summary = trim((string) ($entry['summary'] ?? ''));
                                ?>
                                <tr>
                                    <td>
                                        <a class="db-agent-activity__ticket" href="<?php echo e($ticket_link); ?>">
                                            <?php echo e($entry['ticket_title'] ?? ('#' . (int) $entry['ticket_id'])); ?>
                                        </a>
                                        <div class="db-agent-activity__summary">
                                            <?php echo e($summary !== '' ? $summary : ($entry['status_name'] ?? t('Time entry'))); ?>
                                        </div>
                                    </td>
                                    <td><?php echo format_duration_minutes((int) ($entry['actual_minutes'] ?? $entry['duration_minutes'] ?? 0)); ?></td>
                                    <td>
                                        <?php echo $started_ts ? e(date('d.m.Y H:i', $started_ts)) : '—'; ?>
                                        <div class="db-agent-activity__summary">
                                            <?php echo $ended_ts ? e(date('H:i', $ended_ts)) : e(t('Running')); ?>
                                        </div>
                                    </td>
                                    <td><?php echo e(ucfirst((string) ($entry['source'] ?? 'manual'))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="db-agent-activity__empty"><?php echo e(t('No time entries in the last 30 days.')); ?></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="db-agent-activity__empty">
                <?php echo e(t('Agent was not found or is not available in this workspace.')); ?>
                <a href="<?php echo e(url('dashboard')); ?>" class="db-inline-link"><?php echo e(t('Back to dashboard')); ?></a>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>


<!-- ••••••••••••••••••••••••••••••••••••••••••••••••••••••• -->
<!-- Dashboard Grid                                         -->
<!-- ••••••••••••••••••••••••••••••••••••••••••••••••••••••• -->
<div class="db-grid">
    <?php foreach ($section_order as $section_id): ?>
        <?php
        $is_section_hidden = in_array($section_id, $hidden_sections);
        $hide_class = $is_section_hidden ? ' is-hidden' : '';
        $w_size = $widget_sizes[$section_id] ?? ($default_sizes[$section_id] ?? 'full');
        ?>

        <?php switch ($section_id):

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // OVERVIEW — KPI Strip (always full width)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'overview': ?>
                <div class="db-widget<?php echo $hide_class; ?>" data-widget="overview" data-size="full" draggable="true">
                    <div class="db-kpi-grid">
                        <?php
                        $kpi_href = url('tickets', $scope_link_params);
                        $kpi_color = 'blue';
                        $kpi_label = t('Open tickets');
                        $kpi_value = $workload_stats['open'];
                        $kpi_value_class = '';
                        $kpi_pulse = false;
                        $kpi_sub = $new_ticket_stats['today'] > 0 ? '+' . $new_ticket_stats['today'] . ' ' . e(t('Today')) : '';
                        include BASE_PATH . '/includes/components/kpi-card.php';

                        $kpi_href = $link_overdue;
                        $kpi_color = 'red';
                        $kpi_label = t('Overdue');
                        $kpi_value = $workload_stats['overdue'];
                        $kpi_value_class = $workload_stats['overdue'] > 0 ? 'text-red-600' : '';
                        $kpi_pulse = $workload_stats['overdue'] > 0;
                        $kpi_sub = e(t('needs attention'));
                        include BASE_PATH . '/includes/components/kpi-card.php';

                        $kpi_href = $link_due_today;
                        $kpi_color = 'amber';
                        $kpi_label = t('Due today');
                        $kpi_value = $workload_stats['due_today'];
                        $kpi_value_class = $workload_stats['due_today'] > 0 ? 'text-amber-600' : '';
                        $kpi_pulse = false;
                        $kpi_sub = e(t('Due this week')) . ': ' . $workload_stats['due_this_week'];
                        include BASE_PATH . '/includes/components/kpi-card.php';

                        $kpi_href = $link_new_today;
                        $kpi_color = 'blue';
                        $kpi_label = t('New today');
                        $kpi_value = $new_ticket_stats['today'];
                        $kpi_value_class = $new_ticket_stats['today'] > 0 ? 'text-blue-600' : '';
                        $kpi_sub = e(t('This week')) . ': ' . $new_ticket_stats['week'];
                        include BASE_PATH . '/includes/components/kpi-card.php';

                        $kpi_href = url('tickets');
                        $kpi_color = 'slate';
                        $kpi_label = t('All tickets');
                        $kpi_value = $total_visible_tickets;
                        $kpi_value_class = '';
                        $kpi_sub = e(t('Closed')) . ': ' . $closed_visible_tickets;
                        include BASE_PATH . '/includes/components/kpi-card.php';
                        ?>
                    </div>
                </div>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // NOTIFICATIONS — Recent activity feed
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'notifications': ?>
                <?php $ww_id = 'notifications';
                $ww_size = $w_size;
                $ww_hidden = $is_section_hidden;
                include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                <div class="db-section-header">
                    <h3 class="db-section-title flex items-center gap-2">
                        <?php echo get_icon('bell', 'w-4 h-4'); ?>
                        <?php echo e(t('Notifications')); ?>
                        <?php if ($dashboard_unread_count > 0): ?>
                            <span id="dbnotif-badge" class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold text-white bg-red-500 fd-rounded-pill"><?php echo $dashboard_unread_count; ?></span>
                        <?php endif; ?>
                    </h3>
                    <div class="flex items-center gap-2">
                        <?php if ($dashboard_unread_count > 0): ?>
                            <button type="button" onclick="dbNotifMarkAllRead()" class="dbnotif-btn dbnotif-btn--visible" title="<?php echo e(t('Mark all as read')); ?>">
                                <?php echo get_icon('check', 'w-4 h-4'); ?>
                            </button>
                        <?php endif; ?>
                        <a href="<?php echo url('notifications'); ?>" class="db-section-link"><?php echo e(t('View all')); ?></a>
                    </div>
                </div>

                <!-- Filter tabs -->
                <div class="dbnotif-filter-tabs">
                    <button type="button" class="dbnotif-filter-tab" data-dbnotif-filter="all"><?php echo e(t('All')); ?></button>
                    <button type="button" class="dbnotif-filter-tab active" data-dbnotif-filter="action"><?php echo e(t('Action required')); ?></button>
                    <button type="button" class="dbnotif-filter-tab" data-dbnotif-filter="info"><?php echo e(t('Informational')); ?></button>
                </div>

                <?php if (empty($dashboard_notifications)): ?>
                    <div id="dbnotif-empty" class="flex flex-col items-center justify-center py-8 text-center">
                        <?php echo get_icon('bell', 'w-8 h-8 mb-2 opacity-30'); ?>
                        <p class="text-sm theme-text-muted"><?php echo e(t('No notifications yet')); ?></p>
                        <p class="text-xs theme-text-muted mt-1"><?php echo e(t('Activity on your tickets will appear here')); ?></p>
                    </div>
                <?php else: ?>
                    <div id="dbnotif-list">
                    <?php
                    $grouped_dashboard = group_notifications($dashboard_notifications);
                    foreach (['today', 'yesterday', 'earlier'] as $_g) {
                        $grouped_dashboard[$_g] = group_by_ticket($grouped_dashboard[$_g]);
                    }
                    $group_labels = [
                        'today' => t('Today'),
                        'yesterday' => t('Yesterday'),
                        'earlier' => t('Earlier'),
                    ];
                    foreach (['today', 'yesterday', 'earlier'] as $grp):
                        if (empty($grouped_dashboard[$grp])) continue;
                        ?>
                        <div class="dbnotif-group-heading">
                            <?php echo e($group_labels[$grp]); ?>
                        </div>
                        <div class="space-y-0.5">
                            <?php foreach ($grouped_dashboard[$grp] as $tg):
                                $primary = $tg['primary'];
                                $others = $tg['others'];
                                $group_count = $tg['count'];

                                // ── Primary card variables ──
                                $notif = $primary;
                                $n_is_read = (bool) $notif['is_read'];
                                $n_text = format_notification_text($notif);
                                $n_time = notification_time_ago($notif['created_at']);
                                $n_ticket_id = $notif['ticket_id'] ? (int) $notif['ticket_id'] : null;
                                $n_data = $notif['data'] ?? [];
                                $n_comment_id = $n_data['comment_id'] ?? null;
                                $n_snippet = get_notification_snippet($notif);
                                $n_is_action = is_action_required_notification($notif['type'], $n_data);

                                $n_href = '#';
                                if ($n_ticket_id) {
                                    $n_href = 'index.php?page=ticket&id=' . $n_ticket_id . '&ref=dashboard&nid=' . (int)$notif['id'];
                                    if ($n_comment_id) $n_href .= '#comment-' . $n_comment_id;
                                }

                                $n_actor_name = trim(($notif['actor_first_name'] ?? '') . ' ' . ($notif['actor_last_name'] ?? ''));
                                $n_actor_avatar = $notif['actor_avatar'] ?? null;
                                $n_initials = mb_strtoupper(mb_substr($notif['actor_first_name'] ?? '?', 0, 1));
                                $type_meta = notification_type_meta((string) $notif['type']);
                                $type_icon = $type_meta['icon'];
                            ?>
                                <?php if ($group_count > 1): ?>
                                <!-- Ticket group: primary + collapsed children -->
                                <div class="dbnotif-ticket-group">
                                <?php endif; ?>
                                <div class="dbnotif-card <?php echo $n_is_read ? '' : 'unread'; ?>"
                                     id="dbnotif-<?php echo (int)$notif['id']; ?>"
                                     data-id="<?php echo (int)$notif['id']; ?>"
                                     data-action="<?php echo $n_is_action ? '1' : '0'; ?>">
                                    <a href="<?php echo $n_href; ?>" class="dbnotif-avatar <?php echo e(dashboard_avatar_class($n_actor_name)); ?>">
                                        <?php if ($n_actor_avatar && !str_starts_with($n_actor_avatar, 'data:')): ?>
                                            <span class="dbnotif-avatar-fallback"><?php echo e($n_initials); ?></span>
                                            <img src="<?php echo e(upload_url($n_actor_avatar)); ?>" alt=""
                                                 onerror="this.remove()">
                                        <?php elseif ($n_actor_avatar && str_starts_with($n_actor_avatar, 'data:')): ?>
                                            <img src="<?php echo e($n_actor_avatar); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo e($n_initials); ?>
                                        <?php endif; ?>
                                    </a>
                                    <a href="<?php echo $n_href; ?>" class="dbnotif-content">
                                        <div class="dbnotif-text"><?php echo e($n_text); ?></div>
                                        <?php if ($n_snippet): ?>
                                            <div class="dbnotif-snippet"><?php echo e($n_snippet); ?></div>
                                        <?php endif; ?>
                                        <div class="dbnotif-meta">
                                            <span class="<?php echo e(dashboard_notification_type_class((string) $notif['type'])); ?>">
                                                <?php echo get_icon($type_icon, 'w-3 h-3'); ?>
                                            </span>
                                            <span class="dbnotif-time"><?php echo e($n_time); ?></span>
                                            <?php if ($n_is_action): ?>
                                                <span class="dbnotif-action-badge"><?php echo e(t('Action required')); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div class="dbnotif-actions">
                                        <?php if ($group_count > 1): ?>
                                            <button type="button" class="dbnotif-group-toggle" title="<?php echo e(t('Show all')); ?>">
                                                <span class="dbnotif-group-count">+<?php echo $group_count - 1; ?></span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!$n_is_read): ?>
                                            <button type="button" class="dbnotif-btn"
                                                    onclick="event.stopPropagation();dbNotifMarkRead(<?php echo (int)$notif['id']; ?>)"
                                                    title="<?php echo e(t('Mark as read')); ?>">
                                                <?php echo get_icon('check', 'w-3.5 h-3.5'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?php echo $n_href; ?>" class="dbnotif-btn"
                                           title="<?php echo e(t('Open')); ?>">
                                            <?php echo get_icon('chevron-right', 'w-3.5 h-3.5'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php if ($group_count > 1): ?>
                                <div class="dbnotif-group-children">
                                    <?php foreach ($others as $child):
                                        $c_is_read = (bool) $child['is_read'];
                                        $c_text = format_notification_text($child);
                                        $c_time = notification_time_ago($child['created_at']);
                                        $c_ticket_id = $child['ticket_id'] ? (int) $child['ticket_id'] : null;
                                        $c_data = $child['data'] ?? [];
                                        $c_comment_id = $c_data['comment_id'] ?? null;
                                        $c_is_action = is_action_required_notification($child['type'], $c_data);

                                        $c_href = '#';
                                        if ($c_ticket_id) {
                                            $c_href = 'index.php?page=ticket&id=' . $c_ticket_id . '&ref=dashboard&nid=' . (int)$child['id'];
                                            if ($c_comment_id) $c_href .= '#comment-' . $c_comment_id;
                                        }

                                        $c_icon = 'bell';
                                        switch ($child['type']) {
                                            case 'new_ticket':       $c_icon = 'plus'; break;
                                            case 'new_comment':      $c_icon = 'comment'; break;
                                            case 'status_changed':   $c_icon = 'refresh-cw'; break;
                                            case 'assigned_to_you':  $c_icon = 'user-plus'; break;
                                            case 'priority_changed': $c_icon = 'exclamation-triangle'; break;
                                            case 'ticket_updated':   $c_icon = 'edit'; break;
                                            case 'due_date_reminder': $c_icon = 'clock'; break;
                                        }
                                    ?>
                                        <a href="<?php echo $c_href; ?>"
                                           class="dbnotif-child-card <?php echo $c_is_read ? '' : 'unread'; ?>"
                                           id="dbnotif-<?php echo (int)$child['id']; ?>"
                                           data-id="<?php echo (int)$child['id']; ?>">
                                            <span class="<?php echo e(dashboard_notification_type_class((string) $child['type'])); ?>">
                                                <?php echo get_icon($c_icon, 'w-3 h-3'); ?>
                                            </span>
                                            <span class="dbnotif-child-text"><?php echo e($c_text); ?></span>
                                            <span class="dbnotif-child-time"><?php echo e($c_time); ?></span>
                                            <?php if ($c_is_action): ?>
                                                <span class="dbnotif-action-badge"><?php echo e(t('Action required')); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="dbnotif-footer">
                        <a href="<?php echo url('notifications'); ?>"
                           class="dbnotif-footer__link">
                            <?php echo e(t('View all notifications')); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // NEW TICKETS — Separate widget (default: half)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'new_tickets': ?>
                <?php if ($is_staff): ?>
                    <?php $ww_id = 'new_tickets';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <?php $sh_icon = 'plus-circle';
                    $sh_title = t('New tickets');
                    $sh_link_url = $link_new_month;
                    $sh_link_label = t('View all');
                    include BASE_PATH . '/includes/components/section-header.php'; ?>
                    <div>
                        <?php $sr_href = $link_new_today;
                        $sr_label = t('Today');
                        $sr_value = $new_ticket_stats['today'];
                        $sr_value_class = $new_ticket_stats['today'] > 0 ? 'text-emerald-600' : '';
                        $sr_dot_color = '';
                        $sr_extra = '';
                        include BASE_PATH . '/includes/components/stat-row.php'; ?>
                        <?php $sr_href = $link_new_week;
                        $sr_label = t('This week');
                        $sr_value = $new_ticket_stats['week'];
                        $sr_value_class = '';
                        include BASE_PATH . '/includes/components/stat-row.php'; ?>
                        <?php $sr_href = $link_new_month;
                        $sr_label = t('This Month');
                        $sr_value = $new_ticket_stats['month'];
                        include BASE_PATH . '/includes/components/stat-row.php'; ?>
                    </div>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // DEADLINES — Separate widget (default: half)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'deadlines': ?>
                <?php if ($is_staff): ?>
                    <?php $ww_id = 'deadlines';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <?php $sh_icon = 'clock';
                    $sh_title = t('Deadlines');
                    $sh_link_url = $link_due_week;
                    $sh_link_label = t('View all');
                    include BASE_PATH . '/includes/components/section-header.php'; ?>
                    <div>
                        <?php $sr_href = $link_overdue;
                        $sr_label = t('Overdue');
                        $sr_value = $workload_stats['overdue'];
                        $sr_value_class = $workload_stats['overdue'] > 0 ? 'text-red-600' : '';
                        $sr_dot_color = '#ef4444';
                        $sr_extra = $workload_stats['overdue'] > 0 ? '<span class="db-pulse-dot"></span>' : '';
                        include BASE_PATH . '/includes/components/stat-row.php'; ?>
                        <?php $sr_href = $link_due_today;
                        $sr_label = t('Due today');
                        $sr_value = $workload_stats['due_today'];
                        $sr_value_class = $workload_stats['due_today'] > 0 ? 'text-amber-600' : '';
                        $sr_dot_color = '#f59e0b';
                        $sr_extra = '';
                        include BASE_PATH . '/includes/components/stat-row.php'; ?>
                        <?php $sr_href = $link_due_week;
                        $sr_label = t('Due this week');
                        $sr_value = $workload_stats['due_this_week'];
                        $sr_value_class = '';
                        $sr_dot_color = '#8b5cf6';
                        include BASE_PATH . '/includes/components/stat-row.php'; ?>
                    </div>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // MY TIME — Separate widget (default: half)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'my_time': ?>
                <?php if ($is_staff): ?>
                    <?php $ww_id = 'my_time';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <?php $sh_icon = 'clock';
                    $sh_title = t('My Time');
                    $sh_link_url = $link_reports;
                    $sh_link_label = t('Report');
                    include BASE_PATH . '/includes/components/section-header.php'; ?>
                    <div>
                        <?php
                        $time_periods = [
                            ['label' => t('Today'), 'value' => $my_time_today, 'target' => 480],
                            ['label' => t('This week'), 'value' => $my_time_week, 'target' => 2400],
                            ['label' => t('This Month'), 'value' => $my_time_month, 'target' => 9600],
                        ];
                        foreach ($time_periods as $tp):
                            $pct = $tp['target'] > 0 ? min(100, round(($tp['value'] / $tp['target']) * 100)) : 0;
                            ?>
                            <a href="<?php echo $link_reports; ?>" class="db-time-row">
                                <span class="db-time-label"><?php echo e($tp['label']); ?></span>
                                <span class="db-time-value"><?php echo format_duration_minutes($tp['value']); ?></span>
                                <div class="db-time-bar-wrap">
                                    <div class="db-time-bar <?php echo e(dashboard_width_class($pct)); ?>"></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // TEAM TIME — Separate widget (default: half, admin only)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'team_time': ?>
                <?php if ($is_admin): ?>
                    <?php $ww_id = 'team_time';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <?php $sh_icon = 'users';
                    $sh_title = t('Team');
                    $sh_link_url = $link_reports;
                    $sh_link_label = t('Report');
                    include BASE_PATH . '/includes/components/section-header.php'; ?>
                    <?php
                    // Pre-filter to only members with any time logged
                    $active_team = array_filter($team_members_time, function($m) {
                        return ((int)($m['today_mins'] ?? 0)) + ((int)($m['week_mins'] ?? 0)) + ((int)($m['month_mins'] ?? 0)) > 0;
                    });
                    $team_total = count($active_team);
                    ?>
                    <?php if (!empty($active_team)): ?>
                        <div class="overflow-x-auto">
                            <table class="db-team-table">
                                <thead>
                                    <tr>
                                        <th colspan="2"><?php echo e(t('Name')); ?></th>
                                        <th><?php echo e(t('Today')); ?></th>
                                        <th><?php echo e(t('This week')); ?></th>
                                        <th><?php echo e(t('This Month')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice(array_values($active_team), 0, $db_list_limit) as $member):
                                        $m_today = (int) ($member['today_mins'] ?? 0);
                                        $m_week = (int) ($member['week_mins'] ?? 0);
                                        $m_month = (int) ($member['month_mins'] ?? 0);
                                        $role_class = $member['role'] === 'admin' ? 'db-role-badge--admin' : 'db-role-badge--agent';
                                        $member_link = url('dashboard', ['agent_id' => (int) $member['id']]) . '#agent-activity';
                                        ?>
                                        <tr>
                                            <td class="db-avatar-cell">
                                                <?php echo render_user_avatar($member, 'sm', 'db-avatar'); ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo $member_link; ?>"
                                                    class="db-team-name font-medium text-sm"><?php echo e($member['first_name'] . ' ' . mb_substr($member['last_name'] ?? '', 0, 1) . '.'); ?></a>
                                                <span
                                                    class="db-role-badge <?php echo $role_class; ?>"><?php echo e(ucfirst($member['role'])); ?></span>
                                            </td>
                                            <td><?php echo $m_today > 0 ? format_duration_minutes($m_today) : '—'; ?></td>
                                            <td><?php echo $m_week > 0 ? format_duration_minutes($m_week) : '—'; ?></td>
                                            <td><?php echo $m_month > 0 ? format_duration_minutes($m_month) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="font-bold"><?php echo e(t('Total')); ?></td>
                                        <td><?php echo format_duration_minutes($team_time_today); ?></td>
                                        <td><?php echo format_duration_minutes($team_time_week); ?></td>
                                        <td><?php echo format_duration_minutes($team_time_month); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php if ($team_total > $db_list_limit): ?>
                            <div class="db-widget-viewall">
                                <a href="<?php echo $link_reports; ?>"><?php echo e(t('View all')); ?> (<?php echo $team_total; ?>)</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div>
                            <?php
                            $team_periods = [
                                ['label' => t('Today'), 'value' => $team_time_today, 'target' => 1440],
                                ['label' => t('This week'), 'value' => $team_time_week, 'target' => 7200],
                                ['label' => t('This Month'), 'value' => $team_time_month, 'target' => 28800],
                            ];
                            foreach ($team_periods as $tp):
                                $pct = $tp['target'] > 0 ? min(100, round(($tp['value'] / $tp['target']) * 100)) : 0;
                                ?>
                                <a href="<?php echo $link_reports; ?>" class="db-time-row">
                                    <span class="db-time-label"><?php echo e($tp['label']); ?></span>
                                    <span class="db-time-value"><?php echo format_duration_minutes($tp['value']); ?></span>
                                    <div class="db-time-bar-wrap">
                                        <div class="db-time-bar <?php echo e(dashboard_width_class($pct)); ?>"></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // STATUS CHART — Doughnut + Bar (default: half)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'status_chart': ?>
                <?php if ($is_admin): ?>
                    <div class="db-widget<?php echo $hide_class; ?>" data-widget="status_chart" data-size="<?php echo e($w_size); ?>" draggable="true">
                        <div class="card card-body">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                                <h3 class="db-section-title"><?php echo e(t('By status')); ?></h3>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="db-chart-toggle active"
                                        data-chart-toggle="status"><?php echo e(t('Status')); ?></button>
                                    <button type="button" class="db-chart-toggle"
                                        data-chart-toggle="priority"><?php echo e(t('Priority')); ?></button>
                                </div>
                            </div>
                            <div id="chart-status-panel" class="db-chart-wrap">
                                <?php if (empty($status_chart_values)): ?>
                                    <div class="db-chart-empty"><?php echo e(t('No tickets yet')); ?></div>
                                <?php else: ?>
                                    <canvas id="dashboard-status-chart"></canvas>
                                <?php endif; ?>
                            </div>
                            <div id="chart-priority-panel" class="db-chart-wrap hidden">
                                <?php if (empty($priority_chart_values)): ?>
                                    <div class="db-chart-empty"><?php echo e(t('No activity')); ?></div>
                                <?php else: ?>
                                    <canvas id="dashboard-priority-chart"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // DUE WEEK — Due This Week List (default: half)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'due_week': ?>
                <?php if ($is_staff): ?>
                    <?php $ww_id = 'due_week';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <?php $sh_icon = '';
                    $sh_title = t('Due this week');
                    $sh_link_url = url('tickets', array_merge($scope_link_params, ['due_date' => 'week']));
                    $sh_link_label = t('View all');
                    include BASE_PATH . '/includes/components/section-header.php'; ?>
                    <?php $due_total = count($due_week_tickets); ?>
                    <?php if (empty($due_week_tickets)): ?>
                        <div class="text-sm theme-text-muted"><?php echo e(t('No tickets due this week')); ?></div>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach (array_slice($due_week_tickets, 0, $db_list_limit) as $ticket):
                                $priority_name = (string) ($ticket['priority_name'] ?? t('Normal'));
                                $due_label = '';
                                $due_class = 'bg-gray-100 text-gray-700';
                                if (!empty($ticket['due_date'])) {
                                    $due_ts = strtotime($ticket['due_date']);
                                    $ticket_closed = !empty($ticket['is_closed']);
                                    $is_overdue = is_due_date_overdue($ticket['due_date'], $ticket_closed);
                                    $is_today = !$ticket_closed && !$is_overdue && $due_ts !== false && date('Y-m-d', $due_ts) === date('Y-m-d');
                                    if ($is_overdue) {
                                        $due_label = t('Overdue');
                                        $due_class = 'bg-red-100 text-red-700';
                                    } elseif ($is_today) {
                                        $due_label = t('Due today');
                                        $due_class = 'bg-orange-100 text-orange-700';
                                    } elseif ($due_ts !== false) {
                                        $due_label = format_date($ticket['due_date'], 'd.m.Y');
                                    }
                                }
                                ?>
                                <a href="<?php echo ticket_url($ticket); ?>" class="db-ticket">
                                    <div class="min-w-0">
                                        <div class="db-ticket__title"><?php echo e($ticket['title']); ?></div>
                                        <div class="db-ticket__meta">
                                            <span><?php echo get_ticket_code($ticket['id']); ?></span>
                                            <?php if (!empty($ticket['status_name'])): ?>
                                                <span>&middot;</span>
                                                <span class="<?php echo e(dashboard_status_text_class($ticket)); ?>"><?php echo e($ticket['status_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="<?php echo e(dashboard_priority_badge_class($priority_name)); ?>">
                                            <?php echo e($priority_name); ?>
                                        </span>
                                        <?php if ($due_label): ?>
                                            <span class="db-badge <?php echo $due_class; ?>"><?php echo e($due_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($due_total > $db_list_limit): ?>
                            <div class="db-widget-viewall">
                                <a href="<?php echo url('tickets', array_merge($scope_link_params, ['due_date' => 'week'])); ?>"><?php echo e(t('View all')); ?> (<?php echo $due_total; ?>)</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // TIMERS — Active Timers (default: half)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'timers': ?>
                <?php if (!empty($active_timers)): ?>
                    <div class="db-widget<?php echo $hide_class; ?>" data-widget="timers" data-size="<?php echo e($w_size); ?>" draggable="true">
                        <div class="card overflow-hidden">
                            <div class="card-header">
                                <h3 class="db-section-title flex items-center gap-2">
                                    <?php echo get_icon('play', 'w-4 h-4'); ?>
                                    <?php echo e(t('Active Timers')); ?>
                                </h3>
                                <span
                                    class="text-xs font-semibold px-2 py-0.5 fd-rounded-pill bg-green-100 text-green-700"><?php echo count($active_timers); ?></span>
                            </div>
                            <div class="divide-y theme-border">
                                <?php foreach ($active_timers as $timer):
                                    $timer_is_paused = is_timer_paused($timer);
                                    $elapsed_seconds = calculate_timer_elapsed($timer);
                                    $elapsed_minutes = max(0, (int) floor($elapsed_seconds / 60));
                                    ?>
                                    <div class="db-timer-row">
                                        <div class="min-w-0">
                                            <a href="<?php echo ticket_url($timer['ticket_id']); ?>"
                                                class="font-medium text-sm truncate block theme-text"><?php echo e($timer['ticket_title']); ?></a>
                                            <div class="text-xs mt-0.5 theme-text-muted">
                                                <?php echo get_ticket_code($timer['ticket_id']); ?></div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if ($timer_is_paused): ?>
                                                <span class="db-badge bg-yellow-100 text-yellow-700"><?php echo e(t('Paused')); ?></span>
                                            <?php else: ?>
                                                <span class="db-badge bg-green-100 text-green-700 timer-display"
                                                    data-started="<?php echo strtotime($timer['started_at']); ?>"
                                                    data-paused-seconds="<?php echo (int) ($timer['paused_seconds'] ?? 0); ?>"><?php echo format_duration_minutes($elapsed_minutes); ?></span>
                                            <?php endif; ?>
                                            <a href="<?php echo ticket_url($timer['ticket_id']); ?>"
                                                class="btn btn-primary btn-sm text-xs"><?php echo e(t('Log time')); ?></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // FOCUS — Recent / Assigned Tickets (always full)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'focus': ?>
                <?php if ($is_admin): ?>
                    <?php $ww_id = 'focus';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <?php $sh_icon = '';
                    $sh_title = t('Recent tickets');
                    $sh_link_url = url('tickets', $scope_link_params);
                    $sh_link_label = t('View all');
                    include BASE_PATH . '/includes/components/section-header.php'; ?>
                    <?php $recent_total = count($recent_tickets); ?>
                    <?php if (empty($recent_tickets)): ?>
                        <div class="text-sm theme-text-muted"><?php echo e(t('No tickets yet')); ?></div>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach (array_slice($recent_tickets, 0, $db_list_limit) as $ticket):
                                $priority_name = (string) ($ticket['priority_name'] ?? t('Normal'));
                                $due_label = '';
                                $due_class = 'bg-gray-100 text-gray-700';
                                if (!empty($ticket['due_date'])) {
                                    $due_ts = strtotime($ticket['due_date']);
                                    $ticket_closed = !empty($ticket['is_closed']);
                                    $is_overdue = is_due_date_overdue($ticket['due_date'], $ticket_closed);
                                    $is_today = !$ticket_closed && !$is_overdue && $due_ts !== false && date('Y-m-d', $due_ts) === date('Y-m-d');
                                    if ($is_overdue) {
                                        $due_label = t('Overdue');
                                        $due_class = 'bg-red-100 text-red-700';
                                    } elseif ($is_today) {
                                        $due_label = t('Due today');
                                        $due_class = 'bg-orange-100 text-orange-700';
                                    } elseif ($due_ts !== false) {
                                        $due_label = format_date($ticket['due_date'], 'd.m.');
                                    }
                                }
                                ?>
                                <a href="<?php echo ticket_url($ticket); ?>" class="db-ticket">
                                    <div class="min-w-0">
                                        <div class="db-ticket__title"><?php echo e($ticket['title']); ?></div>
                                        <div class="db-ticket__meta">
                                            <span><?php echo get_ticket_code($ticket['id']); ?></span>
                                            <?php if (!empty($ticket['status_name'])): ?>
                                                <span>&middot;</span>
                                                <span class="<?php echo e(dashboard_status_text_class($ticket)); ?>"><?php echo e($ticket['status_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($ticket['assignee_first_name'])): ?>
                                                <span>&middot;</span>
                                                <span><?php echo e($ticket['assignee_first_name'] . ' ' . mb_substr((string) $ticket['assignee_last_name'], 0, 1) . '.'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="<?php echo e(dashboard_priority_badge_class($priority_name)); ?>">
                                            <?php echo e($priority_name); ?>
                                        </span>
                                        <?php if ($due_label): ?>
                                            <span class="db-badge <?php echo $due_class; ?>"><?php echo e($due_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($recent_total > $db_list_limit): ?>
                            <div class="db-widget-viewall">
                                <a href="<?php echo url('tickets', $scope_link_params); ?>"><?php echo e(t('View all')); ?> (<?php echo $recent_total; ?>)</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php else: ?>
                    <?php $ww_id = 'focus';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <?php $sh_icon = '';
                    $sh_title = $is_staff ? t('Assigned to you') : t('Your recent tickets');
                    $sh_link_url = '';
                    $sh_link_label = '';
                    include BASE_PATH . '/includes/components/section-header.php'; ?>
                    <?php $focus_total = count($focus_tickets); ?>
                    <?php if (empty($focus_tickets)): ?>
                        <div class="text-sm theme-text-muted"><?php echo e(t('No tickets yet')); ?></div>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach (array_slice($focus_tickets, 0, $db_list_limit) as $focus_item):
                                $ticket = $focus_item['ticket'];
                                $priority_name = (string) ($ticket['priority_name'] ?? t('Normal'));
                                $due_label = '';
                                $due_class = 'bg-gray-100 text-gray-700';
                                if (!empty($ticket['due_date'])) {
                                    $due_ts = strtotime($ticket['due_date']);
                                    $ticket_closed = !empty($ticket['is_closed']);
                                    $is_overdue = is_due_date_overdue($ticket['due_date'], $ticket_closed);
                                    $is_today = !$ticket_closed && !$is_overdue && $due_ts !== false && date('Y-m-d', $due_ts) === date('Y-m-d');
                                    if ($is_overdue) {
                                        $due_label = t('Overdue');
                                        $due_class = 'bg-red-100 text-red-700';
                                    } elseif ($is_today) {
                                        $due_label = t('Due today');
                                        $due_class = 'bg-orange-100 text-orange-700';
                                    } elseif ($due_ts !== false) {
                                        $due_label = format_date($ticket['due_date'], 'd.m.');
                                    }
                                }
                                ?>
                                <a href="<?php echo ticket_url($ticket); ?>" class="db-ticket">
                                    <div class="min-w-0">
                                        <div class="db-ticket__title"><?php echo e($ticket['title']); ?></div>
                                        <div class="db-ticket__meta">
                                            <span><?php echo get_ticket_code($ticket['id']); ?></span>
                                            <?php if (!empty($ticket['status_name'])): ?>
                                                <span>&middot;</span>
                                                <span class="<?php echo e(dashboard_status_text_class($ticket)); ?>"><?php echo e($ticket['status_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="<?php echo e(dashboard_priority_badge_class($priority_name)); ?>">
                                            <?php echo e($priority_name); ?>
                                        </span>
                                        <?php if ($due_label): ?>
                                            <span class="db-badge <?php echo $due_class; ?>"><?php echo e($due_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($focus_total > $db_list_limit): ?>
                            <div class="db-widget-viewall">
                                <a href="<?php echo url('tickets', $scope_link_params); ?>"><?php echo e(t('View all')); ?> (<?php echo $focus_total; ?>)</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // RECENT — Non-staff ticket list (always full)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'recent': ?>
                <?php if (!$is_staff): ?>
                    <div class="db-widget<?php echo $hide_class; ?>" data-widget="recent" data-size="full" draggable="true">
                        <div class="card overflow-hidden">
                            <div class="card-header">
                                <h3 class="db-section-title"><?php echo e(t('Your recent tickets')); ?></h3>
                                <a href="<?php echo url('tickets', $scope_link_params); ?>"
                                    class="db-section-link"><?php echo e(t('View all')); ?></a>
                            </div>
                            <?php if (empty($recent_tickets)): ?>
                                <div class="empty-state">
                                    <?php echo get_icon('inbox', 'empty-state__icon'); ?>
                                    <p class="empty-state__title"><?php echo e(t('No tickets yet')); ?></p>
                                    <a href="<?php echo url('new-ticket'); ?>"
                                        class="btn btn-primary mt-4"><?php echo e(t('Create your first ticket')); ?></a>
                                </div>
                            <?php else: ?>
                                <?php $nonstaff_total = count($recent_tickets); ?>
                                <div class="divide-y theme-border">
                                    <?php foreach (array_slice($recent_tickets, 0, $db_list_limit) as $ticket):
                                        $priority_name = (string) ($ticket['priority_name'] ?? t('Normal'));
                                        $due_label = '';
                                        $due_class = 'bg-gray-100 text-gray-700';
                                        if (!empty($ticket['due_date'])) {
                                            $due_ts = strtotime($ticket['due_date']);
                                            $ticket_closed = !empty($ticket['is_closed']);
                                            $is_overdue = is_due_date_overdue($ticket['due_date'], $ticket_closed);
                                            $is_today = !$ticket_closed && !$is_overdue && $due_ts !== false && date('Y-m-d', $due_ts) === date('Y-m-d');
                                            if ($is_overdue) {
                                                $due_label = t('Overdue');
                                                $due_class = 'bg-red-100 text-red-700';
                                            } elseif ($is_today) {
                                                $due_label = t('Due today');
                                                $due_class = 'bg-orange-100 text-orange-700';
                                            } elseif ($due_ts !== false) {
                                                $due_label = format_date($ticket['due_date'], 'd.m.');
                                            }
                                        }
                                        ?>
                                        <a href="<?php echo ticket_url($ticket); ?>" class="block px-3 py-2.5 hover:bg-gray-50 transition no-underline">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <span class="<?php echo e(dashboard_status_dot_class($ticket)); ?>"></span>
                                                        <span class="font-medium truncate theme-text"><?php echo e($ticket['title']); ?></span>
                                                    </div>
                                                    <div class="text-xs mt-1 flex flex-wrap items-center gap-1.5 theme-text-muted">
                                                        <span><?php echo get_ticket_code($ticket['id']); ?></span>
                                                        <span>&middot;</span>
                                                        <span class="<?php echo e(dashboard_status_text_class($ticket)); ?>"><?php echo e($ticket['status_name']); ?></span>
                                                        <?php if (!empty($ticket['assignee_first_name'])): ?>
                                                            <span>&middot;</span>
                                                            <span><?php echo e($ticket['assignee_first_name'] . ' ' . mb_substr((string) $ticket['assignee_last_name'], 0, 1) . '.'); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                    <span class="<?php echo e(dashboard_priority_badge_class($priority_name)); ?>">
                                                        <?php echo e($priority_name); ?>
                                                    </span>
                                                    <?php if ($due_label): ?>
                                                        <span class="db-badge <?php echo $due_class; ?>"><?php echo e($due_label); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($nonstaff_total > $db_list_limit): ?>
                                    <div class="db-widget-viewall db-widget-viewall--padded">
                                        <a href="<?php echo url('tickets', $scope_link_params); ?>"><?php echo e(t('View all')); ?> (<?php echo $nonstaff_total; ?>)</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php break;

            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            // COMPLETED — Completed Tickets (default: half)
            // ••••••••••••••••••••••••••••••••••••••••••••••••••••••
            case 'completed': ?>
                <?php if ($is_staff): ?>
                    <?php $ww_id = 'completed';
                    $ww_size = $w_size;
                    $ww_hidden = $is_section_hidden;
                    include BASE_PATH . '/includes/components/widget-wrap-open.php'; ?>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="db-section-title flex items-center gap-2">
                            <?php echo get_icon('check-circle', 'w-4 h-4'); ?>
                            <?php echo e(t('Completed tickets')); ?>
                        </h3>
                        <div class="dbnotif-filter-tabs dbnotif-filter-tabs--flush">
                            <button type="button" class="dbnotif-filter-tab" onclick="filterCompletedTickets('today', this)"><?php echo e(t('Today')); ?></button>
                            <button type="button" class="dbnotif-filter-tab active" onclick="filterCompletedTickets('week', this)"><?php echo e(t('Week')); ?></button>
                            <button type="button" class="dbnotif-filter-tab" onclick="filterCompletedTickets('month', this)"><?php echo e(t('Month')); ?></button>
                        </div>
                    </div>
                    <?php if (empty($completed_tickets)): ?>
                        <div class="text-sm theme-text-muted" id="completed-tickets-list">
                            <div class="completed-empty"><?php echo e(t('No completed tickets')); ?></div>
                        </div>
                    <?php else: ?>
                        <div class="space-y-2" id="completed-tickets-list" data-limit="<?php echo $db_list_limit; ?>">
                            <?php foreach ($completed_tickets as $ticket):
                                $completed_date = date('Y-m-d', strtotime($ticket['updated_at']));
                                ?>
                                <a href="<?php echo ticket_url($ticket); ?>" class="db-ticket" data-completed-date="<?php echo e($completed_date); ?>">
                                    <div class="min-w-0">
                                        <div class="db-ticket__title"><?php echo e($ticket['title']); ?></div>
                                        <div class="db-ticket__meta">
                                            <span><?php echo get_ticket_code($ticket['id']); ?></span>
                                            <?php if (!empty($ticket['status_name'])): ?>
                                                <span>&middot;</span>
                                                <span class="<?php echo e(dashboard_status_text_class($ticket)); ?>"><?php echo e($ticket['status_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($ticket['assignee_first_name'])): ?>
                                                <span>&middot;</span>
                                                <span><?php echo e($ticket['assignee_first_name'] . ' ' . $ticket['assignee_last_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="text-xs theme-text-muted"><?php echo format_date($ticket['updated_at'], 'd.m.'); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="db-widget-viewall is-hidden" id="completed-viewall">
                            <a href="<?php echo url('tickets', ['status' => 'closed']); ?>"><?php echo e(t('View all')); ?> (<span id="completed-viewall-count">0</span>)</a>
                        </div>
                    <?php endif; ?>
                    <?php include BASE_PATH . '/includes/components/widget-wrap-close.php'; ?>
                <?php endif; ?>
                <?php break;

        endswitch; ?>
    <?php endforeach; ?>
</div>


<script>
    /* ─── Completed Tickets Filter ─── */
    function filterCompletedTickets(range, btn) {
        // Toggle active tab
        if (btn && btn.closest) {
            var tabs = btn.closest('.dbnotif-filter-tabs');
            if (tabs) tabs.querySelectorAll('.dbnotif-filter-tab').forEach(function(t) { t.classList.remove('active'); });
            btn.classList.add('active');
        }
        var list = document.getElementById('completed-tickets-list');
        if (!list) return;
        var items = list.querySelectorAll('[data-completed-date]');
        if (!items.length) return;
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
        var day = today.getDay(); // 0=Sun
        var weekStart = new Date(today);
        weekStart.setDate(today.getDate() - ((day === 0 ? 7 : day) - 1)); // Monday
        var monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
        var limit = parseInt(list.getAttribute('data-limit')) || 5;
        var visible = 0;
        var shown = 0;
        items.forEach(function(el) {
            var parts = el.getAttribute('data-completed-date').split('-');
            var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            var match = false;
            switch(range) {
                case 'today': match = d >= today; break;
                case 'yesterday': match = d >= yesterday && d < today; break;
                case 'week': match = d >= weekStart; break;
                case 'month': match = d >= monthStart; break;
                default: match = true;
            }
            if (match) {
                visible++;
                el.classList.toggle('is-hidden', shown >= limit);
                shown++;
            } else {
                el.classList.add('is-hidden');
            }
        });
        var empty = list.querySelector('.completed-empty');
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'completed-empty text-sm theme-text-muted';
            empty.textContent = '<?php echo e(t('No completed tickets')); ?>';
            list.appendChild(empty);
        }
        empty.classList.toggle('is-hidden', visible !== 0);
        // "View all" footer
        var va = document.getElementById('completed-viewall');
        var vc = document.getElementById('completed-viewall-count');
        if (va) {
            va.classList.toggle('is-hidden', !(visible > limit));
            if (vc) vc.textContent = visible;
        }
    }
    document.addEventListener('DOMContentLoaded', function() { filterCompletedTickets('week'); });

    (function () {
        /* ─── Chart.js Lazy Load ─── */
        var chartJsLoaded = false;
        function loadChartJs(cb) {
            if (chartJsLoaded) { cb(); return; }
            var s = document.createElement('script');
            s.src = 'assets/vendor/chartjs/4.4.0/chart.umd.js?v=<?php echo APP_VERSION; ?>';
            s.onload = function () { chartJsLoaded = true; cb(); };
            document.head.appendChild(s);
        }

        var statusData = {
            labels: <?php echo json_encode($status_chart_labels, JSON_UNESCAPED_UNICODE); ?>,
            values: <?php echo json_encode($status_chart_values); ?>,
            colors: <?php echo json_encode($status_chart_colors); ?>,
            ids: <?php echo json_encode($status_chart_ids); ?>,
            baseUrl: <?php echo json_encode(url('tickets')); ?>
        };
        var priorityData = {
            labels: <?php echo json_encode($priority_chart_labels, JSON_UNESCAPED_UNICODE); ?>,
            values: <?php echo json_encode($priority_chart_values); ?>,
            colors: <?php echo json_encode($priority_chart_colors); ?>,
            ids: <?php echo json_encode($priority_chart_ids); ?>,
            baseUrl: <?php echo json_encode(url('tickets', $scope_link_params)); ?>
        };

        function initChartToggle() {
            var sp = document.getElementById('chart-status-panel');
            var pp = document.getElementById('chart-priority-panel');
            document.querySelectorAll('[data-chart-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('[data-chart-toggle]').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    if (btn.getAttribute('data-chart-toggle') === 'priority') {
                        if (sp) sp.classList.add('hidden');
                        if (pp) pp.classList.remove('hidden');
                    } else {
                        if (pp) pp.classList.add('hidden');
                        if (sp) sp.classList.remove('hidden');
                    }
                });
            });
        }

        function getChartLegendColor() {
            return getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#64748b';
        }

        function initStatusChart() {
            var c = document.getElementById('dashboard-status-chart');
            if (!c || typeof Chart === 'undefined' || !statusData.values.length) return;
            var legendColor = getChartLegendColor();
            new Chart(c.getContext('2d'), {
                type: 'doughnut',
                data: { labels: statusData.labels, datasets: [{ data: statusData.values, backgroundColor: statusData.colors, borderWidth: 0, hoverOffset: 6 }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, color: legendColor, font: { size: 11 } } } },
                    onClick: function (_, el) { if (el.length) { var i = el[0].index; if (statusData.ids[i]) window.location.href = statusData.baseUrl + '&status=' + encodeURIComponent(statusData.ids[i]); } }
                }
            });
        }

        function initPriorityChart() {
            var c = document.getElementById('dashboard-priority-chart');
            if (!c || typeof Chart === 'undefined' || !priorityData.values.length) return;
            var legendColor = getChartLegendColor();
            new Chart(c.getContext('2d'), {
                type: 'bar',
                data: { labels: priorityData.labels, datasets: [{ data: priorityData.values, backgroundColor: priorityData.colors, borderRadius: 8, borderSkipped: false }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: legendColor } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: legendColor }, grid: { color: 'rgba(128,128,128,0.1)' } }
                    },
                    onClick: function (_, el) { if (el.length) { var i = el[0].index; if (priorityData.ids[i]) window.location.href = priorityData.baseUrl + '&priority=' + encodeURIComponent(priorityData.ids[i]); } }
                }
            });
        }

        initChartToggle();
        if (document.getElementById('dashboard-status-chart') || document.getElementById('dashboard-priority-chart')) {
            loadChartJs(function () { initStatusChart(); initPriorityChart(); });
        }
        // Timer display updates are now handled globally by app-footer.js
    })();

    /* ─── Dashboard Config Panel ─── */
    function toggleDashboardConfig() {
        var p = document.getElementById('dashboard-config-panel');
        if (p) p.classList.toggle('hidden');
    }
    document.addEventListener('click', function (e) {
        var w = document.getElementById('dashboard-config-wrapper');
        if (w && !w.contains(e.target)) {
            var p = document.getElementById('dashboard-config-panel');
            if (p) p.classList.add('hidden');
        }
    });

    function toggleDashboardWidget(cb) {
        var sid = cb.dataset.section;
        var grid = document.querySelector('.db-grid');
        if (!grid) return;
        var el = grid.querySelector('.db-widget[data-widget="' + sid + '"]');
        if (el) el.classList.toggle('is-hidden', !cb.checked);
        saveDashboardLayout();
    }

    /* ─── Widget Size Toggle ─── */
    function toggleWidgetSize(widgetId) {
        var grid = document.querySelector('.db-grid');
        if (!grid) return;
        var el = grid.querySelector('.db-widget[data-widget="' + widgetId + '"]');
        if (!el) return;

        var current = el.getAttribute('data-size');
        var next = current === 'half' ? 'full' : 'half';
        el.setAttribute('data-size', next);

        // Update config panel button
        var btn = document.querySelector('[data-size-widget="' + widgetId + '"]');
        if (btn) {
            btn.setAttribute('data-current-size', next);
            btn.textContent = next === 'half' ? '⇔' : '▣';
            btn.title = next === 'half' ? '1 column' : 'Full width';
        }

        saveDashboardLayout();
    }

    /* ─── Drag & Drop (Grid v2) ─── */
    (function () {
        var grid = document.querySelector('.db-grid');
        if (!grid) return;
        var draggedEl = null;

        // Mark all inner links/imgs as non-draggable to prevent native drag stealing
        grid.querySelectorAll('.db-widget[draggable="true"] a, .db-widget[draggable="true"] img').forEach(function (el) {
            el.setAttribute('draggable', 'false');
        });

        function initDrag(widget) {
            widget.addEventListener('dragstart', function (e) {
                // Prevent drag from interactive elements (buttons, inputs, canvas)
                var origin = document.elementFromPoint(e.clientX, e.clientY);
                if (origin && origin.closest('button, input, select, textarea, canvas')) {
                    e.preventDefault();
                    return;
                }
                draggedEl = widget;
                widget.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', widget.dataset.widget || '');
            });

            widget.addEventListener('dragend', function () {
                draggedEl = null;
                widget.classList.remove('dragging');
                grid.querySelectorAll('.drag-over').forEach(function (el) { el.classList.remove('drag-over'); });
            });

            widget.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (draggedEl && draggedEl !== widget) widget.classList.add('drag-over');
            });

            widget.addEventListener('dragleave', function (e) {
                if (!widget.contains(e.relatedTarget)) widget.classList.remove('drag-over');
            });

            widget.addEventListener('drop', function (e) {
                e.preventDefault();
                widget.classList.remove('drag-over');
                if (!draggedEl || draggedEl === widget) return;
                var rect = widget.getBoundingClientRect();
                var midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    grid.insertBefore(draggedEl, widget);
                } else {
                    grid.insertBefore(draggedEl, widget.nextSibling);
                }
                saveDashboardLayout();
            });
        }

        grid.querySelectorAll('.db-widget').forEach(initDrag);
    })();

    /* ─── Save Layout (with sizes) ─── */
    function saveDashboardLayout() {
        var grid = document.querySelector('.db-grid');
        if (!grid) return;
        var order = [], hidden = [], sizes = {};
        grid.querySelectorAll('.db-widget').forEach(function (w) {
            var id = w.dataset.widget;
            if (id) {
                order.push(id);
                if (w.classList.contains('is-hidden')) hidden.push(id);
                sizes[id] = w.getAttribute('data-size') || 'full';
            }
        });
        fetch('index.php?page=api&action=save-dashboard-layout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ layout: { order: order, hidden: hidden, sizes: sizes } })
        }).catch(function () { });
    }

    window.dismissGetStarted = function() {
        var panel = document.querySelector('[data-onboarding]');
        if (panel) panel.classList.add('is-hidden');
        try {
            localStorage.setItem('foxdesk_get_started_hidden', '1');
        } catch (e) {}
    };

    (function() {
        var panel = document.querySelector('[data-onboarding]');
        if (!panel) return;
        var forceShow = new URLSearchParams(window.location.search).has('signup');
        try {
            if (!forceShow && localStorage.getItem('foxdesk_get_started_hidden') === '1') {
                panel.classList.add('is-hidden');
            }
        } catch (e) {}
    })();

    /* ─── Dashboard Notifications — mark-read, mark-all, filter ─── */
    window.dbNotifMarkRead = function(id) {
        fetch('index.php?page=api&action=mark-notification-read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'notification_id=' + id + '&csrf_token=' + encodeURIComponent(window.csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success) return;
            var el = document.getElementById('dbnotif-' + id);
            if (el) {
                el.classList.remove('unread');
                var btn = el.querySelector('.dbnotif-btn[onclick]');
                if (btn) btn.remove();
            }
            dbNotifUpdateBadge(-1);
            // Sync header dropdown
            var hdrEl = document.querySelector('.notif-item[data-id="' + id + '"]');
            if (hdrEl) hdrEl.classList.remove('unread');
            if (typeof updateBadge === 'function') updateBadge();
        });
    };

    window.dbNotifMarkAllRead = function() {
        fetch('index.php?page=api&action=mark-all-notifications-read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(window.csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success) return;
            document.querySelectorAll('.dbnotif-card.unread').forEach(function(el) {
                el.classList.remove('unread');
            });
            document.querySelectorAll('.dbnotif-child-card.unread').forEach(function(el) {
                el.classList.remove('unread');
            });
            document.querySelectorAll('.dbnotif-btn[onclick]').forEach(function(btn) {
                btn.remove();
            });
            dbNotifUpdateBadge(0);
            // Sync header dropdown
            document.querySelectorAll('.notif-item.unread').forEach(function(el) { el.classList.remove('unread'); });
            if (typeof updateBadge === 'function') updateBadge(0);
        });
    };

    function dbNotifUpdateBadge(delta) {
        var badge = document.getElementById('dbnotif-badge');
        if (!badge) return;
        var c = parseInt(badge.textContent) || 0;
        if (delta === 0) c = 0;
        else c = Math.max(0, c + delta);
        if (c <= 0) {
            badge.classList.add('is-hidden');
        } else {
            badge.textContent = c > 99 ? '99+' : c;
            badge.classList.remove('is-hidden');
        }
    }

    /* Toggle group expand on click (mobile + desktop fallback) */
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.dbnotif-group-toggle');
        if (!toggle) return;
        e.preventDefault();
        e.stopPropagation();
        var group = toggle.closest('.dbnotif-ticket-group');
        if (group) group.classList.toggle('expanded');
    });

    /* Filter tabs — client-side show/hide */
    function applyFilter(f) {
        document.querySelectorAll('.dbnotif-card').forEach(function(card) {
            var isAction = card.getAttribute('data-action') === '1';
            var showCard = f === 'all' || (f === 'action' ? isAction : !isAction);
            card.classList.toggle('is-hidden', !showCard);
        });
        document.querySelectorAll('.dbnotif-ticket-group').forEach(function(g) {
            var primary = g.querySelector('.dbnotif-card');
            g.classList.toggle('is-hidden', !(primary && !primary.classList.contains('is-hidden')));
        });
    }
    (function() {
        var tabs = document.querySelectorAll('[data-dbnotif-filter]');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var f = this.getAttribute('data-dbnotif-filter');
                tabs.forEach(function(t) { t.classList.remove('active'); });
                this.classList.add('active');
                applyFilter(f);
            });
        });
        // Apply default filter on page load (action required)
        var activeTab = document.querySelector('.dbnotif-filter-tab.active');
        if (activeTab) applyFilter(activeTab.getAttribute('data-dbnotif-filter'));
    })();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; 
