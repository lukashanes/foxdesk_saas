<?php
/**
 * Dashboard page.
 *
 * Action-first view for daily support work. Dashboard keeps analytics; this page
 * shows the queues that need attention now.
 */

$page_title = t('Dashboard');
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
$time_work = function_exists('time_activity_work_model') ? time_activity_work_model($user, $_GET) : [
    'period' => ['period' => 'this_month', 'label' => t('This month'), 'start' => null, 'end' => null, 'from_date' => '', 'to_date' => ''],
    'period_options' => [],
    'my_totals' => ['today' => 0, 'week' => 0, 'month' => 0, 'selected' => 0],
    'team' => [],
];
$time_period = $time_work['period'];
$time_period_options = $time_work['period_options'];
$activity_scope = $time_work['activity_scope'] ?? [
    'key' => 'last3',
    'limit' => 3,
    'options' => ['last3' => t('Last 3 tickets'), 'last10' => t('Last 10 tickets'), 'search' => t('Search')],
];
$my_activity_filter = $time_work['my_activity_filter'] ?? [
    'key' => 'last3',
    'label' => t('Last 3 tickets'),
    'is_search' => false,
    'options' => ['last3' => t('Last 3 tickets')],
];
$team_activity_filter = $time_work['team_activity_filter'] ?? $my_activity_filter;
$my_time_totals = $time_work['my_totals'];
$my_activity_entries = $time_work['my_entries'] ?? [];
$team_time_rows = $time_work['team'];
$week_chart = $time_work['week_chart'] ?? [
    'days' => [],
    'max_minutes' => 0,
    'total_minutes' => 0,
];
$period_chart = $time_work['period_chart'] ?? $week_chart;
$current_work_timers = [];
if ($is_staff
    && function_exists('ticket_time_table_exists')
    && ticket_time_table_exists()
    && function_exists('get_user_all_active_timers')
) {
    $current_work_timers = get_user_all_active_timers((int) ($user['id'] ?? 0));
}
$active_timer_minutes = 0;
foreach ($current_work_timers as $timer) {
    if (function_exists('calculate_timer_elapsed')) {
        $active_timer_minutes += (int) floor(calculate_timer_elapsed($timer) / 60);
        continue;
    }

    $started_at = strtotime((string) ($timer['started_at'] ?? ''));
    if ($started_at) {
        $active_timer_minutes += max(0, (int) floor((time() - $started_at) / 60));
    }
}
$selected_period_key = (string) ($time_period['period'] ?? 'this_month');
$selected_period_label = (string) ($time_period['label'] ?? t('This month'));
$show_selected_period_metric = !in_array($selected_period_key, ['today', 'this_week', 'this_month'], true);

$work_queue_url = static function (string $key): string {
    return url('work', $key === 'mine' ? [] : ['queue' => $key]);
};
$work_asset_version = static function (string $path): string {
    return (defined('APP_VERSION') ? (string) APP_VERSION : '1') . '-' . (string) (@filemtime(BASE_PATH . '/' . $path) ?: '0');
};

$work_period_url = static function (string $period) use ($queue_key, $my_activity_filter, $team_activity_filter): string {
    $params = ['period' => $period];
    if ($queue_key !== 'mine') {
        $params['queue'] = $queue_key;
    }
    if (($my_activity_filter['key'] ?? 'last3') !== 'last3') {
        $params['my_activity'] = (string) $my_activity_filter['key'];
    }
    if (($team_activity_filter['key'] ?? 'last3') !== 'last3') {
        $params['team_activity'] = (string) $team_activity_filter['key'];
    }
    return url('work', $params);
};

$work_activity_filter_url = static function (string $param, string $value): string {
    $params = $_GET;
    unset($params['page']);
    $params[$param] = $value;
    return url('work', $params);
};

