<?php
/**
 * Billing actions and status page.
 */

$user = current_user();
if (!$user || !is_admin()) {
    $redirect_page = function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard';
    header('Location: index.php?page=' . $redirect_page);
    exit;
}

$requested_tenant_id = (int) ($_GET['tenant_id'] ?? current_tenant_id());
if ($requested_tenant_id !== current_tenant_id() && !is_platform_admin($user)) {
    http_response_code(403);
    $redirect_page = function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard';
    header('Location: index.php?page=' . $redirect_page);
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
        $billing_action_state = function_exists('billing_tenant_billing_action_state')
            ? billing_tenant_billing_action_state($tenant)
            : ['show_checkout' => true, 'show_portal' => !empty($tenant['stripe_customer_id'])];

        if ($action === 'checkout') {
            if (empty($billing_action_state['show_checkout'])) {
                throw new RuntimeException('This workspace does not need a checkout action in its current billing state.');
            }
            $plan = (string) ($_POST['plan'] ?? billing_plan_code());
            log_security_event('billing_checkout_requested', (int) ($user['id'] ?? 0), 'tenant_id=' . (int) $tenant['id'] . ';plan=' . str_replace([';', "\r", "\n"], ['_', ' ', ' '], $plan));
            $url = billing_create_checkout_session((int) $tenant['id'], $plan);
        } else {
            if (empty($billing_action_state['show_portal'])) {
                throw new RuntimeException('The billing portal is not available for this workspace state.');
            }
            log_security_event('billing_portal_requested', (int) ($user['id'] ?? 0), 'tenant_id=' . (int) $tenant['id']);
            $url = billing_create_portal_session((int) $tenant['id']);
        }
        header('Location: ' . $url);
        exit;
    } catch (Throwable $e) {
        $safe_error = str_replace([';', "\r", "\n"], ['_', ' ', ' '], $e->getMessage());
        log_security_event('billing_action_failed', (int) ($user['id'] ?? 0), 'tenant_id=' . (int) $tenant['id'] . ';action=' . $action . ';error=' . $safe_error);
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
$trial_grace_ends_at = billing_trial_grace_ends_at($tenant);
$past_due_grace_ends_at = billing_past_due_grace_ends_at($tenant);
$usage = billing_tenant_usage((int) $tenant['id']);
$billing_action_state = function_exists('billing_tenant_billing_action_state')
    ? billing_tenant_billing_action_state($tenant, $access_state)
    : [
        'show_checkout' => true,
        'checkout_label' => 'Start subscription',
        'show_portal' => !empty($tenant['stripe_customer_id']),
        'portal_label' => 'Manage billing',
        'notice_title' => '',
        'notice_body' => '',
        'notice_variant' => 'info',
    ];
$checkout_state = (string) ($_GET['checkout'] ?? $_GET['billing'] ?? '');
$storage_percent = $usage['included_storage_bytes'] > 0
    ? min(100, (int) round(($usage['storage_bytes'] / $usage['included_storage_bytes']) * 100))
    : 0;
require_once BASE_PATH . '/includes/header.php';
?>

<div class="billing-page">
    <div class="card card-body billing-card">
        <h1 class="billing-title">Billing</h1>
        <p class="billing-muted billing-intro">Manage subscription and access for <?php echo e($tenant['name']); ?>.</p>

        <?php if ($checkout_state === 'success'): ?>
            <div class="alert alert-success mb-5">Payment setup is complete. Stripe will confirm the subscription shortly.</div>
        <?php elseif ($checkout_state === 'cancelled'): ?>
            <div class="alert alert-warning mb-5">Checkout was cancelled. Your workspace is still available, but the subscription has not started.</div>
        <?php elseif (isset($_GET['signup'])): ?>
            <div class="alert alert-info mb-5">Workspace created. Your <?php echo billing_trial_days(); ?>-day trial is active. Add billing before it ends to keep access.</div>
        <?php elseif (empty($access_state['allowed'])): ?>
            <div class="alert alert-error mb-5"><?php echo e($access_state['message']); ?></div>
        <?php elseif (($access_state['reason'] ?? '') === 'past_due_grace' && $past_due_grace_ends_at): ?>
            <div class="alert alert-warning mb-5">Payment is past due. Update billing before <?php echo e(format_date($past_due_grace_ends_at)); ?> to avoid suspension.</div>
        <?php endif; ?>

        <dl class="billing-summary-grid">
            <div class="billing-fact"><dt>Plan</dt><dd><?php echo e(billing_plan_name()); ?></dd></div>
            <div class="billing-fact"><dt>Workspace status</dt><dd><?php echo e($tenant['status']); ?></dd></div>
            <div class="billing-fact"><dt>Subscription</dt><dd><?php echo e($tenant['subscription_status'] ?? 'manual'); ?></dd></div>
            <div class="billing-fact"><dt>Billing email</dt><dd><?php echo e($tenant['billing_email'] ?? ''); ?></dd></div>
            <?php if ((string) ($tenant['subscription_status'] ?? '') === 'trialing' && $trial_days_remaining !== null): ?>
                <div class="billing-fact">
                    <dt>Trial</dt>
                    <dd>
                        <?php if ($trial_days_remaining > 0): ?>
                            <?php echo $trial_days_remaining; ?> day<?php echo $trial_days_remaining === 1 ? '' : 's'; ?> remaining
                        <?php elseif ($trial_grace_ends_at): ?>
                            Grace ends <?php echo e(format_date($trial_grace_ends_at)); ?>
                        <?php else: ?>
                            Grace active
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if ((string) ($tenant['status'] ?? '') === 'past_due' && $past_due_grace_ends_at): ?>
                <div class="billing-fact"><dt>Payment grace</dt><dd>Until <?php echo e(format_date($past_due_grace_ends_at)); ?></dd></div>
            <?php endif; ?>
        </dl>

        <section class="billing-plan-panel">
            <div class="billing-plan-head">
                <div>
                    <h2><?php echo e(billing_format_money(billing_cloud_base_price_cents())); ?>/month</h2>
                    <p class="billing-muted">Unlimited users, clients, agents, and tickets. <?php echo e(format_file_size($usage['included_storage_bytes'])); ?> storage included.</p>
                </div>
                <div class="billing-overage-price">
                    <strong><?php echo e(billing_format_money(billing_storage_overage_price_cents())); ?>/extra GB</strong>
                    <div>metered monthly</div>
                </div>
            </div>

            <div class="billing-storage">
                <div class="billing-storage-row">
                    <span>Storage used</span>
                    <span><?php echo e(format_file_size($usage['storage_bytes'])); ?> / <?php echo e(format_file_size($usage['included_storage_bytes'])); ?></span>
                </div>
                <progress class="billing-storage-progress" value="<?php echo (int) $storage_percent; ?>" max="100"></progress>
            </div>

            <div class="billing-usage-grid">
                <div class="billing-usage-stat"><span>Users</span><strong><?php echo (int) $usage['users']; ?></strong></div>
                <div class="billing-usage-stat"><span>Clients</span><strong><?php echo (int) $usage['clients']; ?></strong></div>
                <div class="billing-usage-stat"><span>Agents</span><strong><?php echo (int) $usage['agents']; ?></strong></div>
                <div class="billing-usage-stat"><span>Extra storage</span><strong><?php echo (int) $usage['extra_storage_gb']; ?> GB</strong></div>
                <div class="billing-usage-stat"><span>Local storage</span><strong><?php echo e(format_file_size((int) $usage['storage_local_bytes'])); ?></strong></div>
                <div class="billing-usage-stat"><span>R2 storage</span><strong><?php echo e(format_file_size((int) $usage['storage_r2_bytes'])); ?></strong></div>
                <div class="billing-usage-stat"><span>API requests</span><strong><?php echo (int) $usage['api_requests']; ?></strong></div>
                <div class="billing-usage-stat"><span>Email volume</span><strong><?php echo (int) $usage['inbound_email_total']; ?> in / <?php echo (int) $usage['outbound_email_sent']; ?> out</strong></div>
            </div>

            <?php if ($usage['extra_storage_gb'] > 0): ?>
                <div class="alert alert-info mt-4 mb-0">Estimated storage overage this month: <?php echo e(billing_format_money($usage['storage_overage_cents'])); ?>.</div>
            <?php endif; ?>
        </section>

        <?php if (!billing_enabled()): ?>
            <div class="alert alert-info mb-5">Billing is prepared but not enabled. Configure Stripe keys and set BILLING_ENABLED=true.</div>
        <?php endif; ?>

        <?php if (!empty($billing_action_state['notice_title']) || !empty($billing_action_state['notice_body'])): ?>
            <?php
            $notice_variant = (string) ($billing_action_state['notice_variant'] ?? 'info');
            $notice_class = $notice_variant === 'warning' ? 'alert-warning' : 'alert-info';
            ?>
            <div class="alert <?php echo e($notice_class); ?> mb-5">
                <?php if (!empty($billing_action_state['notice_title'])): ?>
                    <strong><?php echo e($billing_action_state['notice_title']); ?></strong>
                <?php endif; ?>
                <?php if (!empty($billing_action_state['notice_body'])): ?>
                    <span><?php echo e($billing_action_state['notice_body']); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="billing-actions">
            <?php if (!empty($billing_action_state['show_checkout'])): ?>
                <form method="post" action="<?php echo url('billing', ['action' => 'checkout', 'tenant_id' => (int) $tenant['id']]); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="plan" value="<?php echo e(billing_plan_code()); ?>">
                    <button class="btn btn-primary" type="submit"><?php echo e((string) ($billing_action_state['checkout_label'] ?? 'Start subscription')); ?></button>
                </form>
            <?php endif; ?>
            <?php if (!empty($billing_action_state['show_portal'])): ?>
                <form method="post" action="<?php echo url('billing', ['action' => 'portal', 'tenant_id' => (int) $tenant['id']]); ?>">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-secondary" type="submit"><?php echo e((string) ($billing_action_state['portal_label'] ?? 'Manage billing')); ?></button>
                </form>
            <?php endif; ?>
            <?php if (is_platform_admin($user)): ?>
                <a href="<?php echo url('platform'); ?>" class="btn btn-ghost">Back to platform</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
