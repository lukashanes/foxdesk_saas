<?php

$root = dirname(__DIR__);

$billing = file_get_contents($root . '/includes/billing-functions.php');
$mailer = file_get_contents($root . '/includes/mailer.php');
$router = file_get_contents($root . '/includes/api/router.php');
$ingest = file_get_contents($root . '/includes/email-ingest-functions.php');
$platform = file_get_contents($root . '/pages/platform.php');
$billing_page = file_get_contents($root . '/pages/billing.php');
$schema = file_get_contents($root . '/includes/schema.sql');
$tenant = file_get_contents($root . '/includes/tenant-functions.php');
$migration = file_get_contents($root . '/includes/migration-functions.php');
$docs = file_get_contents($root . '/docs/STRIPE_BILLING.md');

if (
    $billing === false || $mailer === false || $router === false || $ingest === false ||
    $platform === false || $billing_page === false || $schema === false ||
    $tenant === false || $migration === false || $docs === false
) {
    fwrite(STDERR, "Unable to read billing usage counter files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($billing, 'function billing_ensure_usage_events_table'), 'Usage event table helper is missing.');
$assert(str_contains($billing, 'function billing_record_usage_event'), 'Usage event recorder is missing.');
$assert(str_contains($billing, 'function billing_storage_breakdown'), 'Storage breakdown helper is missing.');
$assert(str_contains($billing, 'function billing_volume_counters'), 'Email/API volume counter helper is missing.');
$assert(str_contains($billing, "'storage_local_bytes'"), 'Tenant usage must expose local storage bytes.');
$assert(str_contains($billing, "'storage_r2_bytes'"), 'Tenant usage must expose R2 storage bytes.');
$assert(str_contains($billing, "'api_requests'"), 'Tenant usage must expose API request counts.');
$assert(str_contains($billing, "'outbound_email_sent'"), 'Tenant usage must expose outbound email counts.');
$assert(str_contains($billing, "'inbound_email_total'"), 'Tenant usage must expose inbound email counts.');
$assert(str_contains($billing, "'storage_bytes' => (int)"), 'Billing storage total must remain the canonical overage input.');
$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS billing_usage_events'), 'Schema must include billing_usage_events.');
$assert(str_contains($tenant, "'billing_usage_events'"), 'Tenant-owned tables must include billing_usage_events.');
$assert(str_contains($migration, "'billing_usage_events'"), 'Migration export must include billing_usage_events.');
$assert(str_contains($mailer, 'function mailer_record_usage_event'), 'Mailer must record outbound usage events.');
$assert(str_contains($mailer, "'email.sent'"), 'Mailer must record sent email volume.');
$assert(str_contains($mailer, "'email.failed'"), 'Mailer must record failed email volume.');
$assert(str_contains($mailer, "'email.skipped'"), 'Mailer must record skipped email volume.');
$assert(str_contains($router, 'function api_record_usage_request'), 'API router must record request volume.');
$assert(str_contains($router, "'api.request'"), 'API router must record api.request events.');
$assert(str_contains($ingest, 'tenant_id INT NULL'), 'Email ingest logs must be tenant-aware.');
$assert(str_contains($ingest, 'COALESCE(l.tenant_id, t.tenant_id)') || str_contains($billing, 'COALESCE(l.tenant_id, t.tenant_id)'), 'Inbound email volume must resolve tenant ownership.');
$assert(str_contains($platform, 'Local <?php echo e(format_file_size'), 'Platform UI must show local/R2 storage breakdown.');
$assert(str_contains($platform, '$total_inbound_email_total') && str_contains($platform, '$total_outbound_email_sent'), 'Platform UI must show email/API activity.');
$assert(str_contains($billing_page, 'Local storage'), 'Billing page must show local storage.');
$assert(str_contains($billing_page, 'R2 storage'), 'Billing page must show R2 storage.');
$assert(str_contains($billing_page, 'API requests'), 'Billing page must show API usage.');
$assert(str_contains($docs, 'billing_usage_events'), 'Stripe billing docs must document usage events.');

echo "Billing usage counters contract OK\n";
