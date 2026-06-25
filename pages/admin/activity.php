<?php
/**
 * Admin - User Activity
 *
 * Lightweight page view analytics — shows which pages users visit,
 * how active they are, and provides a raw access log.
 */

$page_title = t('Activity');
$page = 'admin';

if (!ensure_page_views_table()) {
    require_once BASE_PATH . '/includes/header.php';
    echo '<div class="p-8 text-center text-theme-muted">' . e(t('Activity tracking table is not available.')) . '</div>';
    require_once BASE_PATH . '/includes/footer.php';
    return;
}

// ── Handle POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    if (isset($_POST['clear_old'])) {
        $days = max(7, (int) ($_POST['days'] ?? 90));
        db_query("DELETE FROM page_views WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND tenant_id = ?", [$days, current_tenant_id()]);
        flash(t('Deleted activity data older than {days} days.', ['days' => $days]), 'success');
        redirect('admin', ['section' => 'activity']);
    }
    if (isset($_POST['clear_all'])) {
        db_query("DELETE FROM page_views WHERE tenant_id = ?", [current_tenant_id()]);
        flash(t('All activity data cleared.'), 'success');
        redirect('admin', ['section' => 'activity']);
    }
}

// ── Tab / view selection ─────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'overview';
$range = $_GET['range'] ?? '7';
$range_days = in_array($range, ['1', '7', '30', '90']) ? (int) $range : 7;
$range_date = date('Y-m-d H:i:s', strtotime("-{$range_days} days"));

// Human-readable page labels
$page_labels = [
    'dashboard' => t('Dashboard'),
    'tickets' => t('Tickets'),
    'ticket' => t('Ticket Detail'),
    'new-ticket' => t('New Ticket'),
    'notifications' => t('Notifications'),
    'admin' => t('Admin'),
    'profile' => t('Profile'),
    'user-profile' => t('User Profile'),
    'login' => t('Login'),
    'logout' => t('Logout'),
    'forgot-password' => t('Forgot Password'),
];

$section_labels = [
    'statuses' => t('Statuses'),
    'priorities' => t('Priorities'),
    'ticket-types' => t('Ticket Types'),
    'organizations' => t('Organizations'),
    'clients' => t('Clients'),
    'users' => t('Users'),
    'settings' => t('Settings'),
    'reports' => t('Reports'),
    'reports-list' => t('Reports List'),
    'report-builder' => t('Report Builder'),
    'recurring-tasks' => t('Recurring Tasks'),
    'agent-connect' => t('Agent Connect'),
    'activity' => t('Activity'),
];

function pv_page_label($pg, $sec, $page_labels, $section_labels) {
    $label = $page_labels[$pg] ?? ucfirst($pg);
    if ($pg === 'admin' && $sec) {
        $label .= ' → ' . ($section_labels[$sec] ?? ucfirst($sec));
    }
    return $label;
}

function pv_page_icon($pg) {
    $icons = [
        'dashboard' => 'home', 'tickets' => 'list', 'ticket' => 'file-text',
        'new-ticket' => 'plus', 'notifications' => 'bell', 'admin' => 'settings',
        'profile' => 'user', 'user-profile' => 'user',
    ];
    return $icons[$pg] ?? 'file';
}

function pv_scale_class($prefix, $value, $max, $steps) {
    $value = max(0, (int) $value);
    $max = max(1, (int) $max);
    $steps = max(1, (int) $steps);
    $index = (int) round($steps * $value / $max);
    if ($value > 0) {
        $index = max(1, $index);
    }
    $index = min($steps, max(0, $index));
    return $prefix . $index;
}

function pv_meter_class($value, $max) {
    return pv_scale_class('act-meter--', $value, $max, 20);
}

function pv_spark_class($value, $max) {
    return pv_scale_class('act-spark-bar--', $value, $max, 10);
}

function pv_avatar_class($name) {
    $seed = trim((string) $name);
    if ($seed === '') {
        $seed = 'user';
    }
    return 'act-avatar--' . (abs(crc32($seed)) % 12);
}

function pv_role_class($role) {
    $role = strtolower((string) ($role ?: 'user'));
    return in_array($role, ['admin', 'agent', 'user'], true) ? 'act-role-' . $role : 'act-role-user';
}

