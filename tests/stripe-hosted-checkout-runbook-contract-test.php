<?php

$root = dirname(__DIR__);

$runbook = file_get_contents($root . '/docs/STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md');
$template = file_get_contents($root . '/docs/stripe-hosted-checkout-evidence.template.json');
$setup = file_get_contents($root . '/docs/STRIPE_PUBLIC_BETA_SETUP.md');
$tracker = file_get_contents($root . '/docs/feature-user-stories.csv');
$launch_gate = file_get_contents($root . '/bin/launch-go-no-go.js');
$package = file_get_contents($root . '/package.json');

if (
    $runbook === false
    || $template === false
    || $setup === false
    || $tracker === false
    || $launch_gate === false
    || $package === false
) {
    fwrite(STDERR, "Unable to read hosted Checkout runbook contract files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

foreach ([
    'BILLING-002',
    'Do not use live payment details',
    'Redact Checkout Session URLs',
    'VAT ID',
    'reverse-charge',
    'checkout.session.completed',
    'invoice.payment_failed',
    'invoice.paid',
    'subscription deletion',
    'billing.stripe.com',
    'php bin/test-stripe-billing-flow.php --json',
    'php bin/test-stripe-webhook-lifecycle.php --json',
] as $required) {
    $assert(str_contains($runbook, $required), "Runbook must include {$required}.");
}

$evidence = json_decode($template, true);
$assert(is_array($evidence), 'Hosted Checkout evidence template must be valid JSON.');
foreach ([
    'stripe_mode',
    'workspace',
    'checkout',
    'webhooks',
    'customer_portal',
    'cleanup',
    'safe_smoke_commands',
] as $required_key) {
    $assert(array_key_exists($required_key, $evidence), "Evidence template missing {$required_key}.");
}

$assert(($evidence['checkout']['host'] ?? '') === 'checkout.stripe.com', 'Evidence template must require Stripe Checkout host.');
$assert(($evidence['customer_portal']['host'] ?? '') === 'billing.stripe.com', 'Evidence template must require Stripe Portal host.');
$assert(array_key_exists('reverse_charge_or_zero_rate_observed', $evidence['checkout']), 'Evidence template must track reverse-charge/zero-rate observation.');

$assert(str_contains($setup, 'STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md'), 'Stripe setup guide must link the hosted Checkout runbook.');
$assert(str_contains($tracker, 'STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md'), 'Feature tracker must reference the hosted Checkout runbook for BILLING-002.');
$assert(str_contains($tracker, 'needs_external_smoke'), 'BILLING-002 must remain marked external-smoke until hosted Checkout evidence exists.');
$assert(str_contains($launch_gate, 'STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md'), 'Launch gate acknowledgement must point to the runbook.');
$assert(str_contains($package, '"test:stripe-hosted-checkout-runbook"'), 'package.json must expose the hosted Checkout runbook contract.');

echo "Stripe hosted Checkout runbook contract OK\n";
