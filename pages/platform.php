<?php
/**
 * FoxDesk Cloud operator console.
 *
 * This surface is intentionally separate from the customer helpdesk admin. It is
 * the SaaS control plane for the platform operator: tenants, lifecycle, billing
 * state, billing, and operational health.
 */

require_platform_admin();
require_once BASE_PATH . '/includes/modules/platform/operator-console.php';

$page_title = 'FoxDesk Cloud Console';
$page = 'platform';
$user = current_user();
$error = '';
$success = '';
$operator_link = '';
$operator_secret = '';
$operator_secret_label = '';
$selected_tenant_id = (int) ($_GET['tenant_id'] ?? $_POST['return_tenant_id'] ?? $_POST['tenant_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = (string) ($_POST['platform_action'] ?? '');

    try {
        if ($action === 'create_workspace') {
            $workspace = create_tenant_workspace([
                'workspace_name' => $_POST['workspace_name'] ?? '',
                'admin_email' => $_POST['admin_email'] ?? '',
                'admin_first_name' => $_POST['admin_first_name'] ?? '',
                'admin_last_name' => $_POST['admin_last_name'] ?? '',
                'password' => $_POST['password'] ?? '',
                'status' => $_POST['status'] ?? 'trialing',
                'subscription_status' => $_POST['subscription_status'] ?? 'trialing',
                'plan' => billing_plan_code(),
            ]);
            $selected_tenant_id = (int) $workspace['tenant_id'];
            platform_log_operator_action('platform_workspace_created', $selected_tenant_id, [
                'status' => (string) ($_POST['status'] ?? 'trialing'),
                'subscription_status' => (string) ($_POST['subscription_status'] ?? 'trialing'),
            ]);
            $success = 'Workspace created.';
        } elseif ($action === 'update_tenant') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'active');
            $subscription_status = (string) ($_POST['subscription_status'] ?? 'manual');
            platform_update_tenant_lifecycle(
                $tenant_id,
                $status,
                $subscription_status
            );
            $selected_tenant_id = $tenant_id;
            platform_log_operator_action('platform_workspace_lifecycle_updated', $tenant_id, [
                'status' => $status,
                'subscription_status' => $subscription_status,
            ]);
            $success = 'Workspace updated.';
        } elseif ($action === 'extend_trial') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $days = max(1, min(90, (int) ($_POST['days'] ?? 7)));
            platform_extend_trial($tenant_id, $days);
            $selected_tenant_id = $tenant_id;
            platform_log_operator_action('platform_trial_extended', $tenant_id, ['days' => $days]);
            $success = 'Trial extended by ' . $days . ' days.';
        } elseif ($action === 'block_tenant') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            platform_block_tenant($tenant_id);
            $selected_tenant_id = $tenant_id;
            platform_log_operator_action('platform_workspace_blocked', $tenant_id);
            $success = 'Workspace blocked.';
        } elseif ($action === 'reactivate_tenant') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $reason = (string) ($_POST['override_reason'] ?? 'Manual reactivation approved by platform operator.');
            platform_reactivate_tenant($tenant_id, $reason);
            $selected_tenant_id = $tenant_id;
            platform_log_operator_action('platform_workspace_reactivated', $tenant_id, [
                'subscription_status' => 'manual',
                'reason' => $reason,
            ]);
            $success = 'Workspace reactivated manually.';
        } elseif ($action === 'grant_free_access') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $reason = (string) ($_POST['override_reason'] ?? 'Operator approved free access.');
            platform_grant_free_access($tenant_id, $reason);
            $selected_tenant_id = $tenant_id;
            platform_log_operator_action('platform_workspace_free_access_granted', $tenant_id, [
                'subscription_status' => 'free',
                'reason' => $reason,
            ]);
            $success = 'Workspace marked free by platform override.';
        } elseif ($action === 'send_owner_reset') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $result = platform_send_owner_reset($tenant_id);
            $selected_tenant_id = $tenant_id;
            $success = !empty($result['sent'])
                ? 'Owner reset email sent to ' . (string) ($result['owner']['email'] ?? 'workspace owner') . '.'
                : 'Owner reset email could not be sent. Use the generated reset link below.';
            if (empty($result['sent'])) {
                $operator_link = (string) $result['reset_link'];
            }
        } elseif ($action === 'create_owner_reset_link') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $owner = platform_find_owner($tenant_id);
            if (!$owner) {
                throw new InvalidArgumentException('Workspace owner is missing.');
            }
            $result = platform_create_owner_reset($tenant_id, $owner);
            $selected_tenant_id = $tenant_id;
            $operator_link = (string) $result['reset_link'];
            platform_log_operator_action('platform_owner_reset_link_created', $tenant_id, [
                'owner_id' => (int) ($owner['id'] ?? 0),
            ]);
            $success = 'Owner reset link generated. It expires in one hour.';
        } elseif ($action === 'invite_owner') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $result = platform_invite_or_set_owner(
                $tenant_id,
                (string) ($_POST['owner_email'] ?? ''),
                (string) ($_POST['owner_first_name'] ?? ''),
                (string) ($_POST['owner_last_name'] ?? '')
            );
            $selected_tenant_id = $tenant_id;
            $success = !empty($result['sent'])
                ? 'Owner invite sent to ' . (string) ($result['owner']['email'] ?? 'workspace owner') . '.'
                : 'Owner account prepared. Email could not be sent, use the generated reset link below.';
            if (empty($result['sent'])) {
                $operator_link = (string) $result['reset_link'];
            }
        } elseif ($action === 'create_migration_token') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $result = platform_create_migration_token($tenant_id);
            $selected_tenant_id = $tenant_id;
            $operator_secret = (string) $result['token'];
            $operator_secret_label = 'Migration token';
            platform_log_operator_action('platform_migration_token_created', $tenant_id, [
                'connection_id' => (int) ($result['id'] ?? 0),
            ]);
            $success = 'Migration token created. Copy it now; it will not be shown again.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = db_fetch_one("
    SELECT
      COUNT(*) AS tenants,
      SUM(status IN ('active','trialing')) AS active_tenants,
      SUM(status = 'past_due') AS past_due_tenants,
      SUM(status IN ('trial_expired','suspended','blocked','canceled')) AS blocked_tenants,
      SUM(subscription_status IN ('active','trialing','manual','free')) AS billing_ok,
      (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS users
    FROM tenants
");

$tenants = db_fetch_all("
    SELECT
      t.*,
      u.email AS owner_email,
      u.first_name AS owner_first_name,
      u.last_name AS owner_last_name,
      (SELECT COUNT(*) FROM users ux WHERE ux.tenant_id = t.id AND ux.deleted_at IS NULL) AS user_count,
      (SELECT COUNT(*) FROM tickets tx WHERE tx.tenant_id = t.id) AS ticket_count,
      (SELECT MAX(created_at) FROM tickets tx WHERE tx.tenant_id = t.id) AS last_ticket_at
    FROM tenants t
    LEFT JOIN users u ON u.id = t.owner_user_id
    ORDER BY t.created_at DESC, t.id DESC
");

$total_storage_bytes = 0;
$total_local_storage_bytes = 0;
$total_r2_storage_bytes = 0;
$total_extra_gb = 0;
$estimated_storage_overage_cents = 0;
$total_api_requests = 0;
$total_inbound_email_total = 0;
$total_outbound_email_sent = 0;
$tenant_usage = [];
$attention_items = [];

foreach ($tenants as $tenant) {
    $tenant_id = (int) $tenant['id'];
    $usage = billing_tenant_usage($tenant_id);
    $tenant_usage[$tenant_id] = $usage;
    $total_storage_bytes += (int) $usage['storage_bytes'];
    $total_local_storage_bytes += (int) $usage['storage_local_bytes'];
    $total_r2_storage_bytes += (int) $usage['storage_r2_bytes'];
    $total_extra_gb += (int) $usage['extra_storage_gb'];
    $estimated_storage_overage_cents += (int) $usage['storage_overage_cents'];
    $total_api_requests += (int) $usage['api_requests'];
    $total_inbound_email_total += (int) $usage['inbound_email_total'];
    $total_outbound_email_sent += (int) $usage['outbound_email_sent'];

    if (in_array((string) $tenant['status'], ['past_due', 'trial_expired', 'suspended', 'blocked', 'canceled'], true)) {
        $attention_items[] = [
            'type' => 'Lifecycle',
            'title' => (string) $tenant['name'],
            'detail' => 'Workspace status is ' . (string) $tenant['status'],
            'tone' => 'risk',
        ];
    } elseif ((int) $usage['extra_storage_gb'] > 0) {
        $attention_items[] = [
            'type' => 'Storage',
            'title' => (string) $tenant['name'],
            'detail' => (int) $usage['extra_storage_gb'] . ' extra GB this period',
            'tone' => 'notice',
        ];
    }
}

$selected_detail = $selected_tenant_id > 0 ? platform_tenant_detail_context($selected_tenant_id) : null;
if ($selected_tenant_id > 0 && !$selected_detail && !$error) {
    $error = 'Workspace detail was not found.';
}

$operator_name = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$operator_initial = strtoupper(substr($operator_name !== '' ? $operator_name : (string) ($user['email'] ?? 'F'), 0, 1));
$active_tenants = (int) ($summary['active_tenants'] ?? 0);
$tenant_count = (int) ($summary['tenants'] ?? 0);
$health_label = ((int) ($summary['past_due_tenants'] ?? 0) + (int) ($summary['blocked_tenants'] ?? 0)) > 0 ? 'Needs review' : 'Stable';
$health_class = $health_label === 'Stable' ? 'good' : 'warn';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link href="tailwind.min.css?v=<?php echo e((string) APP_VERSION); ?>" rel="stylesheet">
    <link href="theme.css?v=<?php echo e((string) APP_VERSION); ?>" rel="stylesheet">
    <link href="assets/css/platform.css?v=<?php echo e((string) APP_VERSION); ?>" rel="stylesheet">
</head>
<body class="op-page">
<div class="op-shell">
    <aside class="op-sidebar" id="platformSidebar">
        <a class="op-brand" href="<?php echo e(url('platform')); ?>">
            <span class="op-mark">F</span>
            <span>FoxDesk Cloud<span class="op-subtitle">Operator console</span></span>
        </a>
        <nav class="op-nav" aria-label="Platform navigation">
            <a href="#overview" class="active"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z"/></svg>Overview</a>
            <a href="#workspaces"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>Workspaces</a>
            <a href="#workspaces"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 7h10v10H7zM4 4l3 3M20 4l-3 3M4 20l3-3M20 20l-3-3"/></svg>Lifecycle</a>
            <a href="#billing"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 7h16v10H4zM4 10h16M8 15h4"/></svg>Billing</a>
            <a href="<?php echo e(url('work')); ?>"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 6H5v13h13v-5M14 5h5v5M13 11l6-6"/></svg>Open helpdesk</a>
        </nav>
        <div class="op-sidebar-bottom">
            <button type="button" class="op-btn" onclick="togglePlatformTheme()">Toggle theme</button>
            <div class="op-user">
                <span class="op-avatar"><?php echo e($operator_initial); ?></span>
                <span>
                    <strong><?php echo e($operator_name !== '' ? $operator_name : 'Platform admin'); ?></strong>
                    <span><?php echo e($user['email'] ?? ''); ?></span>
                </span>
            </div>
        </div>
    </aside>

    <main class="op-main">
        <header class="op-topbar">
            <div>
                <div class="op-title-row">
                    <h1>Platform console</h1>
                    <span class="op-environment-pill">Platform admin</span>
                </div>
                <p>Operate hosted FoxDesk workspaces without mixing platform controls into customer helpdesk administration.</p>
            </div>
            <div class="op-actions">
                <button type="button" class="op-btn op-mobile-toggle" onclick="togglePlatformSidebar()">Menu</button>
                <a class="op-btn" href="<?php echo e(url('cloud')); ?>" target="_blank" rel="noopener">Public site</a>
                <a class="op-btn" href="<?php echo e(url('signup')); ?>" target="_blank" rel="noopener">Signup</a>
                <a class="op-btn primary" href="#create-workspace">New workspace</a>
            </div>
        </header>

        <?php if ($error): ?><div class="op-alert error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="op-alert success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($operator_link): ?>
            <div class="op-alert success">
                Reset link
                <code class="op-reset-code"><?php echo e($operator_link); ?></code>
            </div>
        <?php endif; ?>
        <?php if ($operator_secret): ?>
            <div class="op-alert success">
                <?php echo e($operator_secret_label ?: 'Generated secret'); ?>
                <code class="op-reset-code"><?php echo e($operator_secret); ?></code>
            </div>
        <?php endif; ?>

        <section class="op-overview" id="overview">
            <div class="op-card op-compact-card primary">
                <div class="op-compact-title">
                    <span class="op-label">Health</span>
                    <span class="op-status-dot <?php echo $health_class === 'warn' ? 'warn' : ''; ?>"></span>
                </div>
                <strong><?php echo e($health_label); ?></strong>
                <div class="op-sub"><?php echo $active_tenants; ?> / <?php echo $tenant_count; ?> active or trialing. Manual/free billing overrides count as valid access. Past due <?php echo (int) ($summary['past_due_tenants'] ?? 0); ?>, blocked <?php echo (int) ($summary['blocked_tenants'] ?? 0); ?>.</div>
                <div class="op-actions">
                    <a class="op-btn primary" href="#workspaces">Workspaces</a>
                    <a class="op-btn" href="#billing">Billing</a>
                </div>
            </div>
            <div class="op-card op-compact-card"><span class="op-label">Workspaces</span><strong><?php echo $tenant_count; ?></strong><div class="op-sub">Total tenants</div></div>
            <div class="op-card op-compact-card"><span class="op-label">App access</span><strong><?php echo $active_tenants; ?></strong><div class="op-sub">Active, trial, manual, or free</div></div>
            <div class="op-card op-compact-card"><span class="op-label">Users</span><strong><?php echo (int) ($summary['users'] ?? 0); ?></strong><div class="op-sub">Across all workspaces</div></div>
            <div class="op-card op-compact-card"><span class="op-label">Storage</span><strong><?php echo e(format_file_size($total_storage_bytes)); ?></strong><div class="op-sub">Local <?php echo e(format_file_size($total_local_storage_bytes)); ?> · R2 <?php echo e(format_file_size($total_r2_storage_bytes)); ?></div></div>
            <div class="op-card op-compact-card"><span class="op-label">Overage</span><strong><?php echo e(billing_format_money($estimated_storage_overage_cents)); ?></strong><div class="op-sub">Current period estimate</div></div>
            <div class="op-card op-compact-card"><span class="op-label">Activity</span><strong><?php echo (int) $total_api_requests; ?> API</strong><div class="op-sub"><?php echo (int) $total_inbound_email_total; ?> inbound · <?php echo (int) $total_outbound_email_sent; ?> sent</div></div>
        </section>

        <?php if ($selected_detail): ?>
            <?php
                $detail_tenant = $selected_detail['tenant'];
                $detail_usage = $selected_detail['usage'];
                $detail_owner = $selected_detail['owner'];
                $detail_included = max(1, (int) $detail_usage['included_storage_bytes']);
                $detail_storage_percent = min(100, (int) round(((int) $detail_usage['storage_bytes'] / $detail_included) * 100));
                $detail_status_class = preg_replace('/[^a-z_]/', '', (string) ($detail_tenant['status'] ?? ''));
                $detail_owner_name = $detail_owner ? trim((string) (($detail_owner['first_name'] ?? '') . ' ' . ($detail_owner['last_name'] ?? ''))) : '';
                $detail_lifecycle_state = function_exists('billing_tenant_lifecycle_state') ? billing_tenant_lifecycle_state($detail_tenant) : ['platform_buttons' => []];
                $detail_platform_buttons = array_fill_keys((array) ($detail_lifecycle_state['platform_buttons'] ?? []), true);
            ?>
            <section class="op-card op-detail" id="tenant-detail">
                <div class="op-section-head">
                    <div>
                        <h2><?php echo e($detail_tenant['name']); ?></h2>
                        <p>Tenant detail, billing state, owner access, and current-period usage.</p>
                    </div>
                    <div class="op-actions">
                        <span class="op-pill <?php echo e($detail_status_class); ?>"><?php echo e($detail_tenant['status']); ?></span>
                        <a class="op-btn" href="<?php echo e(url('billing', ['tenant_id' => (int) $detail_tenant['id']])); ?>">Billing detail</a>
                        <a class="op-btn" href="<?php echo e(url('platform')); ?>#workspaces">Close detail</a>
                    </div>
                </div>

                <div class="op-detail-grid">
                    <div class="op-mini-card">
                        <span class="op-label">Workspace</span>
                        <strong>/<?php echo e($detail_tenant['slug']); ?></strong>
                        <div class="op-sub">ID <?php echo (int) $detail_tenant['id']; ?> · created <?php echo e(substr((string) $detail_tenant['created_at'], 0, 10)); ?></div>
                    </div>
                    <div class="op-mini-card">
                        <span class="op-label">Subscription</span>
                        <strong><?php echo e($detail_tenant['subscription_status'] ?? 'manual'); ?></strong>
                        <div class="op-sub">
                            <?php echo e(billing_plan_name()); ?> ·
                            <?php if (billing_subscription_is_manual_access((string) ($detail_tenant['subscription_status'] ?? 'manual'))): ?>
                                platform override
                                <?php if (!empty($detail_tenant['billing_override_reason'])): ?>
                                    · <?php echo e($detail_tenant['billing_override_reason']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/mo
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="op-mini-card">
                        <span class="op-label">Storage</span>
                        <strong><?php echo e(format_file_size((int) $detail_usage['storage_bytes'])); ?></strong>
                        <div class="op-sub">Local <?php echo e(format_file_size((int) $detail_usage['storage_local_bytes'])); ?> · R2 <?php echo e(format_file_size((int) $detail_usage['storage_r2_bytes'])); ?></div>
                        <div class="op-bar mt-2"><span style="width: <?php echo $detail_storage_percent; ?>%;"></span></div>
                    </div>
                    <div class="op-mini-card">
                        <span class="op-label">Activity</span>
                        <strong><?php echo (int) $detail_usage['api_requests']; ?> API</strong>
                        <div class="op-sub"><?php echo (int) $detail_usage['inbound_email_total']; ?> inbound · <?php echo (int) $detail_usage['outbound_email_sent']; ?> sent this month</div>
                    </div>
                </div>

                <div class="op-detail-layout">
                    <div class="op-stack">
                        <section class="op-card">
                            <div class="op-section-head">
                                <div>
                                    <h2>Lifecycle control</h2>
                                    <p>Manual override for support, billing, and emergency access decisions.</p>
                                </div>
                            </div>
                            <div class="op-panel-body">
                                <form method="post" class="op-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="platform_action" value="update_tenant">
                                    <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                    <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                    <div class="op-field-row">
                                        <div class="op-field">
                                            <label>Status</label>
                                            <select class="op-select" name="status">
                                                <?php foreach (platform_allowed_tenant_statuses() as $status): ?>
                                                    <option value="<?php echo $status; ?>" <?php echo ($detail_tenant['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="op-field">
                                            <label>Subscription status</label>
                                            <select class="op-select" name="subscription_status">
                                                <?php foreach (platform_allowed_subscription_statuses() as $subscription_status): ?>
                                                    <option value="<?php echo e($subscription_status); ?>" <?php echo ($detail_tenant['subscription_status'] ?? 'manual') === $subscription_status ? 'selected' : ''; ?>><?php echo e($subscription_status); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button class="op-btn primary" type="submit">Save lifecycle</button>
                                </form>
                                <?php if (isset($detail_platform_buttons['grant_free_access'])): ?>
                                    <div class="op-field mt-3">
                                        <label>Override reason</label>
                                        <input class="op-input" form="tenant-free-access-form-<?php echo (int) $detail_tenant['id']; ?>" name="override_reason" value="<?php echo e($detail_tenant['billing_override_reason'] ?? 'Operator approved free access.'); ?>" maxlength="500" required>
                                    </div>
                                <?php endif; ?>
                                <div class="op-actions mt-2 justify-start">
                                    <?php if (isset($detail_platform_buttons['extend_trial'])): ?>
                                    <form method="post">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="platform_action" value="extend_trial">
                                        <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <input class="op-input" style="width: 84px;" type="number" name="days" value="7" min="1" max="90" aria-label="Trial extension days">
                                        <button class="op-btn" type="submit">Extend trial</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (isset($detail_platform_buttons['grant_free_access'])): ?>
                                    <form method="post" id="tenant-free-access-form-<?php echo (int) $detail_tenant['id']; ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="platform_action" value="grant_free_access">
                                        <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <button class="op-btn" type="submit">Free access</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (isset($detail_platform_buttons['reactivate_tenant'])): ?>
                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="platform_action" value="reactivate_tenant">
                                            <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                            <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                            <input type="hidden" name="override_reason" value="Manual reactivation approved by platform operator.">
                                            <button class="op-btn primary" type="submit">Reactivate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (isset($detail_platform_buttons['block_tenant'])): ?>
                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="platform_action" value="block_tenant">
                                            <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                            <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                            <button class="op-btn danger" type="submit">Block workspace</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <section class="op-card">
                            <div class="op-section-head">
                                <div>
                                    <h2>Owner access</h2>
                                    <p>Reset current owner access or invite a replacement owner.</p>
                                </div>
                            </div>
                            <div class="op-panel-body">
                                <div class="op-list mb-3">
                                    <div class="op-list-row">
                                        <span>
                                            <span class="op-label">Current owner</span>
                                            <div class="op-name"><?php echo e($detail_owner_name !== '' ? $detail_owner_name : 'No owner'); ?></div>
                                            <div class="op-sub"><?php echo e($detail_owner['email'] ?? ''); ?></div>
                                        </span>
                                        <?php if ($detail_owner): ?>
                                            <span class="op-pill good">admin</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="op-actions justify-start mb-3">
                                    <form method="post">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="platform_action" value="send_owner_reset">
                                        <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <button class="op-btn" type="submit">Send reset email</button>
                                    </form>
                                    <form method="post">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="platform_action" value="create_owner_reset_link">
                                        <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                        <button class="op-btn" type="submit">Generate reset link</button>
                                    </form>
                                </div>
                                <form method="post" class="op-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="platform_action" value="invite_owner">
                                    <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                    <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                    <div class="op-field">
                                        <label>New owner email</label>
                                        <input class="op-input" type="email" name="owner_email" placeholder="owner@example.com" required>
                                    </div>
                                    <div class="op-field-row">
                                        <div class="op-field">
                                            <label>First name</label>
                                            <input class="op-input" name="owner_first_name" required>
                                        </div>
                                        <div class="op-field">
                                            <label>Last name</label>
                                            <input class="op-input" name="owner_last_name">
                                        </div>
                                    </div>
                                    <button class="op-btn primary" type="submit">Invite owner</button>
                                </form>
                            </div>
                        </section>
                    </div>

                    <div class="op-stack" style="grid-template-columns: 1fr;">
                        <section class="op-card">
                            <div class="op-section-head">
                                <div>
                                    <h2>Migration bridge</h2>
                                    <p>Issue a scoped token for one-way self-hosted sync and final cutover.</p>
                                </div>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="platform_action" value="create_migration_token">
                                    <input type="hidden" name="tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                    <input type="hidden" name="return_tenant_id" value="<?php echo (int) $detail_tenant['id']; ?>">
                                    <button class="op-btn primary" type="submit">Create token</button>
                                </form>
                            </div>
                            <div class="op-panel-body op-list">
                                <?php if (empty($selected_detail['migration_connections'])): ?>
                                    <div class="op-empty">No migration bridge has been issued for this workspace yet.</div>
                                <?php endif; ?>
                                <?php foreach ($selected_detail['migration_connections'] as $connection): ?>
                                    <?php $migration_status = preg_replace('/[^a-z_]/', '', (string) ($connection['status'] ?? 'issued')); ?>
                                    <?php
                                        $synced_attachments = (int) ($connection['attachment_sync_count'] ?? 0);
                                        $synced_bytes = (int) ($connection['attachment_sync_bytes'] ?? 0);
                                        $synced_label = $synced_attachments > 0
                                            ? ' · attachments ' . $synced_attachments . ' / ' . (function_exists('format_file_size') ? format_file_size($synced_bytes) : ($synced_bytes . ' B'))
                                            : '';
                                        $last_attachment_label = !empty($connection['attachment_sync_last_at'])
                                            ? ' · last attachment ' . e((string) $connection['attachment_sync_last_at'])
                                            : '';
                                    ?>
                                    <div class="op-list-row">
                                        <span>
                                            <span class="op-label"><?php echo e($connection['label'] ?: 'Self-hosted sync'); ?></span>
                                            <div class="op-name"><?php echo e($connection['source_url'] ?: 'Waiting for source app'); ?></div>
                                            <div class="op-sub">
                                                created <?php echo e((string) $connection['created_at']); ?>
                                                <?php echo !empty($connection['last_seen_at']) ? ' · seen ' . e((string) $connection['last_seen_at']) : ''; ?>
                                                <?php echo !empty($connection['expires_at']) ? ' · expires ' . e((string) $connection['expires_at']) : ''; ?>
                                                <?php echo e($synced_label); ?>
                                                <?php echo $last_attachment_label; ?>
                                            </div>
                                        </span>
                                        <span class="op-pill <?php echo e(in_array($migration_status, ['connected', 'syncing', 'ready_for_cutover', 'cutover_complete'], true) ? 'good' : 'notice'); ?>">
                                            <?php echo e($connection['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="op-card">
                            <div class="op-section-head">
                                <div>
                                    <h2>Subscription history</h2>
                                    <p>Stripe events, local usage reports, and trial emails.</p>
                                </div>
                            </div>
                            <div class="op-panel-body op-list">
                                <?php if (empty($selected_detail['history'])): ?>
                                    <div class="op-empty">No subscription history yet.</div>
                                <?php endif; ?>
                                <?php foreach ($selected_detail['history'] as $item): ?>
                                    <div class="op-list-row">
                                        <span>
                                            <span class="op-label"><?php echo e($item['kind']); ?></span>
                                            <div class="op-name"><?php echo e($item['title']); ?></div>
                                            <div class="op-sub"><?php echo e($item['created_at']); ?><?php echo $item['detail'] !== '' ? ' · ' . e($item['detail']) : ''; ?></div>
                                        </span>
                                        <span class="op-pill <?php echo e($item['status'] === 'failed' ? 'risk' : 'good'); ?>"><?php echo e($item['status']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="op-card">
                            <div class="op-section-head">
                                <div>
                                    <h2>Usage overview</h2>
                                    <p>Current month usage signals for support and abuse review.</p>
                                </div>
                            </div>
                            <div class="op-panel-body op-list">
                                <div class="op-list-row"><span><span class="op-label">Users / clients / tickets</span><div class="op-sub">Workspace scale</div></span><strong><?php echo (int) $detail_usage['users']; ?> / <?php echo (int) $detail_usage['clients']; ?> / <?php echo (int) $detail_usage['tickets']; ?></strong></div>
                                <div class="op-list-row"><span><span class="op-label">Extra storage</span><div class="op-sub"><?php echo e(format_file_size((int) $detail_usage['extra_storage_bytes'])); ?> billable overage</div></span><strong><?php echo (int) $detail_usage['extra_storage_gb']; ?> GB</strong></div>
                                <div class="op-list-row"><span><span class="op-label">Estimated overage</span><div class="op-sub">Current period estimate</div></span><strong><?php echo e(billing_format_money((int) $detail_usage['storage_overage_cents'])); ?></strong></div>
                                <?php foreach ($selected_detail['usage_events'] as $event): ?>
                                    <div class="op-list-row">
                                        <span>
                                            <span class="op-label">Usage event</span>
                                            <div class="op-name"><?php echo e($event['event_type']); ?></div>
                                            <div class="op-sub">Latest <?php echo e($event['latest_at']); ?></div>
                                        </span>
                                        <strong><?php echo (int) $event['quantity']; ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="op-card">
                            <div class="op-section-head">
                                <div>
                                    <h2>Team snapshot</h2>
                                    <p>First users in this workspace.</p>
                                </div>
                            </div>
                            <div class="op-panel-body op-list">
                                <?php foreach ($selected_detail['users'] as $tenant_user): ?>
                                    <div class="op-list-row">
                                        <span>
                                            <span class="op-label"><?php echo e($tenant_user['role']); ?></span>
                                            <div class="op-name"><?php echo e(trim((string) (($tenant_user['first_name'] ?? '') . ' ' . ($tenant_user['last_name'] ?? '')))); ?></div>
                                            <div class="op-sub"><?php echo e($tenant_user['email']); ?></div>
                                        </span>
                                        <span class="op-pill <?php echo (int) $tenant_user['is_active'] === 1 ? 'good' : 'risk'; ?>"><?php echo (int) $tenant_user['is_active'] === 1 ? 'active' : 'inactive'; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="op-grid">
            <section class="op-card" id="workspaces">
                <div class="op-section-head">
                    <div>
                        <h2>Workspace catalog</h2>
                        <p>Search tenants, review owners, inspect usage, and update lifecycle state.</p>
                    </div>
                    <div class="op-toolbar">
                        <input class="op-search" id="workspaceSearch" type="search" placeholder="Search workspace or owner" oninput="filterWorkspaces()">
                    </div>
                </div>
                <div class="op-table-wrap">
                    <table class="op-table" id="workspaceTable">
                        <thead>
                            <tr>
                                <th>Workspace</th>
                                <th>Owner</th>
                                <th>Usage</th>
                                <th>Billing</th>
                                <th>Lifecycle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tenants as $tenant): ?>
                                <?php
                                    $tenant_id = (int) $tenant['id'];
                                    $usage = $tenant_usage[$tenant_id];
                                    $included = max(1, (int) $usage['included_storage_bytes']);
                                    $storage_percent = min(100, (int) round(((int) $usage['storage_bytes'] / $included) * 100));
                                    $status_class = preg_replace('/[^a-z_]/', '', (string) ($tenant['status'] ?? ''));
                                    $owner_name = trim((string) (($tenant['owner_first_name'] ?? '') . ' ' . ($tenant['owner_last_name'] ?? '')));
                                    $search_text = strtolower((string) ($tenant['name'] . ' ' . $tenant['slug'] . ' ' . $owner_name . ' ' . ($tenant['owner_email'] ?? '')));
                                    $tenant_lifecycle_state = function_exists('billing_tenant_lifecycle_state') ? billing_tenant_lifecycle_state($tenant) : ['platform_buttons' => []];
                                    $tenant_platform_buttons = array_fill_keys((array) ($tenant_lifecycle_state['platform_buttons'] ?? []), true);
                                ?>
                                <tr data-workspace-row data-search="<?php echo e($search_text); ?>">
                                    <td data-label="Workspace">
                                        <div class="op-name"><?php echo e($tenant['name']); ?></div>
                                        <div class="op-sub">/<?php echo e($tenant['slug']); ?> · ID <?php echo $tenant_id; ?></div>
                                        <div class="op-sub">Created <?php echo e(substr((string) $tenant['created_at'], 0, 10)); ?></div>
                                        <div class="op-actions mt-2 justify-start">
                                            <a class="op-pill" href="<?php echo e(url('platform', ['tenant_id' => $tenant_id])); ?>#tenant-detail">Open detail</a>
                                        </div>
                                    </td>
                                    <td data-label="Owner">
                                        <div class="op-name"><?php echo e($owner_name !== '' ? $owner_name : 'No owner'); ?></div>
                                        <div class="op-sub"><?php echo e($tenant['owner_email'] ?? ''); ?></div>
                                        <div class="op-sub"><?php echo (int) $usage['users']; ?> users · <?php echo (int) $usage['clients']; ?> clients</div>
                                    </td>
                                    <td data-label="Usage">
                                        <div class="op-meter">
                                            <div class="op-sub"><?php echo e(format_file_size((int) $usage['storage_bytes'])); ?> / <?php echo e(format_file_size($included)); ?></div>
                                            <div class="op-bar"><span style="width: <?php echo $storage_percent; ?>%;"></span></div>
                                            <div class="op-sub">Local <?php echo e(format_file_size((int) $usage['storage_local_bytes'])); ?> · R2 <?php echo e(format_file_size((int) $usage['storage_r2_bytes'])); ?></div>
                                            <div class="op-sub"><?php echo (int) $usage['tickets']; ?> tickets · latest <?php echo e($tenant['last_ticket_at'] ? substr((string) $tenant['last_ticket_at'], 0, 10) : 'none'); ?></div>
                                            <div class="op-sub"><?php echo (int) $usage['api_requests']; ?> API · <?php echo (int) $usage['inbound_email_total']; ?> inbound · <?php echo (int) $usage['outbound_email_sent']; ?> sent</div>
                                        </div>
                                    </td>
                                    <td data-label="Billing">
                                        <div class="op-name"><?php echo e(billing_plan_name()); ?></div>
                                        <?php if (billing_subscription_is_manual_access((string) ($tenant['subscription_status'] ?? 'manual'))): ?>
                                            <div class="op-sub">Platform override · no checkout required</div>
                                            <?php if (!empty($tenant['billing_override_reason'])): ?>
                                                <div class="op-sub">Reason: <?php echo e($tenant['billing_override_reason']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="op-sub"><?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/mo base</div>
                                        <?php endif; ?>
                                        <div class="op-sub"><?php echo (int) $usage['extra_storage_gb']; ?> extra GB · <?php echo e(billing_format_money($usage['storage_overage_cents'])); ?></div>
                                    </td>
                                    <td data-label="Lifecycle">
                                        <form method="post" class="op-inline-form">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="platform_action" value="update_tenant">
                                            <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                            <select class="op-select" name="status" aria-label="Workspace status">
                                                <?php foreach (['active', 'trialing', 'past_due', 'trial_expired', 'suspended', 'blocked', 'canceled'] as $status): ?>
                                                    <option value="<?php echo $status; ?>" <?php echo ($tenant['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="op-select" name="subscription_status" aria-label="Billing status">
                                                <?php foreach (platform_allowed_subscription_statuses() as $subscription_status): ?>
                                                    <option value="<?php echo e($subscription_status); ?>" <?php echo ($tenant['subscription_status'] ?? 'manual') === $subscription_status ? 'selected' : ''; ?>><?php echo e($subscription_status); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="op-btn" type="submit">Save</button>
                                        </form>
                                        <div class="op-actions mt-2 justify-start">
                                            <span class="op-pill <?php echo e($status_class); ?>"><?php echo e($tenant['status']); ?></span>
                                            <a class="op-pill" href="<?php echo e(url('platform', ['tenant_id' => $tenant_id])); ?>#tenant-detail">Detail</a>
                                            <a class="op-pill" href="<?php echo e(url('billing', ['tenant_id' => $tenant_id])); ?>">Billing detail</a>
                                            <?php if (isset($tenant_platform_buttons['extend_trial'])): ?>
                                                <form method="post">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="platform_action" value="extend_trial">
                                                    <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                                    <input type="hidden" name="days" value="7">
                                                    <button class="op-pill" type="submit">+7d trial</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (isset($tenant_platform_buttons['grant_free_access'])): ?>
                                                <form method="post">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="platform_action" value="grant_free_access">
                                                    <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                                    <input type="hidden" name="override_reason" value="Operator approved free access.">
                                                    <button class="op-pill good" type="submit">Free access</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (isset($tenant_platform_buttons['reactivate_tenant'])): ?>
                                                <form method="post">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="platform_action" value="reactivate_tenant">
                                                    <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                                    <input type="hidden" name="override_reason" value="Manual reactivation approved by platform operator.">
                                                    <button class="op-pill good" type="submit">Reactivate</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (isset($tenant_platform_buttons['block_tenant'])): ?>
                                                <form method="post">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="platform_action" value="block_tenant">
                                                    <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                                    <button class="op-pill blocked" type="submit">Block</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="op-stack">
                <section class="op-card" id="create-workspace">
                    <div class="op-section-head">
                        <div>
                            <h2>Create workspace</h2>
                            <p>Provision a customer FoxDesk and first admin.</p>
                        </div>
                    </div>
                    <div class="op-panel-body">
                        <form method="post" class="op-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="platform_action" value="create_workspace">
                            <div class="op-field">
                                <label>Workspace name</label>
                                <input class="op-input" name="workspace_name" placeholder="Acme Support" required>
                            </div>
                            <div class="op-field-row">
                                <div class="op-field">
                                    <label>Owner first name</label>
                                    <input class="op-input" name="admin_first_name" placeholder="Alex" required>
                                </div>
                                <div class="op-field">
                                    <label>Owner last name</label>
                                    <input class="op-input" name="admin_last_name" placeholder="Morgan">
                                </div>
                            </div>
                            <div class="op-field">
                                <label>Owner email</label>
                                <input class="op-input" type="email" name="admin_email" placeholder="owner@example.com" required>
                            </div>
                            <div class="op-field">
                                <label>Temporary password</label>
                                <input class="op-input" type="password" name="password" placeholder="At least 12 characters" minlength="12" required>
                            </div>
                            <div class="op-field-row">
                                <div class="op-field">
                                    <label>Workspace status</label>
                                    <select class="op-select" name="status">
                                        <option value="trialing">trialing</option>
                                        <option value="active">active</option>
                                    </select>
                                </div>
                                <div class="op-field">
                                    <label>Billing status</label>
                                    <select class="op-select" name="subscription_status">
                                        <option value="trialing">trialing</option>
                                        <option value="active">active</option>
                                        <option value="manual">manual</option>
                                        <option value="free">free</option>
                                    </select>
                                </div>
                            </div>
                            <button class="op-btn primary" type="submit">Create workspace</button>
                        </form>
                    </div>
                </section>

                <section class="op-card" id="billing">
                    <div class="op-section-head">
                        <div>
                            <h2>Plan and billing state</h2>
                            <p>Customer-facing plan configuration, not margin math.</p>
                        </div>
                    </div>
                    <div class="op-panel-body op-list">
                        <div class="op-list-row"><span><span class="op-label">Cloud plan</span><div class="op-sub">Base subscription</div></span><strong><?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/mo</strong></div>
                        <div class="op-list-row"><span><span class="op-label">Included storage</span><div class="op-sub">Per workspace</div></span><strong><?php echo e(format_file_size(billing_included_storage_bytes())); ?></strong></div>
                        <div class="op-list-row"><span><span class="op-label">Extra storage</span><div class="op-sub">Metered customer usage</div></span><strong><?php echo e(billing_format_money(billing_storage_overage_price_cents())); ?>/GB</strong></div>
                    </div>
                </section>

                <section class="op-card">
                    <div class="op-section-head">
                        <div>
                            <h2>Needs attention</h2>
                            <p>Lifecycle or usage items that should be reviewed.</p>
                        </div>
                    </div>
                    <div class="op-panel-body op-list">
                        <?php if (!$attention_items): ?>
                            <div class="op-empty">No workspace currently needs operator attention.</div>
                        <?php endif; ?>
                        <?php foreach (array_slice($attention_items, 0, 6) as $item): ?>
                            <div class="op-list-row">
                                <span>
                                    <span class="op-label"><?php echo e($item['type']); ?></span>
                                    <div class="op-name"><?php echo e($item['title']); ?></div>
                                    <div class="op-sub"><?php echo e($item['detail']); ?></div>
                                </span>
                                <span class="op-pill <?php echo e($item['tone']); ?>"><?php echo e($item['tone']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

            </aside>
        </div>
    </main>
</div>

<script>
    (function () {
        var saved = localStorage.getItem('foxdesk-platform-theme') || localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);
    })();

    function togglePlatformTheme() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('foxdesk-platform-theme', next);
    }

    function togglePlatformSidebar() {
        var sidebar = document.getElementById('platformSidebar');
        if (sidebar) sidebar.classList.toggle('open');
    }

    function filterWorkspaces() {
        var input = document.getElementById('workspaceSearch');
        var query = (input ? input.value : '').trim().toLowerCase();
        document.querySelectorAll('[data-workspace-row]').forEach(function (row) {
            var text = row.getAttribute('data-search') || '';
            row.classList.toggle('op-hidden-row', query !== '' && text.indexOf(query) === -1);
        });
    }
</script>
</body>
</html>
