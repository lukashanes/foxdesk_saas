<?php
/**
 * FoxDesk Cloud operator console.
 *
 * This surface is intentionally separate from the customer helpdesk admin. It is
 * the SaaS control plane for the platform operator: tenants, lifecycle, billing
 * state, migrations, and operational health.
 */

require_platform_admin();

$page_title = 'FoxDesk Cloud Console';
$page = 'platform';
$user = current_user();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = (string) ($_POST['platform_action'] ?? '');

    try {
        if ($action === 'create_workspace') {
            create_tenant_workspace([
                'workspace_name' => $_POST['workspace_name'] ?? '',
                'admin_email' => $_POST['admin_email'] ?? '',
                'admin_first_name' => $_POST['admin_first_name'] ?? '',
                'admin_last_name' => $_POST['admin_last_name'] ?? '',
                'password' => $_POST['password'] ?? '',
                'status' => $_POST['status'] ?? 'trialing',
                'subscription_status' => $_POST['subscription_status'] ?? 'trialing',
                'plan' => billing_plan_code(),
            ]);
            $success = 'Workspace created.';
        } elseif ($action === 'import_migration') {
            if (empty($_FILES['migration_package']['tmp_name']) || ($_FILES['migration_package']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Upload a FoxDesk migration ZIP package.');
            }
            $name = (string) ($_FILES['migration_package']['name'] ?? '');
            if (!preg_match('/\.zip$/i', $name)) {
                throw new InvalidArgumentException('Migration package must be a ZIP file.');
            }

            $summary = migration_import_package((string) $_FILES['migration_package']['tmp_name'], [
                'workspace_name' => $_POST['migration_workspace_name'] ?? '',
                'billing_email' => $_POST['migration_billing_email'] ?? null,
                'status' => $_POST['migration_status'] ?? 'trialing',
                'subscription_status' => $_POST['migration_subscription_status'] ?? 'manual',
            ]);
            $success = 'Migration imported into workspace #' . (int) $summary['tenant_id'] . '.';
        } elseif ($action === 'update_tenant') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'active');
            $subscription_status = trim((string) ($_POST['subscription_status'] ?? 'manual'));
            $allowed_statuses = ['active', 'trialing', 'past_due', 'suspended', 'canceled'];
            if ($tenant_id <= 0 || !in_array($status, $allowed_statuses, true)) {
                throw new InvalidArgumentException('Invalid workspace update.');
            }
            db_update('tenants', [
                'status' => $status,
                'subscription_status' => $subscription_status !== '' ? $subscription_status : 'manual',
                'plan' => billing_plan_code(),
                'suspended_at' => $status === 'suspended' ? date('Y-m-d H:i:s') : null,
            ], 'id = ?', [$tenant_id]);
            $success = 'Workspace updated.';
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
      SUM(status IN ('suspended','canceled')) AS blocked_tenants,
      SUM(subscription_status IN ('active','trialing')) AS billing_ok,
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
$total_extra_gb = 0;
$estimated_storage_overage_cents = 0;
$tenant_usage = [];
$attention_items = [];

foreach ($tenants as $tenant) {
    $tenant_id = (int) $tenant['id'];
    $usage = billing_tenant_usage($tenant_id);
    $tenant_usage[$tenant_id] = $usage;
    $total_storage_bytes += (int) $usage['storage_bytes'];
    $total_extra_gb += (int) $usage['extra_storage_gb'];
    $estimated_storage_overage_cents += (int) $usage['storage_overage_cents'];

    if (in_array((string) $tenant['status'], ['past_due', 'suspended'], true)) {
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

migration_ensure_imports_table();
$migration_imports = db_fetch_all("
    SELECT mi.*, t.name AS tenant_name, t.slug AS tenant_slug, u.email AS created_by_email
    FROM migration_imports mi
    LEFT JOIN tenants t ON t.id = mi.tenant_id
    LEFT JOIN users u ON u.id = mi.created_by
    ORDER BY mi.created_at DESC, mi.id DESC
    LIMIT 8
");

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
    <link href="tailwind.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        :root {
            --op-bg: #f6f7f9;
            --op-ink: #111827;
            --op-muted: #667085;
            --op-soft: #eef1f5;
            --op-line: rgba(17, 24, 39, .10);
            --op-panel: rgba(255, 255, 255, .82);
            --op-panel-solid: #ffffff;
            --op-blue: #2563eb;
            --op-green: #047857;
            --op-amber: #b45309;
            --op-red: #b42318;
            --op-shadow: 0 12px 32px rgba(15, 23, 42, .06);
        }
        [data-theme="dark"] {
            --op-bg: #070b12;
            --op-ink: #f8fafc;
            --op-muted: #9ca3af;
            --op-soft: rgba(255, 255, 255, .08);
            --op-line: rgba(255, 255, 255, .12);
            --op-panel: rgba(15, 23, 42, .78);
            --op-panel-solid: #0f172a;
            --op-shadow: 0 16px 44px rgba(0, 0, 0, .30);
        }
        * {
            box-sizing: border-box;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, .10), transparent 32rem),
                linear-gradient(180deg, var(--op-bg), var(--op-bg));
            color: var(--op-ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
            opacity: 1 !important;
            animation: none !important;
            overflow-x: hidden;
        }
        section[id] {
            scroll-margin-top: 76px;
        }
        a {
            color: inherit;
            text-decoration: none;
        }
        .op-shell {
            min-height: 100vh;
            display: block;
        }
        .op-sidebar {
            position: sticky;
            top: 0;
            z-index: 30;
            min-height: 56px;
            padding: 9px clamp(12px, 2vw, 24px);
            border-bottom: 1px solid var(--op-line);
            background: rgba(255, 255, 255, .54);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        [data-theme="dark"] .op-sidebar {
            background: rgba(2, 6, 23, .64);
        }
        .op-brand {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 34px;
            font-weight: 820;
            font-size: 15px;
            flex: 0 0 auto;
        }
        .op-mark {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            color: #fff;
            background: #111827;
            box-shadow: 0 14px 32px rgba(17, 24, 39, .18);
        }
        [data-theme="dark"] .op-mark {
            background: #f8fafc;
            color: #020617;
        }
        .op-subtitle {
            display: block;
            color: var(--op-muted);
            font-size: 11px;
            font-weight: 620;
            margin-top: 2px;
        }
        .op-nav {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .op-nav::-webkit-scrollbar {
            display: none;
        }
        .op-nav a {
            min-height: 34px;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 0 10px;
            border-radius: 9px;
            color: var(--op-muted);
            font-size: 13px;
            font-weight: 680;
            white-space: nowrap;
            flex: 0 0 auto;
        }
        .op-nav a:hover,
        .op-nav a.active {
            background: var(--op-soft);
            color: var(--op-ink);
        }
        .op-nav svg {
            width: 15px;
            height: 15px;
            stroke-width: 2;
        }
        .op-sidebar-bottom {
            position: static;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }
        .op-user {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid var(--op-line);
            border-radius: 12px;
            background: var(--op-panel);
        }
        .op-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--op-ink);
            color: var(--op-bg);
            font-weight: 800;
        }
        .op-user strong,
        .op-user span {
            display: block;
            max-width: 138px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .op-user strong {
            font-size: 12px;
        }
        .op-user span {
            color: var(--op-muted);
            font-size: 11px;
        }
        .op-main {
            min-width: 0;
            width: 100%;
            padding: 14px clamp(12px, 2vw, 24px) 24px;
        }
        .op-topbar {
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .op-topbar h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1.12;
            font-weight: 820;
            letter-spacing: 0;
        }
        .op-topbar p {
            margin: 3px 0 0;
            color: var(--op-muted);
            font-size: 12px;
            line-height: 1.35;
        }
        .op-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .op-btn {
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: 1px solid var(--op-line);
            border-radius: 9px;
            padding: 0 11px;
            background: var(--op-panel-solid);
            color: var(--op-ink);
            font-size: 12px;
            font-weight: 760;
            cursor: pointer;
        }
        .op-btn.primary {
            border-color: #111827;
            background: #111827;
            color: #fff;
        }
        [data-theme="dark"] .op-btn.primary {
            border-color: #f8fafc;
            background: #f8fafc;
            color: #020617;
        }
        .op-btn.danger {
            color: var(--op-red);
        }
        .op-card {
            min-width: 0;
            border: 1px solid var(--op-line);
            border-radius: 12px;
            background: var(--op-panel);
            box-shadow: var(--op-shadow);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
        .op-alert {
            margin-bottom: 10px;
            padding: 9px 11px;
            border-radius: 10px;
            border: 1px solid var(--op-line);
            font-size: 12px;
            font-weight: 680;
        }
        .op-alert.error {
            color: var(--op-red);
            background: rgba(254, 226, 226, .72);
        }
        .op-alert.success {
            color: var(--op-green);
            background: rgba(220, 252, 231, .72);
        }
        .op-overview {
            display: grid;
            grid-template-columns: 1.4fr repeat(5, minmax(132px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .op-compact-card {
            padding: 12px;
            min-height: 86px;
        }
        .op-compact-card.primary {
            display: grid;
            gap: 7px;
        }
        .op-compact-card strong {
            display: block;
            margin-top: 7px;
            font-size: 22px;
            line-height: 1;
        }
        .op-compact-card .op-actions {
            justify-content: flex-start;
            margin-top: 2px;
        }
        .op-compact-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .op-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--op-green);
            box-shadow: 0 0 0 6px rgba(4, 120, 87, .12);
        }
        .op-status-dot.warn {
            background: var(--op-amber);
            box-shadow: 0 0 0 6px rgba(180, 83, 9, .14);
        }
        .op-label {
            color: var(--op-muted);
            font-size: 10px;
            font-weight: 760;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .op-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 12px;
            align-items: start;
        }
        .op-section-head {
            min-height: 52px;
            padding: 12px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--op-line);
        }
        .op-section-head h2 {
            margin: 0;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 820;
        }
        .op-section-head p {
            margin: 3px 0 0;
            color: var(--op-muted);
            font-size: 12px;
            line-height: 1.35;
        }
        .op-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .op-search {
            width: 220px;
            height: 32px;
            border: 1px solid var(--op-line);
            border-radius: 9px;
            background: var(--op-panel-solid);
            color: var(--op-ink);
            padding: 0 12px;
            font-size: 12px;
            outline: none;
        }
        .op-table-wrap {
            max-width: 100%;
            overflow-x: auto;
            overscroll-behavior-x: contain;
        }
        .op-table {
            width: 100%;
            min-width: 780px;
            border-collapse: collapse;
        }
        .op-table th,
        .op-table td {
            padding: 9px 12px;
            border-bottom: 1px solid var(--op-line);
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }
        .op-table tr:last-child td {
            border-bottom: 0;
        }
        .op-table th {
            color: var(--op-muted);
            font-size: 10px;
            font-weight: 820;
            text-transform: uppercase;
            letter-spacing: .04em;
            background: rgba(255, 255, 255, .34);
        }
        [data-theme="dark"] .op-table th {
            background: rgba(255, 255, 255, .035);
        }
        .op-name {
            color: var(--op-ink);
            font-size: 13px;
            font-weight: 820;
        }
        .op-sub {
            margin-top: 2px;
            color: var(--op-muted);
            font-size: 11px;
            line-height: 1.35;
        }
        .op-pill {
            min-height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0 8px;
            background: var(--op-soft);
            color: var(--op-muted);
            font-size: 11px;
            font-weight: 800;
        }
        .op-pill.active,
        .op-pill.trialing,
        .op-pill.good {
            background: rgba(220, 252, 231, .74);
            color: var(--op-green);
        }
        .op-pill.past_due,
        .op-pill.notice {
            background: rgba(254, 243, 199, .78);
            color: var(--op-amber);
        }
        .op-pill.suspended,
        .op-pill.canceled,
        .op-pill.risk {
            background: rgba(254, 226, 226, .76);
            color: var(--op-red);
        }
        .op-meter {
            display: grid;
            gap: 5px;
            min-width: 140px;
        }
        .op-bar {
            height: 6px;
            border-radius: 999px;
            overflow: hidden;
            background: var(--op-soft);
        }
        .op-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #111827;
        }
        [data-theme="dark"] .op-bar span {
            background: #f8fafc;
        }
        .op-inline-form {
            display: grid;
            grid-template-columns: minmax(92px, 1fr) minmax(96px, 1fr) 54px;
            gap: 6px;
            min-width: 260px;
        }
        .op-input,
        .op-select {
            width: 100%;
            height: 32px;
            border: 1px solid var(--op-line);
            border-radius: 8px;
            background: var(--op-panel-solid);
            color: var(--op-ink);
            padding: 0 9px;
            font-size: 12px;
            outline: none;
        }
        .op-stack {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .op-panel-body {
            padding: 12px;
        }
        .op-form {
            display: grid;
            gap: 9px;
        }
        .op-field label {
            display: block;
            margin-bottom: 4px;
            color: var(--op-muted);
            font-size: 11px;
            font-weight: 760;
        }
        .op-field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .op-list {
            display: grid;
            gap: 9px;
        }
        .op-list-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: start;
            padding-bottom: 9px;
            border-bottom: 1px solid var(--op-line);
        }
        .op-list-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .op-empty {
            color: var(--op-muted);
            font-size: 12px;
            line-height: 1.55;
        }
        .op-mobile-toggle {
            display: none;
        }
        .op-hidden-row {
            display: none;
        }
        @media (max-width: 1180px) {
            .op-sidebar {
                align-items: flex-start;
                flex-wrap: wrap;
            }
            .op-sidebar-bottom {
                margin-left: 0;
            }
            .op-mobile-toggle {
                display: none;
            }
            .op-overview {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 960px) {
            .op-stack {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 720px) {
            .op-main {
                padding: 12px;
            }
            .op-sidebar {
                position: static;
                display: grid;
                grid-template-columns: 1fr;
            }
            .op-nav {
                width: 100%;
            }
            .op-sidebar-bottom {
                width: 100%;
                justify-content: space-between;
            }
            .op-topbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .op-field-row,
            .op-inline-form {
                grid-template-columns: 1fr;
            }
            .op-search {
                width: 100%;
            }
            .op-section-head {
                display: grid;
            }
            .op-table {
                min-width: 0;
            }
            .op-table thead {
                display: none;
            }
            .op-table,
            .op-table tbody,
            .op-table tr,
            .op-table td {
                display: block;
                width: 100%;
            }
            .op-table tr {
                padding: 14px 0;
                border-bottom: 1px solid var(--op-line);
            }
            .op-table tr:last-child {
                border-bottom: 0;
            }
            .op-table td {
                border-bottom: 0;
                padding: 9px 18px;
            }
            .op-table td::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 5px;
                color: var(--op-muted);
                font-size: 11px;
                font-weight: 820;
                text-transform: uppercase;
                letter-spacing: .04em;
            }
            .op-inline-form {
                min-width: 0;
            }
        }
    </style>
</head>
<body>
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
            <a href="#migrations"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>Migrations</a>
            <a href="#billing"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 7h16v10H4zM4 10h16M8 15h4"/></svg>Billing</a>
            <a href="<?php echo e(url('dashboard')); ?>"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 6H5v13h13v-5M14 5h5v5M13 11l6-6"/></svg>Open helpdesk</a>
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
                <h1>Platform console</h1>
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

        <section class="op-overview" id="overview">
            <div class="op-card op-compact-card primary">
                <div class="op-compact-title">
                    <span class="op-label">Health</span>
                    <span class="op-status-dot <?php echo $health_class === 'warn' ? 'warn' : ''; ?>"></span>
                </div>
                <strong><?php echo e($health_label); ?></strong>
                <div class="op-sub"><?php echo $active_tenants; ?> / <?php echo $tenant_count; ?> active or trialing. Past due <?php echo (int) ($summary['past_due_tenants'] ?? 0); ?>, blocked <?php echo (int) ($summary['blocked_tenants'] ?? 0); ?>.</div>
                <div class="op-actions">
                    <a class="op-btn primary" href="#workspaces">Workspaces</a>
                    <a class="op-btn" href="#migrations">Import</a>
                </div>
            </div>
            <div class="op-card op-compact-card"><span class="op-label">Workspaces</span><strong><?php echo $tenant_count; ?></strong><div class="op-sub">Total tenants</div></div>
            <div class="op-card op-compact-card"><span class="op-label">Active/trial</span><strong><?php echo $active_tenants; ?></strong><div class="op-sub">With app access</div></div>
            <div class="op-card op-compact-card"><span class="op-label">Users</span><strong><?php echo (int) ($summary['users'] ?? 0); ?></strong><div class="op-sub">Across all workspaces</div></div>
            <div class="op-card op-compact-card"><span class="op-label">Storage</span><strong><?php echo e(format_file_size($total_storage_bytes)); ?></strong><div class="op-sub"><?php echo (int) $total_extra_gb; ?> extra GB</div></div>
            <div class="op-card op-compact-card"><span class="op-label">Overage</span><strong><?php echo e(billing_format_money($estimated_storage_overage_cents)); ?></strong><div class="op-sub">Current period estimate</div></div>
        </section>

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
                                ?>
                                <tr data-workspace-row data-search="<?php echo e($search_text); ?>">
                                    <td data-label="Workspace">
                                        <div class="op-name"><?php echo e($tenant['name']); ?></div>
                                        <div class="op-sub">/<?php echo e($tenant['slug']); ?> · ID <?php echo $tenant_id; ?></div>
                                        <div class="op-sub">Created <?php echo e(substr((string) $tenant['created_at'], 0, 10)); ?></div>
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
                                            <div class="op-sub"><?php echo (int) $usage['tickets']; ?> tickets · latest <?php echo e($tenant['last_ticket_at'] ? substr((string) $tenant['last_ticket_at'], 0, 10) : 'none'); ?></div>
                                        </div>
                                    </td>
                                    <td data-label="Billing">
                                        <div class="op-name"><?php echo e(billing_plan_name()); ?></div>
                                        <div class="op-sub"><?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/mo base</div>
                                        <div class="op-sub"><?php echo (int) $usage['extra_storage_gb']; ?> extra GB · <?php echo e(billing_format_money($usage['storage_overage_cents'])); ?></div>
                                    </td>
                                    <td data-label="Lifecycle">
                                        <form method="post" class="op-inline-form">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="platform_action" value="update_tenant">
                                            <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                            <select class="op-select" name="status" aria-label="Workspace status">
                                                <?php foreach (['active', 'trialing', 'past_due', 'suspended', 'canceled'] as $status): ?>
                                                    <option value="<?php echo $status; ?>" <?php echo ($tenant['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input class="op-input" name="subscription_status" value="<?php echo e($tenant['subscription_status'] ?? 'manual'); ?>" aria-label="Billing status">
                                            <button class="op-btn" type="submit">Save</button>
                                        </form>
                                        <div class="op-actions mt-2 justify-start">
                                            <span class="op-pill <?php echo e($status_class); ?>"><?php echo e($tenant['status']); ?></span>
                                            <a class="op-pill" href="<?php echo e(url('billing', ['tenant_id' => $tenant_id])); ?>">Billing detail</a>
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
                                    <input class="op-input" name="admin_first_name" placeholder="Lukas" required>
                                </div>
                                <div class="op-field">
                                    <label>Owner last name</label>
                                    <input class="op-input" name="admin_last_name" placeholder="Hanes">
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
                                    </select>
                                </div>
                            </div>
                            <button class="op-btn primary" type="submit">Create workspace</button>
                        </form>
                    </div>
                </section>

                <section class="op-card" id="migrations">
                    <div class="op-section-head">
                        <div>
                            <h2>Migration import</h2>
                            <p>Bring an existing self-hosted FoxDesk into Cloud.</p>
                        </div>
                    </div>
                    <div class="op-panel-body">
                        <form method="post" enctype="multipart/form-data" class="op-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="platform_action" value="import_migration">
                            <div class="op-field">
                                <label>Migration ZIP</label>
                                <input class="op-input" type="file" name="migration_package" accept=".zip,application/zip" required>
                            </div>
                            <div class="op-field">
                                <label>New workspace name</label>
                                <input class="op-input" name="migration_workspace_name" placeholder="Imported FoxDesk" required>
                            </div>
                            <div class="op-field">
                                <label>Billing email override</label>
                                <input class="op-input" type="email" name="migration_billing_email" placeholder="billing@example.com">
                            </div>
                            <div class="op-field-row">
                                <div class="op-field">
                                    <label>Workspace status</label>
                                    <select class="op-select" name="migration_status">
                                        <option value="trialing">trialing</option>
                                        <option value="active">active</option>
                                    </select>
                                </div>
                                <div class="op-field">
                                    <label>Billing status</label>
                                    <select class="op-select" name="migration_subscription_status">
                                        <option value="manual">manual</option>
                                        <option value="trialing">trialing</option>
                                        <option value="active">active</option>
                                    </select>
                                </div>
                            </div>
                            <button class="op-btn primary" type="submit">Import migration</button>
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

                <section class="op-card">
                    <div class="op-section-head">
                        <div>
                            <h2>Recent imports</h2>
                            <p>Latest self-hosted migration packages.</p>
                        </div>
                    </div>
                    <div class="op-panel-body op-list">
                        <?php if (!$migration_imports): ?>
                            <div class="op-empty">No migrations imported yet.</div>
                        <?php endif; ?>
                        <?php foreach ($migration_imports as $import): ?>
                            <div class="op-list-row">
                                <span>
                                    <span class="op-label"><?php echo e($import['status']); ?></span>
                                    <div class="op-name"><?php echo e($import['tenant_name'] ?: 'Import without tenant'); ?></div>
                                    <div class="op-sub"><?php echo e($import['created_at']); ?> · <?php echo e($import['created_by_email'] ?: 'system'); ?></div>
                                </span>
                                <span class="op-pill <?php echo e($import['status'] === 'completed' ? 'good' : 'notice'); ?>"><?php echo e($import['imported_tickets']); ?> tickets</span>
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
