<?php
/**
 * Platform control plane for the SaaS operator.
 */

require_platform_admin();

$page_title = 'FoxDesk Cloud';
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
            $subscription_status = (string) ($_POST['subscription_status'] ?? 'manual');
            $allowed_statuses = ['active', 'trialing', 'past_due', 'suspended', 'canceled'];
            if ($tenant_id <= 0 || !in_array($status, $allowed_statuses, true)) {
                throw new InvalidArgumentException('Invalid workspace update.');
            }
            db_update('tenants', [
                'status' => $status,
                'subscription_status' => $subscription_status,
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

$total_storage_bytes = 0;
$total_extra_gb = 0;
$estimated_storage_overage_cents = 0;
$tenant_usage = [];
foreach ($tenants as $tenant) {
    $usage = billing_tenant_usage((int) $tenant['id']);
    $tenant_usage[(int) $tenant['id']] = $usage;
    $total_storage_bytes += (int) $usage['storage_bytes'];
    $total_extra_gb += (int) $usage['extra_storage_gb'];
    $estimated_storage_overage_cents += (int) $usage['storage_overage_cents'];
}

migration_ensure_imports_table();
$migration_imports = db_fetch_all("
    SELECT mi.*, t.name AS tenant_name, t.slug AS tenant_slug, u.email AS created_by_email
    FROM migration_imports mi
    LEFT JOIN tenants t ON t.id = mi.tenant_id
    LEFT JOIN users u ON u.id = mi.created_by
    ORDER BY mi.created_at DESC, mi.id DESC
    LIMIT 5
");

require_once BASE_PATH . '/includes/header.php';
?>

<style>
    .saas-wrap {
        max-width: 1500px;
        margin: 0 auto;
        padding: 24px;
    }
    .saas-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 18px;
        align-items: stretch;
        margin-bottom: 18px;
    }
    .saas-panel {
        background: var(--surface-primary);
        border: 1px solid var(--border-light);
        border-radius: 8px;
        box-shadow: var(--shadow-sm);
    }
    .saas-title {
        padding: 24px;
    }
    .saas-title h1 {
        margin: 0;
        color: var(--text-primary);
        font-size: 2.25rem;
        line-height: 1.12;
        font-weight: 800;
        letter-spacing: 0;
    }
    .saas-title p {
        max-width: 760px;
        margin: 10px 0 0;
        color: var(--text-muted);
        font-size: .98rem;
        line-height: 1.6;
    }
    .saas-plan {
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 16px;
    }
    .saas-plan-label,
    .saas-muted {
        color: var(--text-muted);
    }
    .saas-plan strong {
        display: block;
        margin-top: 4px;
        color: var(--text-primary);
        font-size: 1.8rem;
        line-height: 1.1;
    }
    .saas-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }
    .saas-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }
    .saas-kpi {
        padding: 16px;
    }
    .saas-kpi span {
        display: block;
        color: var(--text-muted);
        font-size: .78rem;
        font-weight: 650;
    }
    .saas-kpi strong {
        display: block;
        margin-top: 8px;
        color: var(--text-primary);
        font-size: 1.55rem;
        line-height: 1.1;
    }
    .saas-layout {
        display: grid;
        grid-template-columns: 380px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }
    .saas-form {
        padding: 18px;
    }
    .saas-form h2,
    .saas-table-head h2,
    .saas-side h2 {
        margin: 0;
        color: var(--text-primary);
        font-size: 1.05rem;
        font-weight: 780;
    }
    .saas-form p,
    .saas-table-head p,
    .saas-side p {
        margin: 4px 0 0;
        color: var(--text-muted);
        font-size: .84rem;
        line-height: 1.5;
    }
    .saas-fields {
        display: grid;
        gap: 12px;
        margin-top: 16px;
    }
    .saas-field label {
        display: block;
        margin-bottom: 6px;
        color: var(--text-secondary);
        font-size: .78rem;
        font-weight: 700;
    }
    .saas-field-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .saas-table-head {
        padding: 18px 18px 8px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }
    .saas-table-wrap {
        overflow-x: auto;
    }
    .saas-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
    }
    .saas-table th,
    .saas-table td {
        padding: 14px 18px;
        border-top: 1px solid var(--border-light);
        text-align: left;
        vertical-align: top;
        font-size: .88rem;
    }
    .saas-table th {
        color: var(--text-muted);
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-weight: 760;
    }
    .saas-name {
        color: var(--text-primary);
        font-weight: 760;
    }
    .saas-sub {
        margin-top: 3px;
        color: var(--text-muted);
        font-size: .78rem;
    }
    .saas-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 26px;
        border-radius: 999px;
        padding: 0 10px;
        font-size: .74rem;
        font-weight: 760;
        background: var(--surface-secondary);
        color: var(--text-secondary);
    }
    .saas-status.active,
    .saas-status.trialing {
        background: var(--success-soft);
        color: var(--success);
    }
    .saas-status.past_due {
        background: var(--warning-soft);
        color: var(--warning);
    }
    .saas-status.suspended,
    .saas-status.canceled {
        background: var(--danger-soft);
        color: var(--danger);
    }
    .saas-usage {
        display: grid;
        gap: 7px;
        min-width: 150px;
    }
    .saas-bar {
        height: 7px;
        border-radius: 999px;
        background: var(--surface-tertiary);
        overflow: hidden;
    }
    .saas-bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--primary);
    }
    .saas-control {
        width: 100%;
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: .5rem .65rem;
        background: var(--surface-primary);
        color: var(--text-primary);
        font-size: .86rem;
    }
    .saas-inline-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 128px;
    }
    .saas-side {
        display: grid;
        gap: 14px;
    }
    .saas-side-card {
        padding: 18px;
    }
    .saas-list {
        display: grid;
        gap: 12px;
        margin-top: 14px;
    }
    .saas-list-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border-light);
        font-size: .88rem;
    }
    .saas-list-row:first-child {
        border-top: 0;
        padding-top: 0;
    }
    .saas-list-row span {
        color: var(--text-muted);
    }
    .saas-list-row strong {
        text-align: right;
        color: var(--text-primary);
    }
    @media (max-width: 1200px) {
        .saas-hero,
        .saas-layout {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 900px) {
        .saas-wrap {
            padding: 16px;
        }
        .saas-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .saas-title h1 {
            font-size: 1.85rem;
        }
    }
    @media (max-width: 640px) {
        .saas-kpis,
        .saas-field-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="saas-wrap">
    <?php if ($error): ?><div class="alert alert-error mb-4"><?php echo e($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success mb-4"><?php echo e($success); ?></div><?php endif; ?>

    <section class="saas-hero">
        <div class="saas-panel saas-title">
            <h1>FoxDesk Cloud</h1>
            <p>Modern SaaS control plane for creating customer FoxDesks, monitoring usage, managing billing state, and keeping every customer helpdesk isolated.</p>
            <div class="saas-actions">
                <a href="<?php echo e(url('signup')); ?>" class="btn btn-primary" style="width:auto;">Public signup</a>
                <a href="<?php echo e(url('dashboard')); ?>" class="btn btn-secondary" style="width:auto;">Open current FoxDesk</a>
            </div>
        </div>
        <aside class="saas-panel saas-plan">
            <div>
                <div class="saas-plan-label text-sm">One scalable plan</div>
                <strong><?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/mo</strong>
                <p class="saas-muted text-sm mt-2 mb-0">Unlimited users, clients, agents, and tickets. <?php echo e(format_file_size(billing_included_storage_bytes())); ?> storage included.</p>
            </div>
            <div class="saas-list-row">
                <span>Extra storage</span>
                <strong><?php echo e(billing_format_money(billing_storage_overage_price_cents())); ?>/GB</strong>
            </div>
        </aside>
    </section>

    <section class="saas-kpis">
        <div class="saas-panel saas-kpi"><span>Workspaces</span><strong><?php echo (int) ($summary['tenants'] ?? 0); ?></strong></div>
        <div class="saas-panel saas-kpi"><span>Active or trialing</span><strong><?php echo (int) ($summary['active_tenants'] ?? 0); ?></strong></div>
        <div class="saas-panel saas-kpi"><span>Billing OK</span><strong><?php echo (int) ($summary['paying_or_trialing'] ?? 0); ?></strong></div>
        <div class="saas-panel saas-kpi"><span>Storage used</span><strong><?php echo e(format_file_size($total_storage_bytes)); ?></strong></div>
    </section>

    <div class="saas-layout">
        <div class="saas-side">
            <section class="saas-panel saas-form">
                <h2>Create FoxDesk</h2>
                <p>Create an isolated workspace and first admin account.</p>
                <form method="post" class="saas-fields">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="platform_action" value="create_workspace">
                    <div class="saas-field">
                        <label>Workspace name</label>
                        <input class="form-input w-full" name="workspace_name" placeholder="Acme Support" required>
                    </div>
                    <div class="saas-field-row">
                        <div class="saas-field">
                            <label>Owner first name</label>
                            <input class="form-input w-full" name="admin_first_name" placeholder="Lukas" required>
                        </div>
                        <div class="saas-field">
                            <label>Owner last name</label>
                            <input class="form-input w-full" name="admin_last_name" placeholder="Hanes">
                        </div>
                    </div>
                    <div class="saas-field">
                        <label>Owner email</label>
                        <input class="form-input w-full" type="email" name="admin_email" placeholder="owner@example.com" required>
                    </div>
                    <div class="saas-field">
                        <label>Temporary password</label>
                        <input class="form-input w-full" type="password" name="password" placeholder="At least 12 characters" minlength="12" required>
                    </div>
                    <div class="saas-field-row">
                        <div class="saas-field">
                            <label>Workspace status</label>
                            <select class="form-select w-full" name="status">
                                <option value="trialing">trialing</option>
                                <option value="active">active</option>
                            </select>
                        </div>
                        <div class="saas-field">
                            <label>Billing status</label>
                            <select class="form-select w-full" name="subscription_status">
                                <option value="trialing">trialing</option>
                                <option value="active">active</option>
                                <option value="manual">manual</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary w-full" type="submit">Create FoxDesk</button>
                </form>
            </section>

            <section class="saas-panel saas-form">
                <h2>Import self-hosted FoxDesk</h2>
                <p>Upload a migration ZIP exported from an existing self-hosted installation.</p>
                <form method="post" enctype="multipart/form-data" class="saas-fields">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="platform_action" value="import_migration">
                    <div class="saas-field">
                        <label>Migration package</label>
                        <input class="form-input w-full" type="file" name="migration_package" accept=".zip,application/zip" required>
                    </div>
                    <div class="saas-field">
                        <label>New workspace name</label>
                        <input class="form-input w-full" name="migration_workspace_name" placeholder="Imported FoxDesk" required>
                    </div>
                    <div class="saas-field">
                        <label>Billing email override</label>
                        <input class="form-input w-full" type="email" name="migration_billing_email" placeholder="billing@example.com">
                    </div>
                    <div class="saas-field-row">
                        <div class="saas-field">
                            <label>Workspace status</label>
                            <select class="form-select w-full" name="migration_status">
                                <option value="trialing">trialing</option>
                                <option value="active">active</option>
                            </select>
                        </div>
                        <div class="saas-field">
                            <label>Billing status</label>
                            <select class="form-select w-full" name="migration_subscription_status">
                                <option value="manual">manual</option>
                                <option value="trialing">trialing</option>
                                <option value="active">active</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary w-full" type="submit">Import migration</button>
                </form>
            </section>

            <section class="saas-panel saas-side-card">
                <h2>Usage economics</h2>
                <p>Storage is the growth lever for margin and billing.</p>
                <div class="saas-list">
                    <div class="saas-list-row"><span>Total extra GB</span><strong><?php echo (int) $total_extra_gb; ?> GB</strong></div>
                    <div class="saas-list-row"><span>Estimated overage</span><strong><?php echo e(billing_format_money($estimated_storage_overage_cents)); ?></strong></div>
                    <div class="saas-list-row"><span>Target gross margin</span><strong>~10x</strong></div>
                </div>
            </section>

            <section class="saas-panel saas-side-card">
                <h2>Recent migrations</h2>
                <p>Last self-hosted imports into the SaaS control plane.</p>
                <div class="saas-list">
                    <?php if (!$migration_imports): ?>
                        <div class="saas-muted text-sm">No migrations imported yet.</div>
                    <?php endif; ?>
                    <?php foreach ($migration_imports as $import): ?>
                        <div class="saas-list-row">
                            <span>
                                <?php echo e($import['tenant_name'] ?: 'Failed import'); ?>
                                <br><small><?php echo e($import['created_at']); ?></small>
                            </span>
                            <strong><?php echo e($import['status']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="saas-panel">
            <div class="saas-table-head">
                <div>
                    <h2>Customer FoxDesks</h2>
                    <p>Manage tenant state, owner, billing and storage usage from one place.</p>
                </div>
            </div>
            <div class="saas-table-wrap">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Owner</th>
                            <th>Usage</th>
                            <th>Billing</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                            <?php
                                $usage = $tenant_usage[(int) $tenant['id']];
                                $included = max(1, (int) $usage['included_storage_bytes']);
                                $storage_percent = min(100, (int) round(((int) $usage['storage_bytes'] / $included) * 100));
                                $status_class = preg_replace('/[^a-z_]/', '', (string) ($tenant['status'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="saas-name"><?php echo e($tenant['name']); ?></div>
                                    <div class="saas-sub">/<?php echo e($tenant['slug']); ?></div>
                                </td>
                                <td>
                                    <div class="saas-name"><?php echo e(trim(($tenant['owner_first_name'] ?? '') . ' ' . ($tenant['owner_last_name'] ?? '')) ?: 'No owner'); ?></div>
                                    <div class="saas-sub"><?php echo e($tenant['owner_email'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <div class="saas-usage">
                                        <div class="saas-sub"><?php echo e(format_file_size((int) $usage['storage_bytes'])); ?> / <?php echo e(format_file_size($included)); ?></div>
                                        <div class="saas-bar"><span style="width: <?php echo $storage_percent; ?>%;"></span></div>
                                        <div class="saas-sub"><?php echo (int) $usage['users']; ?> users, <?php echo (int) $usage['clients']; ?> clients, <?php echo (int) $usage['tickets']; ?> tickets</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="saas-name"><?php echo e(billing_plan_name()); ?></div>
                                    <div class="saas-sub"><?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/mo + <?php echo e(billing_format_money(billing_storage_overage_price_cents())); ?>/GB</div>
                                    <div class="saas-sub"><?php echo (int) $usage['extra_storage_gb']; ?> extra GB, <?php echo e(billing_format_money($usage['storage_overage_cents'])); ?></div>
                                </td>
                                <td>
                                    <form method="post" class="grid gap-2 min-w-[150px]">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="platform_action" value="update_tenant">
                                        <input type="hidden" name="tenant_id" value="<?php echo (int) $tenant['id']; ?>">
                                        <span class="saas-status <?php echo e($status_class); ?>"><?php echo e($tenant['status']); ?></span>
                                        <select class="saas-control" name="status">
                                            <?php foreach (['active', 'trialing', 'past_due', 'suspended', 'canceled'] as $status): ?>
                                                <option value="<?php echo $status; ?>" <?php echo ($tenant['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="saas-control" name="subscription_status" value="<?php echo e($tenant['subscription_status'] ?? 'manual'); ?>">
                                </td>
                                <td>
                                        <div class="saas-inline-actions">
                                            <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                                            <button class="btn btn-ghost btn-sm" formaction="<?php echo url('billing', ['action' => 'checkout', 'tenant_id' => (int) $tenant['id']]); ?>" formmethod="post" name="plan" value="<?php echo e(billing_plan_code()); ?>" type="submit">Checkout</button>
                                            <button class="btn btn-ghost btn-sm" formaction="<?php echo url('billing', ['action' => 'portal', 'tenant_id' => (int) $tenant['id']]); ?>" formmethod="post" type="submit">Portal</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
