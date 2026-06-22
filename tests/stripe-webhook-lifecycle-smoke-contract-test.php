<?php

$root = dirname(__DIR__);

$script = file_get_contents($root . '/bin/test-stripe-webhook-lifecycle.php');
$package = file_get_contents($root . '/package.json');
$docs = file_get_contents($root . '/docs/STRIPE_BILLING.md');

if ($script === false || $package === false || $docs === false) {
    fwrite(STDERR, "Unable to read Stripe webhook lifecycle smoke files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($script, 'billing_handle_webhook_event'), 'Webhook lifecycle smoke must use the real billing webhook handler.');
$assert(str_contains($script, 'checkout.session.completed'), 'Webhook lifecycle smoke must cover checkout completion.');
$assert(str_contains($script, 'invoice.payment_failed'), 'Webhook lifecycle smoke must cover failed payment.');
$assert(str_contains($script, 'invoice.paid'), 'Webhook lifecycle smoke must cover paid invoice recovery.');
$assert(str_contains($script, 'customer.subscription.deleted'), 'Webhook lifecycle smoke must cover subscription cancellation.');
$assert(str_contains($script, 'duplicate_checkout_guarded'), 'Webhook lifecycle smoke must prove duplicate event idempotency.');
$assert(str_contains($script, 'failed_payment_preserves_grace_clock'), 'Webhook lifecycle smoke must prove repeated failures preserve the grace clock.');
$assert(str_contains($script, 'subscription_deleted_blocks_access_clock'), 'Webhook lifecycle smoke must prove cancellation sets a blocking timestamp.');
$assert(str_contains($script, "'tax_ids'"), 'Webhook lifecycle smoke must include VAT/tax id shaped Checkout data.');
$assert(str_contains($script, 'DELETE FROM billing_stripe_events'), 'Webhook lifecycle smoke must clean temporary Stripe event rows.');
$assert(str_contains($script, "db_delete('tenants', 'id = ?'"), 'Webhook lifecycle smoke must clean the temporary tenant.');
$assert(str_contains($package, '"test:stripe-webhook-lifecycle"'), 'package.json must expose the Stripe webhook lifecycle smoke contract.');
$assert(str_contains($docs, 'test-stripe-webhook-lifecycle.php'), 'Stripe billing docs must document the webhook lifecycle smoke.');

echo "Stripe webhook lifecycle smoke contract OK\n";
