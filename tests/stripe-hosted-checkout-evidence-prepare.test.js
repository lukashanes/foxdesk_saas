const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-stripe-evidence-prepare-'));
const prepareScript = fs.readFileSync(path.join(root, 'bin/prepare-stripe-hosted-checkout-evidence.js'), 'utf8');

assert(prepareScript.includes("runJsonCommand('./bin/run-php.sh'"), 'Prepare helper must use the project PHP runner, not a system php binary.');
assert(prepareScript.includes('--smoke-runner <local|compose-prod>'), 'Prepare helper must document the app container smoke runner.');
assert(prepareScript.includes('runComposePhpJson'), 'Prepare helper must support running safe smoke inside the app container.');
assert(prepareScript.includes('docker-compose.prod.yml'), 'Prepare helper must target the production compose app container for compose smoke.');
assert(prepareScript.includes('STRIPE_SECRET_KEY'), 'Prepare helper compose smoke must pass Stripe env names without printing values.');
assert(prepareScript.includes('result.error'), 'Prepare helper must surface spawn errors.');
assert(prepareScript.includes('parsed.errors.join'), 'Prepare helper must surface smoke JSON errors.');

const billingSmokePath = path.join(tmp, 'billing-smoke.json');
const webhookSmokePath = path.join(tmp, 'webhook-smoke.json');
const outputPath = path.join(tmp, 'prepared-evidence.json');

fs.writeFileSync(billingSmokePath, JSON.stringify({
  ok: true,
  key_mode: 'test',
  tenant_id: 123,
  checkout_session_id: 'cs_test_this_full_value_must_not_survive',
  checkout_host: 'checkout.stripe.com',
  portal_host: 'billing.stripe.com',
  stripe_customer_id: 'cus_this_full_value_must_not_survive',
  cleanup: {
    checkout_session_expired: true,
    stripe_customer_deleted: true,
    db_cleaned: true,
  },
}, null, 2));

fs.writeFileSync(webhookSmokePath, JSON.stringify({
  ok: true,
  checks: {
    checkout_completed_handled: true,
    paid_invoice_reactivates: true,
    failed_payment_marks_past_due: true,
    subscription_deleted_cancels: true,
  },
  events: {
    'checkout.session.completed': { handled: true },
    'invoice.payment_failed': { handled: true },
    'invoice.paid': { handled: true },
    'customer.subscription.deleted': { handled: true },
  },
}, null, 2));

const prepare = spawnSync('node', [
  'bin/prepare-stripe-hosted-checkout-evidence.js',
  '--mode',
  'test',
  '--billing-smoke-json',
  billingSmokePath,
  '--webhook-smoke-json',
  webhookSmokePath,
  '--out',
  outputPath,
  '--json',
], {
  cwd: root,
  encoding: 'utf8',
});

assert.strictEqual(prepare.status, 0, prepare.stderr || prepare.stdout);
const summary = JSON.parse(prepare.stdout);
assert.strictEqual(summary.ok, true);
assert.strictEqual(summary.billing_smoke_merged, true);
assert.strictEqual(summary.webhook_smoke_merged, true);

const source = fs.readFileSync(outputPath, 'utf8');
assert(!source.includes('cs_test_this_full_value_must_not_survive'), 'Prepared evidence must redact full Checkout Session ids.');
assert(!source.includes('cus_this_full_value_must_not_survive'), 'Prepared evidence must redact full customer ids.');
assert(!/https:\/\/checkout\.stripe\.com\//.test(source), 'Prepared evidence must not contain hosted Checkout URLs.');
assert(!/4242[\s-]?4242[\s-]?4242[\s-]?4242/.test(source), 'Prepared evidence must not contain card numbers.');

const evidence = JSON.parse(source);
assert.strictEqual(evidence.stripe_mode, 'test');
assert.strictEqual(evidence.checkout.host, 'checkout.stripe.com');
assert.strictEqual(evidence.checkout.session_id_redacted, 'cs_test_redacted');
assert.strictEqual(evidence.customer_portal.host, 'billing.stripe.com');
assert.strictEqual(evidence.safe_smoke_commands.stripe_billing_flow_ok, true);
assert.strictEqual(evidence.safe_smoke_commands.stripe_webhook_lifecycle_ok, true);
assert.strictEqual(evidence.webhooks.checkout_session_completed_observed, true);
assert.strictEqual(evidence.webhooks.invoice_paid_or_subscription_active_observed, true);
assert.strictEqual(evidence.webhooks.invoice_payment_failed_observed, true);
assert.strictEqual(evidence.webhooks.subscription_deleted_or_cancelled_observed, true);
assert(evidence.webhooks.event_ids_redacted.length >= 4);
assert.strictEqual(evidence.checkout.completed, false, 'Prepare helper must not pretend hosted Checkout was completed.');
assert.strictEqual(evidence.customer_portal.opened, false, 'Prepare helper must not pretend Portal UI was checked.');

const finalVerify = spawnSync('node', ['bin/verify-stripe-hosted-checkout-evidence.js', outputPath, '--json'], {
  cwd: root,
  encoding: 'utf8',
});
assert.notStrictEqual(finalVerify.status, 0, 'Prepared skeleton must fail final verification until manual hosted observations are filled.');
const finalResult = JSON.parse(finalVerify.stdout);
assert(finalResult.failures.some((failure) => failure.includes('Checkout completed')));
assert(finalResult.failures.some((failure) => failure.includes('Customer Portal opened')));

console.log('Stripe hosted Checkout evidence prepare OK');
