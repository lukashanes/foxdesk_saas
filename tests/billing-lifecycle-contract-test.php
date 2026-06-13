<?php

$root = dirname(__DIR__);

define('BILLING_TRIAL_GRACE_DAYS', 2);
define('BILLING_PAST_DUE_GRACE_DAYS', 5);
define('BILLING_ENABLED', true);

require_once $root . '/includes/billing-functions.php';

$billing = file_get_contents($root . '/includes/billing-functions.php');
$maintenance = file_get_contents($root . '/bin/run-maintenance.php');
$cron = file_get_contents($root . '/pages/cron.php');
$docs = file_get_contents($root . '/docs/STRIPE_BILLING.md');

if ($billing === false || $maintenance === false || $cron === false || $docs === false) {
    fwrite(STDERR, "Unable to read billing lifecycle files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(billing_trial_grace_days() === 2, 'Trial grace days should be configurable.');
$assert(billing_past_due_grace_days() === 5, 'Past-due grace days should be configurable.');

$trial_grace = billing_trial_grace_ends_at([
    'trial_ends_at' => date('Y-m-d H:i:s', time() - 3600),
]);
$assert($trial_grace !== null && strtotime($trial_grace) > time(), 'Trial grace should extend access after nominal trial end.');

$past_due_grace = billing_past_due_grace_ends_at([
    'suspended_at' => date('Y-m-d H:i:s', time() - 86400),
]);
$assert($past_due_grace !== null && strtotime($past_due_grace) > time(), 'Past-due grace should be based on suspended_at.');

$assert(str_contains($billing, 'function billing_suspend_past_due_tenants'), 'Past-due suspension helper is missing.');
$assert(str_contains($billing, 'function billing_lifecycle_state_matrix'), 'Billing lifecycle state matrix helper is missing.');
$assert(str_contains($billing, 'function billing_tenant_lifecycle_state'), 'Billing tenant lifecycle state helper is missing.');
$assert(str_contains($billing, 'migrated_pending_cutover'), 'Billing matrix must define migrated pending cutover state.');
$assert(str_contains($billing, "status = 'suspended'"), 'Past-due lifecycle must suspend workspaces after grace.');
$assert(str_contains($billing, "'past_due_grace'"), 'Workspace access must allow past_due during grace.');
$assert(str_contains($billing, 'billing_suspend_past_due_tenants((int) $tenant'), 'Access checks must enforce overdue suspension before allowing app pages.');
$assert(str_contains($billing, 'COALESCE(suspended_at, NOW())'), 'Past-due start time should be preserved or initialized once.');
$assert(str_contains($billing, "\$updates['suspended_at'] = \$past_due_started_at"), 'Failed invoices must preserve the original past-due start time.');
$assert(str_contains($billing, 'foxdesk_platform_url'), 'Platform admins must return from Stripe portal to the platform host.');
$assert(str_contains($billing, 'foxdesk_workspace_url'), 'Workspace admins must return from Stripe portal to the workspace host.');
$assert(!str_contains($billing, "? APP_URL . '/index.php?page=platform'"), 'Stripe portal return must not send platform admins to APP_URL.');
$assert(str_contains($maintenance, "'past_due_suspension'"), 'Maintenance JSON must include past-due suspension results.');
$assert(str_contains($cron, 'billing_suspend_past_due_tenants'), 'Pseudo-cron must run past-due suspension.');
$assert(str_contains($docs, 'BILLING_PAST_DUE_GRACE_DAYS'), 'Stripe billing docs must document past-due grace.');

$matrix = billing_lifecycle_state_matrix();
foreach (['trialing', 'active', 'manual_free', 'past_due_grace', 'suspended', 'cancelled', 'migrated_pending_cutover'] as $state_code) {
    $assert(isset($matrix[$state_code]), "Billing state matrix is missing {$state_code}.");
    $assert(array_key_exists('access_allowed', $matrix[$state_code]), "{$state_code} must define app access.");
    $assert(array_key_exists('show_checkout', $matrix[$state_code]), "{$state_code} must define Checkout visibility.");
    $assert(array_key_exists('show_portal', $matrix[$state_code]), "{$state_code} must define Portal visibility.");
    $assert(array_key_exists('platform_buttons', $matrix[$state_code]), "{$state_code} must define platform buttons.");
    $assert(array_key_exists('workspace_buttons', $matrix[$state_code]), "{$state_code} must define workspace buttons.");
}

$assert(billing_tenant_lifecycle_state([
    'status' => 'active',
    'subscription_status' => 'free',
])['code'] === 'manual_free', 'Free active tenant should map to manual_free.');
$assert(billing_tenant_lifecycle_state([
    'status' => 'canceled',
    'subscription_status' => 'canceled',
])['code'] === 'cancelled', 'Canceled tenant should map to the cancelled matrix state.');

foreach (['manual', 'free', 'comped'] as $manual_status) {
    $manual_action = billing_tenant_billing_action_state([
        'id' => 1,
        'status' => 'active',
        'subscription_status' => $manual_status,
        'stripe_customer_id' => '',
        'stripe_subscription_id' => '',
    ], ['allowed' => true, 'reason' => $manual_status, 'message' => '']);
    $assert(empty($manual_action['show_checkout']), "{$manual_status} workspaces must not show checkout.");
    $assert(!empty($manual_action['show_portal']), "{$manual_status} workspaces should allow billing detail management.");
    $assert(($manual_action['portal_label'] ?? '') === 'Manage billing details', "{$manual_status} portal label should clarify billing details.");
    $assert(($manual_action['notice_title'] ?? '') === 'All set', "{$manual_status} notice title should say no action is needed.");
    $assert(str_contains((string) ($manual_action['notice_body'] ?? ''), 'platform-approved access'), "{$manual_status} notice should explain platform-approved access.");
}

$trial_action = billing_tenant_billing_action_state([
    'id' => 2,
    'status' => 'trialing',
    'subscription_status' => 'trialing',
    'trial_ends_at' => date('Y-m-d H:i:s', time() + 86400),
], ['allowed' => true, 'reason' => 'trialing', 'message' => '']);
$assert(!empty($trial_action['show_checkout']), 'Trial workspaces should show an add billing action.');
$assert(($trial_action['checkout_label'] ?? '') === 'Add billing', 'Trial checkout label should be Add billing.');

$active_paid_action = billing_tenant_billing_action_state([
    'id' => 3,
    'status' => 'active',
    'subscription_status' => 'active',
    'stripe_customer_id' => 'cus_test',
    'stripe_subscription_id' => 'sub_test',
], ['allowed' => true, 'reason' => 'active', 'message' => '']);
$assert(empty($active_paid_action['show_checkout']), 'Active paid workspaces must not show checkout.');
$assert(!empty($active_paid_action['show_portal']), 'Active paid workspaces should show billing portal.');

$past_due_action = billing_tenant_billing_action_state([
    'id' => 4,
    'status' => 'past_due',
    'subscription_status' => 'past_due',
    'stripe_customer_id' => 'cus_test',
    'stripe_subscription_id' => 'sub_test',
], ['allowed' => true, 'reason' => 'past_due_grace', 'message' => '']);
$assert(empty($past_due_action['show_checkout']), 'Past-due Stripe workspaces should use portal instead of checkout.');
$assert(!empty($past_due_action['show_portal']), 'Past-due Stripe workspaces should show update payment.');
$assert(($past_due_action['portal_label'] ?? '') === 'Update payment', 'Past-due portal label should be Update payment.');

$suspended_past_due_state = billing_tenant_lifecycle_state([
    'status' => 'suspended',
    'subscription_status' => 'past_due',
    'stripe_customer_id' => 'cus_test',
    'stripe_subscription_id' => 'sub_test',
]);
$assert($suspended_past_due_state['code'] === 'suspended', 'Suspended workspaces must not be downgraded to past-due grace by subscription status.');
$assert(empty($suspended_past_due_state['access_allowed']), 'Suspended past-due workspaces must not have app access.');
$assert(str_contains((string) ($suspended_past_due_state['access_message'] ?? ''), 'We could not process payment'), 'Suspended past-due workspaces should explain the payment failure.');

$blocked_action = billing_tenant_billing_action_state([
    'id' => 5,
    'status' => 'blocked',
    'subscription_status' => 'blocked',
], ['allowed' => false, 'reason' => 'blocked', 'message' => '']);
$assert(empty($blocked_action['show_checkout']), 'Blocked workspaces must not show checkout.');
$assert(empty($blocked_action['show_portal']), 'Blocked workspaces must not show billing portal.');

$billing_page = file_get_contents($root . '/pages/billing.php');
$header = file_get_contents($root . '/includes/header.php');
$tenant_functions = file_get_contents($root . '/includes/tenant-functions.php');
$platform_module = file_get_contents($root . '/includes/modules/platform/operator-console.php');
$platform_page = file_get_contents($root . '/pages/platform.php');
$assert($billing_page !== false && $header !== false, 'Unable to read billing UI files.');
$assert($tenant_functions !== false && $platform_module !== false && $platform_page !== false, 'Unable to read platform billing files.');
$assert(!str_contains($billing_page, 'Start paid subscription'), 'Billing page must not use the old paid subscription CTA.');
$assert(!str_contains($billing_page, 'Billing is off for this workspace. Platform admins can enable it from production settings.'), 'Billing page must not render a duplicate billing-off alert outside the action state notice.');
$assert(!str_contains($header, 'Activate FoxDesk'), 'Header must not show the old Activate FoxDesk CTA.');
$assert(str_contains($tenant_functions, 'billing_override_reason'), 'Tenants must store a billing override reason.');
$assert(str_contains($tenant_functions, 'billing_override_by'), 'Tenants must store the operator that set a billing override.');
$assert(str_contains($platform_module, 'platform_operator_reason'), 'Platform overrides must normalize an explicit reason.');
$assert(str_contains($platform_page, 'name="override_reason"'), 'Platform UI must submit a billing override reason.');
$assert(str_contains($platform_page, "'reason' => \$reason"), 'Platform audit context must include override reasons.');

echo "Billing lifecycle contract OK\n";