$work_report_url = static function (string $period, ?int $agent_id = null) use ($queue_key, $time_period): string {
    $params = [
        'page' => 'admin',
        'section' => 'reports',
        'tab' => 'time',
        'period' => $period,
    ];
    if ($period === 'custom') {
        if (!empty($time_period['from_date'])) {
            $params['from_date'] = (string) $time_period['from_date'];
        }
        if (!empty($time_period['to_date'])) {
            $params['to_date'] = (string) $time_period['to_date'];
        }
    }
    if ($agent_id !== null && $agent_id > 0) {
        $params['agents'] = [$agent_id];
    }
    if ($queue_key !== 'mine') {
        $params['source_queue'] = $queue_key;
    }

    return 'index.php?' . http_build_query($params) . '#report-work-log';
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

$render_activity_filter = static function (array $filter, string $param, string $label) use ($work_activity_filter_url): void {
    ?>
    <div class="fd-segmented work-activity-switch" aria-label="<?php echo e($label); ?>">
        <?php foreach (($filter['options'] ?? []) as $scope_key => $scope_label): ?>
            <a href="<?php echo e($work_activity_filter_url($param, (string) $scope_key)); ?>"
               class="fd-segmented__item work-period-link <?php echo ($filter['key'] ?? 'last3') === (string) $scope_key ? 'is-active' : ''; ?>">
                <?php echo e($scope_label); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
};

$render_work_search = static function (array $filter, string $id): void {
    if (empty($filter['is_search'])) {
        return;
    }
    ?>
    <div class="work-log-search-wrap">
        <label class="work-log-search" for="<?php echo e($id); ?>">
            <?php echo get_icon('search', 'w-4 h-4'); ?>
            <span class="sr-only"><?php echo e(t('Search work records')); ?></span>
            <input id="<?php echo e($id); ?>"
                   type="search"
                   class="form-input work-log-search__input"
                   data-work-log-search
                   placeholder="<?php echo e(t('Search work records...')); ?>"
                   autocomplete="off">
        </label>
    </div>
    <?php
};

$render_work_search_states = static function (array $filter): void {
    if (empty($filter['is_search'])) {
        return;
    }
    ?>
    <div class="work-search-empty" data-work-search-prompt><?php echo e(t('Start typing to search work records.')); ?></div>
    <div class="work-search-empty is-hidden" data-work-search-empty><?php echo e(t('No matching work records.')); ?></div>
    <?php
};

$render_work_activity_entries = static function (array $entries, bool $compact = false): void {
    if (empty($entries)) {
        ?>
        <div class="work-activity-empty"><?php echo e(t('No activity')); ?></div>
        <?php
        return;
    }
    ?>
    <div class="work-activity-list <?php echo $compact ? 'work-activity-list--compact' : ''; ?>">
        <?php foreach ($entries as $entry): ?>
            <?php
            $ticket_id = (int) ($entry['ticket_id'] ?? 0);
            $minutes = (int) ($entry['actual_minutes'] ?? $entry['duration_minutes'] ?? 0);
            $ticket_title = trim((string) ($entry['ticket_title'] ?? ''));
            $ticket_title = $ticket_title !== '' ? $ticket_title : t('Ticket');
            $ticket_url = $ticket_id > 0 ? url('ticket', ['id' => $ticket_id]) : '#';
            $meta = array_filter([
                !empty($entry['organization_name']) ? (string) $entry['organization_name'] : '',
                !empty($entry['started_at']) ? format_date($entry['started_at']) : '',
            ]);
            $search_text = trim(implode(' ', [
                $ticket_title,
                (string) ($entry['summary'] ?? ''),
                (string) ($entry['organization_name'] ?? ''),
                (string) ($entry['status_name'] ?? ''),
                (string) ($entry['started_at'] ?? ''),
            ]));
            ?>
            <article class="work-activity-item"
                     data-work-activity-item
                     data-work-search-text="<?php echo e(mb_strtolower($search_text)); ?>">
                <a href="<?php echo e($ticket_url); ?>" class="work-activity-ticket">
                    <?php echo e($ticket_title); ?>
                </a>
                <span class="work-activity-duration"><?php echo e(format_duration_minutes($minutes)); ?></span>
                <?php if (!empty($meta)): ?>
                    <span class="work-activity-meta"><?php echo e(implode(' · ', $meta)); ?></span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
};

$render_current_work_timers = static function (array $timers): void {
    if (empty($timers)) {
        ?>
        <div class="work-current-empty"><?php echo e(t('No active work right now.')); ?></div>
        <?php
        return;
    }
    ?>
    <div class="work-current-list">
        <?php foreach ($timers as $timer): ?>
            <?php
            $ticket_id = (int) ($timer['ticket_id'] ?? 0);
            $ticket_title = trim((string) ($timer['ticket_title'] ?? ''));
            $ticket_title = $ticket_title !== '' ? $ticket_title : t('Ticket');
            $ticket_url = $ticket_id > 0 ? url('ticket', ['id' => $ticket_id]) : '#';
            $seconds = function_exists('calculate_timer_elapsed')
                ? (int) calculate_timer_elapsed($timer)
                : max(0, (int) ($timer['duration_minutes'] ?? 0) * 60);
            $minutes = max(0, (int) floor($seconds / 60));
            $timer_state = (function_exists('is_timer_paused') && is_timer_paused($timer)) ? 'Paused' : 'Running';
            ?>
            <a href="<?php echo e($ticket_url); ?>" class="work-current-item">
                <span class="work-current-main">
                    <strong><?php echo e($ticket_title); ?></strong>
                    <span>
                        <?php echo e(get_ticket_code($ticket_id)); ?>
                        <span aria-hidden="true">·</span>
                        <?php echo e(t($timer_state)); ?>
                    </span>
                </span>
                <span class="work-current-duration"><?php echo e(format_duration_minutes($minutes)); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
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

<section class="fd-card fd-page-section work-overview-card" data-work-time-overview>
    <div class="fd-section-header work-overview-head">
        <div class="fd-section-main">
            <h2 class="fd-section-title work-overview-title"><?php echo e(t('Work overview')); ?></h2>
        </div>
        <div class="fd-section-actions work-range-controls">
            <div class="fd-segmented work-period-switch" aria-label="<?php echo e(t('Time period')); ?>">
                <?php foreach ($time_period_options as $period_key => $period_label): ?>
                    <?php if ($period_key === 'custom') continue; ?>
                    <a href="<?php echo e($work_period_url((string) $period_key)); ?>"
                       class="fd-segmented__item work-period-link <?php echo ($time_period['period'] ?? '') === $period_key ? 'is-active' : ''; ?>">
                        <?php echo e($period_label); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="work-custom-period">
                <input type="hidden" name="page" value="work">
                <?php if ($queue_key !== 'mine'): ?>
                    <input type="hidden" name="queue" value="<?php echo e($queue_key); ?>">
                <?php endif; ?>
                <?php if (($my_activity_filter['key'] ?? 'last3') !== 'last3'): ?>
                    <input type="hidden" name="my_activity" value="<?php echo e((string) $my_activity_filter['key']); ?>">
                <?php endif; ?>
                <?php if (($team_activity_filter['key'] ?? 'last3') !== 'last3'): ?>
                    <input type="hidden" name="team_activity" value="<?php echo e((string) $team_activity_filter['key']); ?>">
                <?php endif; ?>
                <input type="hidden" name="period" value="custom">
                <label>
                    <span><?php echo e(t('From')); ?></span>
                    <input type="date" name="from_date" class="form-input" value="<?php echo e($time_period['from_date'] ?? ''); ?>">
                </label>
                <label>
                    <span><?php echo e(t('To')); ?></span>
                    <input type="date" name="to_date" class="form-input" value="<?php echo e($time_period['to_date'] ?? ''); ?>">
                </label>
                <button type="submit" class="btn btn-secondary btn-sm fd-button fd-button--secondary fd-button--sm"><?php echo e(t('Apply')); ?></button>
            </form>
        </div>
    </div>

    <div class="work-time-grid">
        <a class="work-time-metric" href="<?php echo e($work_report_url('today', (int) ($user['id'] ?? 0))); ?>">
            <span><?php echo e(t('Today')); ?></span>
            <strong><?php echo e(format_duration_minutes((int) ($my_time_totals['today'] ?? 0))); ?></strong>
        </a>
        <a class="work-time-metric" href="<?php echo e($work_report_url('this_week', (int) ($user['id'] ?? 0))); ?>">
            <span><?php echo e(t('This week')); ?></span>
            <strong><?php echo e(format_duration_minutes((int) ($my_time_totals['week'] ?? 0))); ?></strong>
        </a>
        <a class="work-time-metric work-time-metric--selected"
           href="<?php echo e($work_report_url($selected_period_key, (int) ($user['id'] ?? 0))); ?>"
           data-work-selected-period-metric>
            <span><?php echo e($selected_period_label); ?></span>
            <strong><?php echo e(format_duration_minutes((int) ($period_chart['total_minutes'] ?? $my_time_totals['selected'] ?? 0))); ?></strong>
        </a>
        <div class="work-time-metric">
            <span><?php echo e(t('Active now')); ?></span>
            <strong><?php echo e(format_duration_minutes($active_timer_minutes)); ?></strong>
        </div>
    </div>

    <?php
    $chart_days = $period_chart['days'] ?? [];
    $chart_max_minutes = max(1, (int) ($period_chart['max_minutes'] ?? 0));
    $chart_agents = [];
    foreach ($chart_days as $day) {
        foreach (($day['users'] ?? []) as $chart_user) {
            $chart_user_name = trim((string) ($chart_user['name'] ?? t('Agent')));
            $chart_user_name = $chart_user_name !== '' ? $chart_user_name : t('Agent');
            $chart_user_id = (int) ($chart_user['user_id'] ?? 0);
            $chart_user_key = $chart_user_id > 0 ? 'u' . $chart_user_id : 'n' . mb_strtolower($chart_user_name);
            if (!isset($chart_agents[$chart_user_key])) {
                $chart_agents[$chart_user_key] = [
                    'name' => $chart_user_name,
                    'minutes' => 0,
                ];
            }
            $chart_agents[$chart_user_key]['minutes'] += max(0, (int) ($chart_user['minutes'] ?? 0));
        }
    }
    uasort($chart_agents, static fn(array $a, array $b): int => ($b['minutes'] ?? 0) <=> ($a['minutes'] ?? 0));
    $chart_agent_color_index = [];
    $chart_color_i = 0;
    foreach (array_keys($chart_agents) as $chart_agent_key) {
        $chart_agent_color_index[$chart_agent_key] = $chart_color_i % 8;
        $chart_color_i++;
    }
    $chart_palette = ['#3b5bdb', '#0ca678', '#f59f00', '#9c36b5', '#e03131', '#1971c2', '#2b8a3e', '#e8590c'];
    $chart_labels = [];
    $chart_full_labels = [];
    $chart_agent_datasets = [];
    foreach ($chart_agents as $chart_agent_key => $chart_agent) {
        $chart_agent_datasets[$chart_agent_key] = [
            'label' => (string) ($chart_agent['name'] ?? t('Agent')),
            'data' => array_fill(0, count($chart_days), 0),
            'backgroundColor' => $chart_palette[$chart_agent_color_index[$chart_agent_key] ?? 0] ?? $chart_palette[0],
            'borderColor' => $chart_palette[$chart_agent_color_index[$chart_agent_key] ?? 0] ?? $chart_palette[0],
        ];
    }
    foreach ($chart_days as $day_index => $day) {
        $chart_labels[] = (string) ($day['label'] ?? '');
        $chart_full_labels[] = (string) ($day['full_label'] ?? $day['label'] ?? '');
        foreach (($day['users'] ?? []) as $chart_user) {
            $chart_user_name = trim((string) ($chart_user['name'] ?? t('Agent')));
            $chart_user_name = $chart_user_name !== '' ? $chart_user_name : t('Agent');
            $chart_user_id = (int) ($chart_user['user_id'] ?? 0);
            $chart_user_key = $chart_user_id > 0 ? 'u' . $chart_user_id : 'n' . mb_strtolower($chart_user_name);
            if (!isset($chart_agent_datasets[$chart_user_key])) {
                continue;
            }
            $chart_agent_datasets[$chart_user_key]['data'][$day_index] = max(0, (int) ($chart_user['minutes'] ?? 0));
        }
    }
    $work_chart_payload = [
        'labels' => $chart_labels,
        'fullLabels' => $chart_full_labels,
        'datasets' => array_values($chart_agent_datasets),
        'emptyLabel' => t('No activity'),
        'totalLabel' => t('Total'),
    ];
    $chart_has_minutes = (int) ($period_chart['total_minutes'] ?? 0) > 0;
    ?>
    <div class="work-week-chart work-hours-chart" data-work-week-chart data-work-period-chart data-work-hours-chart>
        <div class="work-week-chart__header">
            <h3><?php echo e(t('Worked hours')); ?></h3>
            <span><?php echo e((string) ($time_period['label'] ?? t('This month'))); ?> · <?php echo e(format_duration_minutes((int) ($period_chart['total_minutes'] ?? 0))); ?></span>
        </div>
        <div class="work-hours-chart__canvas-wrap">
            <canvas data-work-hours-chart-canvas aria-label="<?php echo e(t('Hours by day')); ?>"></canvas>
        </div>
        <div class="work-hours-chart__fallback" data-work-hours-chart-fallback>
            <?php if ($chart_has_minutes): ?>
                <?php
                $svg_day_count = max(1, count($chart_days));
                $svg_width = max(720, $svg_day_count * 30);
                $svg_height = 218;
                $svg_plot_left = 18.0;
                $svg_plot_right = 12.0;
                $svg_plot_top = 22.0;
                $svg_plot_height = 142.0;
                $svg_plot_bottom = 34.0;
                $svg_slot_width = max(1.0, ($svg_width - $svg_plot_left - $svg_plot_right) / $svg_day_count);
                $svg_bar_width = max(8.0, min(24.0, $svg_slot_width * 0.58));
                $svg_dense_chart = $svg_day_count > 14;
                $svg_format = static function (float $value): string {
                    $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
                    return $formatted === '' ? '0' : $formatted;
                };
                ?>
                <div class="work-hours-chart__svg-wrap" role="img" aria-label="<?php echo e(t('Hours by day')); ?>">
                    <svg class="work-hours-chart__svg"
                         viewBox="0 0 <?php echo e((string) $svg_width); ?> <?php echo e((string) $svg_height); ?>"
                         aria-hidden="true">
                        <line class="work-hours-chart__grid-line"
                              x1="<?php echo e($svg_format($svg_plot_left)); ?>"
                              x2="<?php echo e($svg_format($svg_width - $svg_plot_right)); ?>"
                              y1="<?php echo e($svg_format($svg_plot_top + $svg_plot_height)); ?>"
                              y2="<?php echo e($svg_format($svg_plot_top + $svg_plot_height)); ?>"></line>
                        <line class="work-hours-chart__grid-line"
                              x1="<?php echo e($svg_format($svg_plot_left)); ?>"
                              x2="<?php echo e($svg_format($svg_width - $svg_plot_right)); ?>"
                              y1="<?php echo e($svg_format($svg_plot_top + ($svg_plot_height / 2))); ?>"
                              y2="<?php echo e($svg_format($svg_plot_top + ($svg_plot_height / 2))); ?>"></line>
                    <?php foreach ($chart_days as $day_index => $day): ?>
                        <?php
                        $day_minutes = (int) ($day['minutes'] ?? 0);
                        $users_for_day = $day['users'] ?? [];
                        $day_label = (string) ($day['full_label'] ?? $day['label'] ?? '');
                        $show_day_label = !$svg_dense_chart || $day_index === 0 || $day_index === count($chart_days) - 1 || $day_index % 5 === 4;
                        $axis_label = (string) ($day['label'] ?? '');
                        if ($svg_dense_chart && !empty($day['key'])) {
                            $axis_ts = strtotime((string) $day['key']);
                            if ($axis_ts !== false) {
                                $axis_label = date('j', $axis_ts);
                            }
                        }
                        $slot_x = $svg_plot_left + ($svg_slot_width * $day_index);
                        $bar_x = $slot_x + (($svg_slot_width - $svg_bar_width) / 2);
                        $baseline_y = $svg_plot_top + $svg_plot_height;
                        $positive_users_for_day = [];
                        foreach ($users_for_day as $chart_user) {
                            if (max(0, (int) ($chart_user['minutes'] ?? 0)) > 0) {
                                $positive_users_for_day[] = $chart_user;
                            }
                        }
                        $bar_height = $day_minutes > 0
                            ? max(3.0, min($svg_plot_height, ($day_minutes / max(1, $chart_max_minutes)) * $svg_plot_height))
                            : 3.0;
                        $bar_top_y = $baseline_y - $bar_height;
                        $bar_radius = min(7.0, $svg_bar_width / 2, $bar_height / 2);
                        $clip_id = 'work-hours-stack-' . (int) $day_index;
                        $clip_path = implode(' ', [
                            'M', $svg_format($bar_x), $svg_format($baseline_y),
                            'V', $svg_format($bar_top_y + $bar_radius),
                            'Q', $svg_format($bar_x), $svg_format($bar_top_y), $svg_format($bar_x + $bar_radius), $svg_format($bar_top_y),
                            'H', $svg_format($bar_x + $svg_bar_width - $bar_radius),
                            'Q', $svg_format($bar_x + $svg_bar_width), $svg_format($bar_top_y), $svg_format($bar_x + $svg_bar_width), $svg_format($bar_top_y + $bar_radius),
                            'V', $svg_format($baseline_y),
                            'Z',
                        ]);
                        ?>
                        <g>
                            <title><?php echo e(trim($day_label . ' ' . format_duration_minutes($day_minutes))); ?></title>
                            <?php if ($day_minutes > 0 && !empty($positive_users_for_day)): ?>
                                <clipPath id="<?php echo e($clip_id); ?>">
                                    <path d="<?php echo e($clip_path); ?>"></path>
                                </clipPath>
                                <g clip-path="url(#<?php echo e($clip_id); ?>)">
                                <?php
                                $segment_y = $baseline_y;
                                $remaining_height = $bar_height;
                                $segment_count = count($positive_users_for_day);
                                ?>
                                <?php foreach ($positive_users_for_day as $segment_index => $chart_user): ?>
                                    <?php
                                    $chart_user_minutes = max(0, (int) ($chart_user['minutes'] ?? 0));
                                    $chart_user_name = trim((string) ($chart_user['name'] ?? t('Agent')));
                                    $chart_user_name = $chart_user_name !== '' ? $chart_user_name : t('Agent');
                                    $chart_user_id = (int) ($chart_user['user_id'] ?? 0);
                                    $chart_user_key = $chart_user_id > 0 ? 'u' . $chart_user_id : 'n' . mb_strtolower($chart_user_name);
                                    $segment_color = $chart_palette[$chart_agent_color_index[$chart_user_key] ?? 0] ?? $chart_palette[0];
                                    if ($segment_index === $segment_count - 1) {
                                        $segment_height = $remaining_height;
                                    } else {
                                        $segment_height = ($chart_user_minutes / max(1, $day_minutes)) * $bar_height;
                                        $segment_height = max(0.5, min($remaining_height, $segment_height));
                                    }
                                    $segment_y -= $segment_height;
                                    $remaining_height = max(0, $remaining_height - $segment_height);
                                    ?>
                                    <rect class="work-hours-chart__bar-segment"
                                          x="<?php echo e($svg_format($bar_x)); ?>"
                                          y="<?php echo e($svg_format($segment_y)); ?>"
                                          width="<?php echo e($svg_format($svg_bar_width)); ?>"
                                          height="<?php echo e($svg_format($segment_height)); ?>"
                                          fill="<?php echo e($segment_color); ?>">
                                        <title><?php echo e($day_label); ?> · <?php echo e($chart_user_name); ?>: <?php echo e(format_duration_minutes($chart_user_minutes)); ?></title>
                                    </rect>
                                <?php endforeach; ?>
                                </g>
                            <?php else: ?>
                                <rect class="work-hours-chart__bar-empty"
                                      x="<?php echo e($svg_format($bar_x)); ?>"
                                      y="<?php echo e($svg_format($baseline_y - 3)); ?>"
                                      width="<?php echo e($svg_format($svg_bar_width)); ?>"
                                      height="3"
                                      rx="2"></rect>
                            <?php endif; ?>
                            <?php if ($show_day_label): ?>
                                <text class="work-hours-chart__axis"
                                      x="<?php echo e($svg_format($bar_x + ($svg_bar_width / 2))); ?>"
                                      y="<?php echo e($svg_format($svg_plot_top + $svg_plot_height + $svg_plot_bottom - 8)); ?>"
                                      text-anchor="middle"><?php echo e($axis_label); ?></text>
                            <?php endif; ?>
                        </g>
                    <?php endforeach; ?>
                    </svg>
                </div>
                <?php if (!empty($chart_agents)): ?>
                    <div class="work-week-chart__legend" aria-label="<?php echo e(t('Agent')); ?>">
                        <?php foreach ($chart_agents as $chart_agent_key => $chart_agent): ?>
                            <?php $legend_color = $chart_palette[$chart_agent_color_index[$chart_agent_key] ?? 0] ?? $chart_palette[0]; ?>
                            <span class="work-week-chart__legend-item">
                                <span class="work-week-chart__legend-dot" style="background: <?php echo e($legend_color); ?>;"></span>
                                <?php echo e((string) ($chart_agent['name'] ?? t('Agent'))); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="work-hours-chart__empty"><?php echo e(t('No activity')); ?></div>
            <?php endif; ?>
        </div>
        <script type="application/json" data-work-hours-chart-payload><?php echo json_encode($work_chart_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    </div>

</section>

<?php if ($is_staff): ?>
<section class="fd-card fd-page-section work-current-card" data-work-current>
    <div class="fd-section-header work-team-head">
        <div class="fd-section-main">
            <h2 class="fd-section-title work-team-title"><?php echo e(t('Current work')); ?></h2>
        </div>
    </div>
    <?php $render_current_work_timers($current_work_timers); ?>
</section>
<?php endif; ?>

<section class="fd-card fd-page-section work-activity-card"
         data-work-user-activity
         data-work-log-surface
         data-work-filter-mode="<?php echo e((string) ($my_activity_filter['key'] ?? 'last3')); ?>">
    <div class="fd-section-header work-team-head">
        <div class="fd-section-main">
            <h2 class="fd-section-title work-team-title"><?php echo e(t('My work log')); ?></h2>
        </div>
        <div class="fd-section-actions work-log-controls">
            <?php $render_activity_filter($my_activity_filter, 'my_activity', t('My work log filter')); ?>
            <?php $render_work_search($my_activity_filter, 'work-my-search'); ?>
        </div>
    </div>
    <?php $render_work_activity_entries($my_activity_entries); ?>
    <?php $render_work_search_states($my_activity_filter); ?>
</section>

<?php if (is_admin()): ?>
<section class="fd-card fd-page-section work-team-card"
         data-work-team-time
         data-work-log-surface
         data-work-filter-mode="<?php echo e((string) ($team_activity_filter['key'] ?? 'last3')); ?>">
    <div class="fd-section-header work-team-head">
        <div class="fd-section-main">
            <h2 class="fd-section-title work-team-title"><?php echo e(t('Team time')); ?></h2>
        </div>
        <div class="fd-section-actions work-log-controls">
            <?php $render_activity_filter($team_activity_filter, 'team_activity', t('Team time filter')); ?>
            <?php $render_work_search($team_activity_filter, 'work-team-search'); ?>
            <a href="<?php echo e(url('admin', ['section' => 'reports'])); ?>" class="btn btn-secondary btn-sm fd-button fd-button--secondary fd-button--sm">
                <?php echo get_icon('chart-bar', 'w-4 h-4 mr-1'); ?><?php echo e(t('Open reports')); ?>
            </a>
        </div>
    </div>
    <div class="work-team-table-wrap">
        <table class="fd-table work-team-table">
            <thead>
                <tr>
                    <th><?php echo e(t('Agent')); ?></th>
                    <th><?php echo e(t('Today')); ?></th>
                    <th><?php echo e(t('This week')); ?></th>
                    <th><?php echo e(t('This month')); ?></th>
                    <?php if ($show_selected_period_metric): ?>
                        <th><?php echo e(t('Selected')); ?></th>
                    <?php endif; ?>
                    <th><?php echo e(t('Work log')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($team_time_rows)): ?>
                    <tr><td colspan="<?php echo $show_selected_period_metric ? 6 : 5; ?>" class="work-team-empty"><?php echo e(t('No team time yet.')); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($team_time_rows as $row): ?>
                    <?php
                    $staff = $row['user'] ?? [];
                    $staff_id = (int) ($staff['id'] ?? 0);
                    $totals = $row['totals'] ?? [];
                    $entries = $row['entries'] ?? [];
                    $agent_report_url = $work_report_url((string) ($time_period['period'] ?? 'this_month'), $staff_id);
                    $team_search_text = trim(($row['name'] ?? '') . ' ' . implode(' ', array_map(
                        static fn($entry): string => (string) ($entry['ticket_title'] ?? '') . ' ' . (string) ($entry['organization_name'] ?? ''),
                        $entries
                    )));
                    ?>
                    <tr data-work-team-row data-work-search-text="<?php echo e(mb_strtolower($team_search_text)); ?>">
                        <td data-label="<?php echo e(t('Agent')); ?>">
                            <a href="<?php echo e($agent_report_url); ?>" class="work-team-agent">
                                <?php echo e($row['name'] ?? t('Agent')); ?>
                            </a>
                            <?php if (!empty($row['is_running'])): ?>
                                <span class="work-team-running"><?php echo e(t('Running')); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo e(t('Today')); ?>"><a href="<?php echo e($work_report_url('today', $staff_id)); ?>" class="work-time-link"><?php echo e(format_duration_minutes((int) ($totals['today'] ?? 0))); ?></a></td>
                        <td data-label="<?php echo e(t('This week')); ?>"><a href="<?php echo e($work_report_url('this_week', $staff_id)); ?>" class="work-time-link"><?php echo e(format_duration_minutes((int) ($totals['week'] ?? 0))); ?></a></td>
                        <td data-label="<?php echo e(t('This month')); ?>"><a href="<?php echo e($work_report_url('this_month', $staff_id)); ?>" class="work-time-link"><?php echo e(format_duration_minutes((int) ($totals['month'] ?? 0))); ?></a></td>
                        <?php if ($show_selected_period_metric): ?>
                            <td data-label="<?php echo e(t('Selected')); ?>"><a href="<?php echo e($agent_report_url); ?>" class="work-time-link"><strong><?php echo e(format_duration_minutes((int) ($totals['selected'] ?? 0))); ?></strong></a></td>
                        <?php endif; ?>
                        <td class="work-team-last" data-label="<?php echo e(t('Work log')); ?>">
                            <?php $render_work_activity_entries($entries, true); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $render_work_search_states($team_activity_filter); ?>
</section>
<?php endif; ?>

<?php
workspace_render_queue_page([
    'title' => 'Tickets',
    'summary' => $queue_summary,
    'active_key' => $queue_key,
    'active_queue' => $active_queue,
    'items' => $active_items,
    'queue_url' => $work_queue_url,
    'view_all_url' => $work_tickets_url($queue_key),
    'primary_action' => '',
    'row_options' => ['show_assignee' => true],
]);
?>

<script src="assets/vendor/chartjs/4.4.0/chart.umd.js?v=<?php echo e($work_asset_version('assets/vendor/chartjs/4.4.0/chart.umd.js')); ?>"></script>
<script defer src="assets/js/work-dashboard.js?v=<?php echo e($work_asset_version('assets/js/work-dashboard.js')); ?>"></script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
