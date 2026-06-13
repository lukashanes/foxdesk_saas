<?php

$root = dirname(__DIR__);

$module = file_get_contents($root . '/includes/modules/platform/operator-console.php');
$platform = file_get_contents($root . '/pages/platform.php');
$docs = file_get_contents($root . '/docs/NEXT_STEPS.md');

if ($module === false || $platform === false || $docs === false) {
    fwrite(STDERR, "Unable to read platform operator detail files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($module, 'function platform_tenant_detail_context'), 'Tenant detail context helper is missing.');
$assert(str_contains($module, 'function platform_send_owner_reset'), 'Owner reset email helper is missing.');
$assert(str_contains($module, 'function platform_invite_or_set_owner'), 'Owner invite helper is missing.');
$assert(str_contains($module, 'function platform_grant_free_access'), 'Free platform override helper is missing.');
$assert(str_contains($module, 'function platform_operator_reason'), 'Platform override reason helper is missing.');
$assert(str_contains($module, "'subscription_status' => 'free'"), 'Free platform override must set subscription_status=free.');
$assert(str_contains($module, "'status' => 'active'"), 'Free platform override must keep the workspace active.');
$assert(str_contains($module, "'billing_override_reason' => \$reason"), 'Free platform override must persist the operator reason.');
$assert(str_contains($module, "'billing_override_by' => (int) (\$_SESSION['user_id'] ?? 0)"), 'Free platform override must persist the operator id.');
$assert(str_contains($module, "'blocked_at' => null"), 'Free platform override must clear blocked_at.');
$assert(str_contains($module, 'billing_stripe_events'), 'Subscription history must include Stripe events.');
$assert(str_contains($module, 'billing_usage_reports'), 'Subscription history must include usage reports.');
$assert(str_contains($module, 'billing_trial_email_events'), 'Subscription history must include trial email events.');
$assert(str_contains($module, 'billing_usage_events'), 'Usage overview must include monthly usage events.');
$assert(str_contains($module, 'log_security_event'), 'Owner access changes should be security logged.');
$assert(str_contains($platform, 'id="tenant-detail"'), 'Platform page must render tenant detail.');
$assert(str_contains($platform, 'Owner access'), 'Platform tenant detail must expose owner access controls.');
$assert(str_contains($platform, 'Subscription history'), 'Platform tenant detail must expose subscription history.');
$assert(str_contains($platform, 'Usage overview'), 'Platform tenant detail must expose usage overview.');
$assert(str_contains($platform, 'Open detail'), 'Workspace catalog must link to tenant detail.');
$assert(str_contains($platform, 'name="platform_action" value="grant_free_access"'), 'Platform UI must expose a one-click free access action.');
$assert(str_contains($platform, 'name="override_reason"'), 'Platform UI must collect or submit a free access reason.');
$assert(str_contains($platform, 'Free access'), 'Platform UI must label the free access action clearly.');
$assert(str_contains($platform, 'Platform override'), 'Platform UI must explain manual/free billing overrides.');
$assert(str_contains($docs, 'operator tenant detail'), 'Next steps docs must mention completed operator detail.');

echo "Platform operator detail contract OK\n";
