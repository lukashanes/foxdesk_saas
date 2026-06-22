<?php

$root = dirname(__DIR__);

$script = file_get_contents($root . '/bin/test-stripe-billing-flow.php');
$package = file_get_contents($root . '/package.json');

if ($script === false || $package === false) {
    fwrite(STDERR, "Unable to read Stripe billing flow smoke files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($script, 'billing_create_checkout_session'), 'Smoke script must create a real Checkout Session through the billing helper.');
$assert(str_contains($script, 'billing_create_portal_session'), 'Smoke script must create a real Customer Portal Session through the billing helper.');
$assert(str_contains($script, "'checkout/sessions/' . rawurlencode(\$checkout_session_id)"), 'Smoke script must retrieve the Checkout Session from Stripe.');
$assert(str_contains($script, "'checkout/sessions/' . rawurlencode(\$checkout_session_id) . '/line_items'"), 'Smoke script must verify Checkout line items.');
$assert(str_contains($script, "'checkout/sessions/' . rawurlencode(\$checkout_session_id) . '/expire'"), 'Smoke script must expire the temporary Checkout Session.');
$assert(str_contains($script, "'DELETE', 'customers/' . rawurlencode(\$stripe_customer_id)"), 'Smoke script must delete the temporary Stripe customer.');
$assert(str_contains($script, 'parse_url($checkout_url, PHP_URL_HOST)'), 'Smoke script must report only the Checkout host, not the full URL.');
$assert(str_contains($script, 'parse_url($portal_url, PHP_URL_HOST)'), 'Smoke script must report only the Portal host, not the full URL.');
$assert(str_contains($script, "'automatic_tax_matches_config'"), 'Smoke script must verify automatic tax configuration.');
$assert(str_contains($script, "'tax_id_collection_matches_config'"), 'Smoke script must verify tax ID collection configuration.');
$assert(str_contains($script, "'line_items_include_storage_price'"), 'Smoke script must verify storage overage price wiring.');
$assert(str_contains($script, 'db_delete(\'tenants\', \'id = ?\''), 'Smoke script must clean the temporary tenant.');
$assert(str_contains($package, '"test:stripe-billing-flow"'), 'package.json must expose the Stripe billing flow smoke contract.');

echo "Stripe billing flow smoke contract OK\n";
