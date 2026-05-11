<?php
/**
 * Platform control plane for the SaaS operator.
 */

require_platform_admin();

$page_title = 'Platform';
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
                'plan' => $_POST['plan'] ?? 'starter',
                'max_users' => (int) ($_POST['max_users'] ?? 10),
                'max_agents' => (int) ($_POST['max_agents'] ?? 3),
            ]);
            $success = 'Workspace created.';
        } elseif ($action === 'update_tenant') {
            $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'active');
            $subscription_status = (string) ($_POST['subscription_status'] ?? 'manual');
            $plan = trim((string) ($_POST['plan'] ?? 'starter'));
            $allowed_statuses = ['active', 'trialing', 'past_due', 'suspended', 'canceled'];
            if ($tenant_id <= 0 || !in_array($status, $allowed_statuses, true)) {
                throw new InvalidArgumentException('Invalid workspace update.');
            }
            db_update('tenants', [
                'status' => $status,
                'subscription_status' => $subscription_status,
                'plan' => $plan !== '' ? $plan : 'starter',
                'max_users' => max(1, (int) ($_POST['max_users'] ?? 10)),
                'max_agents' => max(0, (int) ($_POST['max_agents'] ?? 3)),
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
      SUM(subscription_status IN ('active','trialing')) AS paying_or_trialing,
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
      (SELECT COUNT(*) FROM tickets tx WHERE tx.tenant_id = t.id) AS ticket_count
    FROM tenants t
    LEFT JOIN users u ON u.id = t.owner_user_id
    ORDER BY t.created_at DESC, t.id DESC
");

require_once BASE_PATH . '/includes/header.php';
?>

<style>
    .platform-grid { display: grid; gap: 1rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .platform-metric { border: 1px solid var(--border-light); background: var(--surface-primary); border-radius: .75rem; padding: 1rem; }
    .platform-metric span { display: block; font-size: .75rem; color: var(--text-muted); }
    .platform-metric strong { display: block; margin-top: .25rem; font-size: 1.7rem; color: var(--text-primary); }
    .platform-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .platform-table th, .platform-table td { padding: .75rem; border-bottom: 1px solid var(--border-light); text-align: left; vertical-align: top; }
    .platform-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); }
    .platform-control { border: 1px solid var(--border-light); border-radius: .5rem; padding: .4rem .55rem; background: var(--surface-primary); color: var(--text-primary); }
    @media (max-width: 900px) { .platform-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .platform-grid { grid-template-columns: 1fr; } }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Platform</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Manage SaaS workspaces, owners, plans, limits, and subscription state.</p>
        </div>
        <a href="<?php echo url('signup'); ?>" class="btn btn-secondary self-start md:self-auto" style="width: auto;">Public signup</a>
    </div>

    <?php if ($error): ?><div class="alert alert-error mb-4"><?php echo e($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success mb-4"><?php echo e($success); ?></div><?php endif; ?>

    <div class="platform-grid mb-6">
        <div class="platform-metric"><span>Workspaces</span><strong><?php echo (int) ($summary['tenants'] ?? 0); ?></strong></div>
        <div class="platform-metric"><span>Active or trialing</span><strong><?php echo (int) ($summary['active_tenants'] ?? 0); ?></strong></div>
        <div class="platform-metric"><span>Billing OK</span><strong><?php echo (int) ($summary['paying_or_trialing'] ?? 0); ?></strong></div>
        <div class="platform-metric"><span>Total users</span><strong><?php echo (int) ($summary['users'] ?? 0); ?></strong></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[380px_1fr] gap-5">
        <section class="card card-body">
            <h2 class="font-semibold mb-4">Create workspace</h2>
            <form method="post" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="platform_action" value="create_workspace">
                <input class="form-input w-full" name="workspace_name" placeholder="Workspace name" required>
                <div class="grid grid-cols-2 gap-2">
                    <input class="form-input w-full" name="admin_first_name" placeholder="Owner first name" required>
                    <input class="form-input w-full" name="admin_last_name" placeholder="Owner last name">
                </div>
                <input class="form-input w-full" type="email" name="admin_email" placeholder="Owner email" required>
                <input class="form-input w-full" type="password" name="password" placeholder="Temporary password" minlength="12" required>
                <div class="grid grid-cols-2 gap-2">
                    <input class="form-input w-full" name="plan" value="starter" placeholder="Plan">
                    <select class="form-select w-full" name="status">
                        <option value="trialing">trialing</option>
                        <option value="active">active</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input class="form-input w-full" type="number" name="max_users" value="10" min="1">
                    <input class="form-input w-full" type="number" name="max_agents" value="3" min="0">
                </div>
                <button class="btn btn-primary w-full" type="submit">Create FoxDesk</button>
            </form>
        </section>

        <section class="card card-body overflow-x-auto">
            <h2 class="font-semibold mb-4">FoxDesks</h2>
            <table class="platform-table">
                <thead>
                    <tr>
                        <th>Workspace</th>
                        <th>Owner</th>
                        <th>Usage</th>
                        <th>Billing</th>
                        <th>Limits</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($tenant['name']); ?></strong>
                            <div class="text-xs" style="color: var(--text-muted);">/<?php echo e($tenant['slug']); ?></div>
                        </td>
                        <td>
                            <?php echo e(trim(($tenant['owner_first_name'] ?? '') . ' ' . ($tenant['owner_last_name'] ?? '')) ?: 'No owner'); ?>
                            <div class="text-xs" style="color: var(--text-muted);"><?php echo e($tenant['owner_email'] ?? ''); ?></div>
                        </td>
                        <td class="text-sm">
                            <?php echo (int) $tenant['user_count']; ?> users<br>
                            <span style="color: var(--text-muted);"><?php echo (int) $tenant['ticket_count']; ?> tickets</span>
                        </td>
                        <td>
                            <form method="post" class="flex flex-col gap-2 min-w-[170px]">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="platform_action" value="update_tenant">
                                <input type="hidden" name="tenant_id" value="<?php echo (int) $tenant['id']; ?>">
                                <input class="platform-control" name="plan" value="<?php echo e($tenant['plan']); ?>">
                                <input class="platform-control" name="subscription_status" value="<?php echo e($tenant['subscription_status'] ?? 'manual'); ?>">
                        </td>
                        <td>
                                <div class="flex gap-2">
                                    <input class="platform-control w-20" type="number" name="max_users" value="<?php echo (int) ($tenant['max_users'] ?? 10); ?>" min="1">
                                    <input class="platform-control w-20" type="number" name="max_agents" value="<?php echo (int) ($tenant['max_agents'] ?? 3); ?>" min="0">
                                </div>
                                <select class="platform-control mt-2 w-full" name="status">
                                    <?php foreach (['active', 'trialing', 'past_due', 'suspended', 'canceled'] as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo ($tenant['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                    <?php endforeach; ?>
                                </select>
                        </td>
                        <td>
                                <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
