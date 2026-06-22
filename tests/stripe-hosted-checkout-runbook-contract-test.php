<?php

$root = dirname(__DIR__);

$runbook = file_get_contents($root . '/docs/STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md');
$template = file_get_contents($root . '/docs/stripe-hosted-checkout-evidence.template.json');
$setup = file_get_contents($root . '/docs/STRIPE_PUBLIC_BETA_SETUP.md');
$tracker = file_get_contents($root . '/docs/feature-user-stories.csv');
$launch_gate = file_get_contents($root . '/bin/launch-go-no-go.js');
$prepare = file_get_contents($root . '/bin/prepare-stripe-hosted-checkout-evidence.js');
$checklist = file_get_contents($root . '/bin/stripe-hosted-checkout-checklist.js');
$package = file_get_contents($root . '/package.json');

if (
    $runbook === false
    || $template === false
    || $setup === false
    || $tracker === false
    || $launch_gate === false
    || $prepare === false
    || $checklist === false
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
    'npm run stripe:hosted-checkout:preflight',
    'npm run stripe:hosted-checkout:prepare',
    'npm run stripe:hosted-checkout:checklist',
    'npm run stripe:hosted-checkout:verify',
    'STRIPE_HOSTED_CHECKOUT_EVIDENCE_PATH',
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
$assert(str_contains($setup, 'stripe:hosted-checkout:prepare'), 'Stripe setup guide must mention the evidence prepare helper.');
$assert(str_contains($tracker, 'STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md'), 'Feature tracker must reference the hosted Checkout runbook for BILLING-002.');
$assert(str_contains($tracker, 'prepare-stripe-hosted-checkout-evidence.js'), 'Feature tracker must reference the hosted Checkout evidence prepare helper for BILLING-002.');
$assert(str_contains($tracker, 'stripe-hosted-checkout-checklist.js'), 'Feature tracker must reference the hosted Checkout checklist helper for BILLING-002.');
$assert(str_contains($tracker, 'verify-stripe-hosted-checkout-evidence.js'), 'Feature tracker must reference the hosted Checkout evidence verifier for BILLING-002.');
$assert(str_contains($tracker, 'needs_external_smoke'), 'BILLING-002 must remain marked external-smoke until hosted Checkout evidence exists.');
$assert(str_contains($launch_gate, 'STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md'), 'Launch gate acknowledgement must point to the runbook.');
$assert(str_contains($prepare, 'test-stripe-billing-flow.php'), 'Prepare helper must merge safe billing smoke output.');
$assert(str_contains($prepare, 'test-stripe-webhook-lifecycle.php'), 'Prepare helper must merge safe webhook lifecycle smoke output.');
$assert(str_contains($prepare, 'assertSafeSerializedEvidence'), 'Prepare helper must reject sensitive evidence output.');
$assert(str_contains($checklist, 'validateEvidence'), 'Checklist helper must wrap the real hosted Checkout evidence verifier.');
$assert(str_contains($checklist, 'Hosted Checkout'), 'Checklist helper must group hosted Checkout missing items.');
$assert(str_contains($package, '"stripe:hosted-checkout:prepare"'), 'package.json must expose the hosted Checkout evidence prepare helper.');
$assert(str_contains($package, '"stripe:hosted-checkout:preflight"'), 'package.json must expose the hosted Checkout preflight helper.');
$assert(str_contains($package, '"stripe:hosted-checkout:checklist"'), 'package.json must expose the hosted Checkout evidence checklist helper.');
$assert(str_contains($package, '"test:stripe-hosted-checkout-runbook"'), 'package.json must expose the hosted Checkout runbook contract.');
$assert(str_contains($package, '"stripe:hosted-checkout:verify"'), 'package.json must expose the hosted Checkout evidence verifier.');
$assert(str_contains($package, '"test:stripe-hosted-checkout-prepare"'), 'package.json must expose the hosted Checkout prepare helper test.');
$assert(str_contains($package, '"test:stripe-hosted-checkout-preflight"'), 'package.json must expose the hosted Checkout preflight helper test.');
$assert(str_contains($package, '"test:stripe-hosted-checkout-checklist"'), 'package.json must expose the hosted Checkout checklist helper test.');

echo "Stripe hosted Checkout runbook contract OK\n";