// ── Fetch data ───────────────────────────────────────────────────────
// Total counts
$total_views = (int) (db_fetch_one("SELECT COUNT(*) as c FROM page_views WHERE created_at >= ? AND tenant_id = ?", [$range_date, current_tenant_id()])['c'] ?? 0);
$total_users = (int) (db_fetch_one("SELECT COUNT(DISTINCT user_id) as c FROM page_views WHERE created_at >= ? AND tenant_id = ?", [$range_date, current_tenant_id()])['c'] ?? 0);
$today_views = (int) (db_fetch_one("SELECT COUNT(*) as c FROM page_views WHERE created_at >= CURDATE() AND tenant_id = ?", [current_tenant_id()])['c'] ?? 0);

// Page popularity
$popular_pages = db_fetch_all("
    SELECT page, section, COUNT(*) as views, COUNT(DISTINCT user_id) as users
    FROM page_views WHERE created_at >= ? AND tenant_id = ?
    GROUP BY page, section ORDER BY views DESC LIMIT 15
", [$range_date, current_tenant_id()]);

// User activity
$user_activity = db_fetch_all("
    SELECT pv.user_id, u.first_name, u.last_name, u.email, u.role, u.avatar,
           COUNT(*) as views, MAX(pv.created_at) as last_active,
           COUNT(DISTINCT pv.page) as pages_used
    FROM page_views pv
    JOIN users u ON pv.user_id = u.id AND u.tenant_id = pv.tenant_id
    WHERE pv.created_at >= ? AND pv.tenant_id = ?
    GROUP BY pv.user_id ORDER BY views DESC
", [$range_date, current_tenant_id()]);

// Daily views for sparkline (last N days)
$daily_views = db_fetch_all("
    SELECT DATE(created_at) as day, COUNT(*) as views
    FROM page_views WHERE created_at >= ? AND tenant_id = ?
    GROUP BY DATE(created_at) ORDER BY day ASC
", [$range_date, current_tenant_id()]);

require_once BASE_PATH . '/includes/header.php';
?>

<div class="admin-legacy-page is-narrow">
    <!-- Header -->
    <section class="admin-hero">
        <div>
            <p class="admin-eyebrow"><?php echo e(t('Activity')); ?></p>
            <h2><?php echo e(t('User Activity')); ?></h2>
            <p><?php echo e(t('Page views, access history, and tracking controls.')); ?></p>
        </div>
        <div class="admin-hero-actions">
            <!-- Range selector -->
            <div class="act-range">
                <?php foreach ([1 => t('Today'), 7 => t('7 days'), 30 => t('30 days'), 90 => t('90 days')] as $d => $label): ?>
                    <a href="<?php echo url('admin', ['section' => 'activity', 'tab' => $tab, 'range' => $d]); ?>"
                       class="<?php echo $range_days === $d ? 'active' : ''; ?>"><?php echo e($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Tabs -->
    <div class="admin-tabs">
        <a href="<?php echo url('admin', ['section' => 'activity', 'range' => $range]); ?>"
           class="admin-tab <?php echo $tab === 'overview' ? 'is-active' : ''; ?>"><?php echo e(t('Overview')); ?></a>
        <a href="<?php echo url('admin', ['section' => 'activity', 'tab' => 'log', 'range' => $range]); ?>"
           class="admin-tab <?php echo $tab === 'log' ? 'is-active' : ''; ?>"><?php echo e(t('Access Log')); ?></a>
        <a href="<?php echo url('admin', ['section' => 'activity', 'tab' => 'manage']); ?>"
           class="admin-tab <?php echo $tab === 'manage' ? 'is-active' : ''; ?>"><?php echo e(t('Manage')); ?></a>
    </div>

    <?php if ($tab === 'overview'): ?>
    <!-- ═══════════════ OVERVIEW ═══════════════ -->

    <!-- Stats row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
        <div class="act-card">
            <div class="act-stat-value"><?php echo number_format($today_views); ?></div>
            <div class="act-stat-label"><?php echo e(t('Page views today')); ?></div>
        </div>
        <div class="act-card">
            <div class="act-stat-value"><?php echo number_format($total_views); ?></div>
            <div class="act-stat-label"><?php echo e(t('Page views ({days}d)', ['days' => $range_days])); ?></div>
        </div>
        <div class="act-card">
            <div class="act-stat-value"><?php echo $total_users; ?></div>
            <div class="act-stat-label"><?php echo e(t('Active users ({days}d)', ['days' => $range_days])); ?></div>
        </div>
        <div class="act-card">
            <div class="act-stat-label act-stat-label--spark"><?php echo e(t('Daily trend')); ?></div>
            <?php if (!empty($daily_views)):
                $max_day = max(array_column($daily_views, 'views'));
            ?>
                <div class="act-spark" title="<?php echo e(t('Daily page views')); ?>">
                    <?php foreach ($daily_views as $dv): ?>
                        <div class="act-spark-bar <?php echo e(pv_spark_class($dv['views'], $max_day)); ?>"
                             title="<?php echo e($dv['day'] . ': ' . $dv['views']); ?>"></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-xs text-theme-muted">—</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Popular pages -->
        <div class="act-card">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 text-theme-muted">
                <?php echo e(t('Most Visited Pages')); ?>
            </h3>
            <?php if (empty($popular_pages)): ?>
                <div class="text-sm text-theme-muted"><?php echo e(t('No data yet.')); ?></div>
            <?php else:
                $max_views = $popular_pages[0]['views'] ?? 1;
            ?>
                <div class="space-y-2.5">
                    <?php foreach ($popular_pages as $pp): ?>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="act-page-pill">
                                    <?php echo get_icon(pv_page_icon($pp['page']), 'w-3 h-3'); ?>
                                    <?php echo e(pv_page_label($pp['page'], $pp['section'], $page_labels, $section_labels)); ?>
                                </span>
                                <span class="text-xs tabular-nums text-theme-muted">
                                    <?php echo number_format($pp['views']); ?>
                                    <span class="ml-1">(<?php echo $pp['users']; ?> <?php echo e(t('users')); ?>)</span>
                                </span>
                            </div>
                            <div class="act-bar-bg">
                                <div class="act-bar <?php echo e(pv_meter_class($pp['views'], $max_views)); ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- User activity -->
        <div class="act-card act-card--flush">
            <div class="px-5 pt-4 pb-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-theme-muted">
                    <?php echo e(t('User Activity')); ?>
                </h3>
            </div>
            <?php if (empty($user_activity)): ?>
                <div class="px-5 pb-4 text-sm text-theme-muted"><?php echo e(t('No data yet.')); ?></div>
            <?php else: ?>
                <div class="act-table-wrap">
                    <table class="act-table">
                        <thead>
                            <tr>
                                <th><?php echo e(t('User')); ?></th>
                                <th><?php echo e(t('Role')); ?></th>
                                <th class="act-th--numeric"><?php echo e(t('Views')); ?></th>
                                <th class="act-th--numeric"><?php echo e(t('Pages')); ?></th>
                                <th><?php echo e(t('Last Active')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_activity as $ua):
                                $ua_name = trim(($ua['first_name'] ?? '') . ' ' . ($ua['last_name'] ?? ''));
                                $role_class = pv_role_class($ua['role'] ?? 'user');
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo url('admin', ['section' => 'activity', 'tab' => 'user', 'uid' => $ua['user_id'], 'range' => $range]); ?>"
                                           class="act-person-link flex items-center gap-2">
                                            <?php echo render_user_avatar($ua, 'xs', 'act-avatar ' . pv_avatar_class($ua_name)); ?>
                                            <div>
                                                <div class="font-medium text-sm"><?php echo e($ua_name); ?></div>
                                                <div class="text-xs text-theme-muted"><?php echo e($ua['email']); ?></div>
                                            </div>
                                        </a>
                                    </td>
                                    <td><span class="act-role-badge <?php echo $role_class; ?>"><?php echo e($ua['role']); ?></span></td>
                                    <td class="act-td--numeric"><?php echo number_format($ua['views']); ?></td>
                                    <td class="act-td--numeric"><?php echo $ua['pages_used']; ?></td>
                                    <td class="act-muted-cell whitespace-nowrap">
                                        <?php echo e(notification_time_ago($ua['last_active'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'user' && isset($_GET['uid'])): ?>
    <!-- ═══════════════ USER DETAIL ═══════════════ -->
    <?php
    $uid = (int) $_GET['uid'];
    $detail_user = db_fetch_one("SELECT id, first_name, last_name, email, role, avatar FROM users WHERE id = ? AND tenant_id = ?", [$uid, current_tenant_id()]);

    if (!$detail_user):
        echo '<div class="act-card text-center py-8 text-theme-muted">' . e(t('User not found.')) . '</div>';
    else:
        $du_name = trim(($detail_user['first_name'] ?? '') . ' ' . ($detail_user['last_name'] ?? ''));
        $du_pages = db_fetch_all("
            SELECT page, section, COUNT(*) as views
            FROM page_views WHERE user_id = ? AND created_at >= ? AND tenant_id = ?
            GROUP BY page, section ORDER BY views DESC
        ", [$uid, $range_date, current_tenant_id()]);

        $du_recent = db_fetch_all("
            SELECT page, section, created_at
            FROM page_views WHERE user_id = ? AND tenant_id = ?
            ORDER BY created_at DESC LIMIT 30
        ", [$uid, current_tenant_id()]);

        $du_total = (int) (db_fetch_one("SELECT COUNT(*) as c FROM page_views WHERE user_id = ? AND created_at >= ? AND tenant_id = ?", [$uid, $range_date, current_tenant_id()])['c'] ?? 0);
    ?>
    <a href="<?php echo url('admin', ['section' => 'activity', 'range' => $range]); ?>"
       class="act-back-link inline-flex items-center gap-1 text-sm mb-3">
        <?php echo get_icon('arrow-left', 'w-3.5 h-3.5'); ?> <?php echo e(t('Back to overview')); ?>
    </a>

    <div class="act-card mb-4">
        <div class="flex items-center gap-3">
            <?php echo render_user_avatar($detail_user, 'lg', 'act-avatar act-avatar--lg ' . pv_avatar_class($du_name)); ?>
            <div>
                <div class="font-bold text-base text-theme-primary"><?php echo e($du_name); ?></div>
                <div class="text-xs text-theme-muted">
                    <?php echo e($detail_user['email']); ?> ·
                    <span class="act-role-badge <?php echo e(pv_role_class($detail_user['role'])); ?>"><?php echo e($detail_user['role']); ?></span> ·
                    <?php echo number_format($du_total); ?> <?php echo e(t('views in {days}d', ['days' => $range_days])); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Pages used -->
        <div class="act-card">
            <h3 class="text-xs font-semibold uppercase tracking-wider mb-3 text-theme-muted">
                <?php echo e(t('Pages Used')); ?>
            </h3>
            <?php if (empty($du_pages)): ?>
                <div class="text-sm text-theme-muted"><?php echo e(t('No data.')); ?></div>
            <?php else:
                $du_max = $du_pages[0]['views'] ?? 1;
            ?>
                <div class="space-y-2">
                    <?php foreach ($du_pages as $dp): ?>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="act-page-pill">
                                    <?php echo get_icon(pv_page_icon($dp['page']), 'w-3 h-3'); ?>
                                    <?php echo e(pv_page_label($dp['page'], $dp['section'], $page_labels, $section_labels)); ?>
                                </span>
                                <span class="text-xs tabular-nums text-theme-muted"><?php echo number_format($dp['views']); ?></span>
                            </div>
                            <div class="act-bar-bg">
                                <div class="act-bar <?php echo e(pv_meter_class($dp['views'], $du_max)); ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent activity -->
        <div class="act-card act-card--flush">
            <div class="px-5 pt-4 pb-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-theme-muted">
                    <?php echo e(t('Recent Activity')); ?>
                </h3>
            </div>
            <div class="act-table-wrap">
                <table class="act-table">
                    <thead>
                        <tr>
                            <th><?php echo e(t('Time')); ?></th>
                            <th><?php echo e(t('Page')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($du_recent as $dr): ?>
                            <tr>
                                <td class="act-muted-cell whitespace-nowrap">
                                    <?php echo date('d.m. H:i', strtotime($dr['created_at'])); ?>
                                </td>
                                <td>
                                    <span class="act-page-pill">
                                        <?php echo get_icon(pv_page_icon($dr['page']), 'w-3 h-3'); ?>
                                        <?php echo e(pv_page_label($dr['page'], $dr['section'], $page_labels, $section_labels)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($tab === 'log'): ?>
    <!-- ═══════════════ ACCESS LOG ═══════════════ -->
    <?php
    $log_user = isset($_GET['uid']) ? (int) $_GET['uid'] : null;
    $log_page_filter = $_GET['fp'] ?? '';
    $page_num = max(1, (int) ($_GET['p'] ?? 1));
    $per_page = 50;
    $log_offset = ($page_num - 1) * $per_page;

    $where = ["pv.created_at >= ?", "pv.tenant_id = ?"];
    $params = [$range_date, current_tenant_id()];
    if ($log_user) {
        $where[] = "pv.user_id = ?";
        $params[] = $log_user;
    }
    if ($log_page_filter !== '') {
        $where[] = "pv.page = ?";
        $params[] = $log_page_filter;
    }
    $where_sql = implode(' AND ', $where);

    $log_total = (int) (db_fetch_one("SELECT COUNT(*) as c FROM page_views pv WHERE $where_sql", $params)['c'] ?? 0);
    $log_pages = (int) ceil(max(1, $log_total) / $per_page);

    $log_entries = db_fetch_all("
        SELECT pv.*, u.first_name, u.last_name, u.email, u.role
        FROM page_views pv
        LEFT JOIN users u ON pv.user_id = u.id AND u.tenant_id = pv.tenant_id
        WHERE $where_sql
        ORDER BY pv.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [(int) $per_page, (int) $log_offset]));

    // Available users and pages for filter dropdowns
    $filter_users = db_fetch_all("
        SELECT DISTINCT pv.user_id, u.first_name, u.last_name
        FROM page_views pv JOIN users u ON pv.user_id = u.id AND u.tenant_id = pv.tenant_id
        WHERE pv.created_at >= ? AND pv.tenant_id = ? ORDER BY u.first_name
    ", [$range_date, current_tenant_id()]);
    $filter_pages_list = db_fetch_all("
        SELECT DISTINCT page FROM page_views WHERE created_at >= ? AND tenant_id = ? ORDER BY page
    ", [$range_date, current_tenant_id()]);
    ?>

    <!-- Filters -->
    <div class="act-card mb-3">
        <form method="get" class="flex items-center gap-3 flex-wrap">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="activity">
            <input type="hidden" name="tab" value="log">
            <input type="hidden" name="range" value="<?php echo $range_days; ?>">

            <select name="uid" class="text-sm rounded-lg border px-3 py-1.5 bg-theme-primary border-theme-light text-theme-primary">
                <option value=""><?php echo e(t('All users')); ?></option>
                <?php foreach ($filter_users as $fu): ?>
                    <option value="<?php echo $fu['user_id']; ?>" <?php echo $log_user == $fu['user_id'] ? 'selected' : ''; ?>>
                        <?php echo e(trim($fu['first_name'] . ' ' . $fu['last_name'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="fp" class="text-sm rounded-lg border px-3 py-1.5 bg-theme-primary border-theme-light text-theme-primary">
                <option value=""><?php echo e(t('All pages')); ?></option>
                <?php foreach ($filter_pages_list as $fp): ?>
                    <option value="<?php echo e($fp['page']); ?>" <?php echo $log_page_filter === $fp['page'] ? 'selected' : ''; ?>>
                        <?php echo e($page_labels[$fp['page']] ?? ucfirst($fp['page'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="act-filter-submit text-sm font-medium px-3 py-1.5 rounded-lg"><?php echo e(t('Filter')); ?></button>
            <?php if ($log_user || $log_page_filter !== ''): ?>
                <a href="<?php echo url('admin', ['section' => 'activity', 'tab' => 'log', 'range' => $range]); ?>"
                   class="act-clear-link text-sm"><?php echo e(t('Clear')); ?></a>
            <?php endif; ?>

            <span class="text-xs ml-auto text-theme-muted">
                <?php echo number_format($log_total); ?> <?php echo e(t('entries')); ?>
            </span>
        </form>
    </div>

    <!-- Log table -->
    <div class="act-card act-card--flush">
        <div class="act-table-wrap">
            <table class="act-table">
                <thead>
                    <tr>
                        <th><?php echo e(t('Time')); ?></th>
                        <th><?php echo e(t('User')); ?></th>
                        <th><?php echo e(t('Role')); ?></th>
                        <th><?php echo e(t('Page')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($log_entries)): ?>
                        <tr><td colspan="4" class="text-center py-6 text-theme-muted"><?php echo e(t('No entries found.')); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($log_entries as $le): ?>
                            <tr>
                                <td class="act-muted-cell whitespace-nowrap">
                                    <?php echo date('d.m. H:i:s', strtotime($le['created_at'])); ?>
                                </td>
                                <td>
                                    <a href="<?php echo url('admin', ['section' => 'activity', 'tab' => 'user', 'uid' => $le['user_id'], 'range' => $range]); ?>"
                                       class="act-log-user-link">
                                        <?php echo e(trim(($le['first_name'] ?? '') . ' ' . ($le['last_name'] ?? ''))); ?>
                                    </a>
                                </td>
                                <td><span class="act-role-badge <?php echo e(pv_role_class($le['role'] ?? 'user')); ?>"><?php echo e($le['role'] ?? ''); ?></span></td>
                                <td>
                                    <span class="act-page-pill">
                                        <?php echo get_icon(pv_page_icon($le['page']), 'w-3 h-3'); ?>
                                        <?php echo e(pv_page_label($le['page'], $le['section'], $page_labels, $section_labels)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($log_pages > 1): ?>
            <div class="flex items-center justify-between px-4 py-3 border-t border-theme-light">
                <div class="text-xs text-theme-muted">
                    <?php echo e(t('Page {current} of {total}', ['current' => $page_num, 'total' => $log_pages])); ?>
                </div>
                <div class="flex gap-1">
                    <?php if ($page_num > 1): ?>
                        <a href="<?php echo url('admin', array_merge(['section' => 'activity', 'tab' => 'log', 'range' => $range, 'p' => $page_num - 1],
                            $log_user ? ['uid' => $log_user] : [], $log_page_filter !== '' ? ['fp' => $log_page_filter] : [])); ?>"
                           class="act-page-link px-3 py-1 text-xs rounded border">
                            ← <?php echo e(t('Previous')); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($page_num < $log_pages): ?>
                        <a href="<?php echo url('admin', array_merge(['section' => 'activity', 'tab' => 'log', 'range' => $range, 'p' => $page_num + 1],
                            $log_user ? ['uid' => $log_user] : [], $log_page_filter !== '' ? ['fp' => $log_page_filter] : [])); ?>"
                           class="act-page-link px-3 py-1 text-xs rounded border">
                            <?php echo e(t('Next')); ?> →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'manage'): ?>
    <!-- ═══════════════ MANAGE ═══════════════ -->
    <?php
    $total_all = (int) (db_fetch_one("SELECT COUNT(*) as c FROM page_views WHERE tenant_id = ?", [current_tenant_id()])['c'] ?? 0);
    $oldest = db_fetch_one("SELECT MIN(created_at) as oldest FROM page_views WHERE tenant_id = ?", [current_tenant_id()]);
    $oldest_date = $oldest['oldest'] ?? null;
    ?>
    <div class="act-card act-card--compact">
        <h3 class="text-sm font-semibold mb-3 text-theme-primary">
            <?php echo e(t('Data Management')); ?>
        </h3>
        <p class="text-xs mb-4 text-theme-muted">
            <?php echo e(t('Total records: {count}', ['count' => number_format($total_all)])); ?>
            <?php if ($oldest_date): ?>
                · <?php echo e(t('Since: {date}', ['date' => date('d.m.Y', strtotime($oldest_date))])); ?>
            <?php endif; ?>
        </p>

        <form method="post" class="space-y-3" onsubmit="return confirm('<?php echo e(t('Are you sure?')); ?>');">
            <?php echo csrf_field(); ?>
            <div class="flex items-center gap-2">
                <button type="submit" name="clear_old" class="text-sm font-medium px-3 py-1.5 rounded-lg border border-theme-light text-theme-secondary">
                    <?php echo e(t('Delete older than')); ?>
                </button>
                <select name="days" class="text-sm rounded-lg border px-2 py-1.5 bg-theme-primary border-theme-light text-theme-primary">
                    <option value="7">7 <?php echo e(t('days')); ?></option>
                    <option value="30">30 <?php echo e(t('days')); ?></option>
                    <option value="90" selected>90 <?php echo e(t('days')); ?></option>
                </select>
            </div>
            <div>
                <button type="submit" name="clear_all" class="text-sm text-red-600 hover:text-red-800">
                    <?php echo get_icon('trash', 'w-3.5 h-3.5 mr-1 inline-block'); ?>
                    <?php echo e(t('Clear all activity data')); ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
