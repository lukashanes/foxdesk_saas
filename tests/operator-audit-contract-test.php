<?php

$root = dirname(__DIR__);

$platform_module = file_get_contents($root . '/includes/modules/platform/operator-console.php');
$platform_page = file_get_contents($root . '/pages/platform.php');
$billing_page = file_get_contents($root . '/pages/billing.php');

if ($platform_module === false || $platform_page === false || $billing_page === false) {
    fwrite(STDERR, "Unable to read operator audit files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($platform_module, 'function platform_log_operator_action'), 'Platform audit helper is missing.');
$assert(str_contains($platform_module, 'log_security_event($event_type'), 'Platform audit helper must write to the security log.');
$assert(str_contains($platform_module, "str_replace([';', \"\\r\", \"\\n\"]"), 'Platform audit context must sanitize separators.');
$assert(str_contains($platform_page, "'reason' => \$reason"), 'Manual billing overrides must write the reason to the audit context.');

foreach ([
    'platform_workspace_created',
    'platform_workspace_lifecycle_updated',
    'platform_trial_extended',
    'platform_workspace_blocked',
    'platform_workspace_reactivated',
    'platform_workspace_free_access_granted',
    'platform_owner_reset_link_created',
    'platform_migration_token_created',
] as $event_type) {
    $assert(str_contains($platform_page, $event_type), "Platform action {$event_type} must be logged.");
}

foreach ([
    'billing_checkout_requested',
    'billing_portal_requested',
    'billing_action_failed',
] as $event_type) {
    $assert(str_contains($billing_page, $event_type), "Billing action {$event_type} must be logged.");
}

$assert(!str_contains($platform_page, "operator_secret ."), 'Platform audit must not log migration token values.');

echo "Operator audit contract OK\n";
