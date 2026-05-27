<?php
/**
 * Billing actions and status page.
 */

$user = current_user();
if (!$user || !is_admin()) {
    header('Location: index.php?page=dashboard');
    exit;
}

$requested_tenant_id = (int) ($_GET['tenant_id'] ?? current_tenant_id());
if ($requested_tenant_id !== current_tenant_id() && !is_platform_admin($user)) {
    http_response_code(403);
    header('Location: index.php?page=dashboard');
    exit;
}

$tenant = billing_get_tenant($requested_tenant_id);
if (!$tenant) {
    http_response_code(404);
    echo 'Workspace not found.';
    exit;
}

$action = (string) ($_GET['action'] ?? '');
if ($action === 'checkout' || $action === 'portal') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php?page=billing');
        exit;
    }
    require_csrf_token();

    try {
        if ($action === 'checkout') {
            $plan = (string) ($_POST['plan'] ?? billing_plan_code());
            $url = billing_create_checkout_session((int) $tenant['id'], $plan);
        } else {
            $url = billing_create_portal_session((int) $tenant['id']);
        }
        header('Location: ' . $url);
        exit;
    } catch (Throwable $e) {
        flash($e->getMessage(), 'error');
        $back = is_platform_admin($user) ? url('platform') : url('billing');
        header('Location: ' . $back);
        exit;
    }
}

$page_title = 'Billing';
$page = 'billing';
$access_state = function_exists('billing_workspace_access_state')
    ? billing_workspace_access_state($tenant)
    : ['allowed' => true, 'reason' => (string) ($tenant['status'] ?? ''), 'message' => ''];
$tenant = billing_get_tenant((int) $tenant['id']) ?: $tenant;
$trial_days_remaining = billing_trial_days_remaining($tenant);
$usage = billing_tenant_usage((int) $tenant['id']);
$checkout_state = (string) ($_GET['checkout'] ?? $_GET['billing'] ?? '');
$storage_percent = $usage['included_storage_bytes'] > 0
    ? min(100, (int) round(($usage['storage_bytes'] / $usage['included_storage_bytes']) * 100))
    : 0;
require_once BASE_PATH . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card card-body">
        <h1 class="text-2xl font-bold mb-2">Billing</h1>
        <p class="text-sm mb-6" style="color: var(--text-muted);">Manage subscription and access for <?php echo e($tenant['name']); ?>.</p>

        <?php if ($checkout_state === 'success'): ?>
            <div class="alert alert-success mb-5">Payment setup is complete. Stripe will confirm the subscription shortly.</div>
        <?php elseif ($checkout_state === 'cancelled'): ?>
            <div class="alert alert-warning mb-5">Checkout was cancelled. Your workspace is still available, but the subscription has not started.</div>
        <?php elseif (isset($_GET['signup'])): ?>
            <div class="alert alert-info mb-5">Workspace created. Your 14-day trial is active. Add billing before it ends to keep access.</div>
        <?php elseif (empty($access_state['allowed'])): ?>
            <div class="alert alert-error mb-5"><?php echo e($access_state['message']); ?></div>
        <?php endif; ?>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Plan</dt><dd class="font-semibold"><?php echo e(billing_plan_name()); ?></dd></div>
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Workspace status</dt><dd class="font-semibold"><?php echo e($tenant['status']); ?></dd></div>
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Subscription</dt><dd class="font-semibold"><?php echo e($tenant['subscription_status'] ?? 'manual'); ?></dd></div>
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Billing email</dt><dd class="font-semibold"><?php echo e($tenant['billing_email'] ?? ''); ?></dd></div>
            <?php if ((string) ($tenant['subscription_status'] ?? '') === 'trialing' && $trial_days_remaining !== null): ?>
                <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Trial</dt><dd class="font-semibold"><?php echo $trial_days_remaining; ?> day<?php echo $trial_days_remaining === 1 ? '' : 's'; ?> remaining</dd></div>
            <?php endif; ?>
        </dl>

        <div class="rounded-xl border p-4 mb-6" style="border-color: var(--border-light); background: var(--surface-secondary);">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-semibold"><?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/month</h2>
                    <p class="text-sm" style="color: var(--text-muted);">Unlimited users, clients, agents, and tickets. <?php echo e(format_file_size($usage['included_storage_bytes'])); ?> storage included.</p>
                </div>
                <div class="text-sm sm:text-right">
                    <strong><?php echo e(billing_format_money(billing_storage_overage_price_cents())); ?>/extra GB</strong>
                    <div style="color: var(--text-muted);">metered monthly</div>
                </div>
            </div>

            <div class="mb-3">
                <div class="flex items-center justify-between text-sm mb-1">
                    <span>Storage used</span>
                    <span><?php echo e(format_file_size($usage['storage_bytes'])); ?> / <?php echo e(format_file_size($usage['included_storage_bytes'])); ?></span>
                </div>
                <div class="h-2 rounded-full overflow-hidden" style="background: var(--border-light);">
                    <div class="h-full" style="width: <?php echo $storage_percent; ?>%; background: var(--primary);"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span style="color: var(--text-muted);">Users</span><strong class="block"><?php echo (int) $usage['users']; ?></strong></div>
                <div><span style="color: var(--text-muted);">Clients</span><strong class="block"><?php echo (int) $usage['clients']; ?></strong></div>
                <div><span style="color: var(--text-muted);">Agents</span><strong class="block"><?php echo (int) $usage['agents']; ?></strong></div>
                <div><span style="color: var(--text-muted);">Extra storage</span><strong class="block"><?php echo (int) $usage['extra_storage_gb']; ?> GB</strong></div>
            </div>

            <?php if ($usage['extra_storage_gb'] > 0): ?>
                <div class="alert alert-info mt-4 mb-0">Estimated storage overage this month: <?php echo e(billing_format_money($usage['storage_overage_cents'])); ?>.</div>
            <?php endif; ?>
        </div>

        <?php if (!billing_enabled()): ?>
            <div class="alert alert-info mb-5">Billing is prepared but not enabled. Configure Stripe keys and set BILLING_ENABLED=true.</div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row gap-3">
            <form method="post" action="<?php echo url('billing', ['action' => 'checkout', 'tenant_id' => (int) $tenant['id']]); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="plan" value="<?php echo e(billing_plan_code()); ?>">
                <button class="btn btn-primary" type="submit">Activate FoxDesk</button>
            </form>
            <form method="post" action="<?php echo url('billing', ['action' => 'portal', 'tenant_id' => (int) $tenant['id']]); ?>">
                <?php echo csrf_field(); ?>
                <button class="btn btn-secondary" type="submit">Manage billing</button>
            </form>
            <?php if (is_platform_admin($user)): ?>
                <a href="<?php echo url('platform'); ?>" class="btn btn-ghost">Back to platform</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
