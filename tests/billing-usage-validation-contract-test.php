<?php

$root = dirname(__DIR__);

$billing = file_get_contents($root . '/includes/billing-functions.php');
$report = file_get_contents($root . '/bin/report-billing-usage.php');
$validate = file_get_contents($root . '/bin/validate-stripe-usage.php');
$docs = file_get_contents($root . '/docs/STRIPE_BILLING.md');

if ($billing === false || $report === false || $validate === false || $docs === false) {
    fwrite(STDERR, "Unable to read billing usage validation files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($billing, 'function billing_usage_reporting_config_status'), 'Billing config validation helper is missing.');
$assert(str_contains($billing, 'function billing_stripe_tax_enabled'), 'Stripe Tax enablement helper is missing.');
$assert(str_contains($billing, 'function billing_tax_id_collection_enabled'), 'Tax ID collection helper is missing.');
$assert(str_contains($billing, "'automatic_tax[enabled]'"), 'Checkout must enable Stripe automatic tax when configured.');
$assert(str_contains($billing, "'tax_id_collection[enabled]'"), 'Checkout must collect VAT/tax IDs when configured.');
$assert(str_contains($billing, "'billing_address_collection'"), 'Checkout must collect billing address for tax location.');
$assert(str_contains($billing, 'function billing_find_meter_by_event_name'), 'Stripe meter lookup helper is missing.');
$assert(str_contains($billing, 'function billing_meter_event_summaries'), 'Stripe meter summary helper is missing.');
$assert(str_contains($billing, 'function billing_invoice_preview_for_customer'), 'Stripe invoice preview helper is missing.');
$assert(str_contains($billing, "in_array(\$method, ['GET', 'DELETE'], true)"), 'Stripe GET requests must encode query parameters in the URL.');
$assert(!str_contains($billing, "['reported', 'dry_run']"), 'Dry-run usage rows must not block a later live Stripe report.');
$assert(str_contains($billing, "\$existing_status === 'reported'"), 'Reported usage rows should remain idempotent.');
$assert(str_contains($report, "'tenant-id:'"), 'Usage report command must support tenant-scoped validation.');
$assert(str_contains($report, "'period:'"), 'Usage report command must support period-scoped validation.');
$assert(str_contains($validate, "'live'"), 'Validation command must support live test reports.');
$assert(str_contains($validate, "'allow-live-key'"), 'Validation command must guard live Stripe keys.');
$assert(str_contains($validate, 'billing_find_meter_by_event_name'), 'Validation command must check the configured Stripe meter.');
$assert(str_contains($validate, 'billing_report_storage_usage_for_tenant'), 'Validation command must run tenant-scoped reporting.');
$assert(str_contains($validate, 'billing_meter_event_summaries'), 'Validation command must verify meter summaries when possible.');
$assert(str_contains($validate, 'billing_invoice_preview_for_customer'), 'Validation command must verify invoice preview when possible.');
$assert(str_contains($docs, 'validate-stripe-usage.php'), 'Stripe billing docs must document the validation command.');
$assert(str_contains($docs, 'tax_behavior=exclusive'), 'Stripe billing docs must document exclusive tax behavior.');
$assert(str_contains($docs, 'txcd_10103001'), 'Stripe billing docs must document SaaS business-use tax code.');

echo "Billing usage validation contract OK\n";
