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
            $plan = (string) ($_POST['plan'] ?? ($tenant['plan'] ?? 'starter'));
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
require_once BASE_PATH . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card card-body">
        <h1 class="text-2xl font-bold mb-2">Billing</h1>
        <p class="text-sm mb-6" style="color: var(--text-muted);">Manage subscription and access for <?php echo e($tenant['name']); ?>.</p>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Plan</dt><dd class="font-semibold"><?php echo e($tenant['plan']); ?></dd></div>
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Workspace status</dt><dd class="font-semibold"><?php echo e($tenant['status']); ?></dd></div>
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Subscription</dt><dd class="font-semibold"><?php echo e($tenant['subscription_status'] ?? 'manual'); ?></dd></div>
            <div><dt class="text-xs uppercase" style="color: var(--text-muted);">Billing email</dt><dd class="font-semibold"><?php echo e($tenant['billing_email'] ?? ''); ?></dd></div>
        </dl>

        <?php if (!billing_enabled()): ?>
            <div class="alert alert-info mb-5">Billing is prepared but not enabled. Configure Stripe keys and set BILLING_ENABLED=true.</div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row gap-3">
            <form method="post" action="<?php echo url('billing', ['action' => 'checkout', 'tenant_id' => (int) $tenant['id']]); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="plan" value="<?php echo e($tenant['plan'] ?: 'starter'); ?>">
                <button class="btn btn-primary" type="submit">Start subscription</button>
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
