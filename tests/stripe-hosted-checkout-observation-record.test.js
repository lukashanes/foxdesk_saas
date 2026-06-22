const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-stripe-observation-record-'));
const evidencePath = path.join(tmp, 'evidence.json');

const template = JSON.parse(fs.readFileSync(path.join(root, 'docs/stripe-hosted-checkout-evidence.template.json'), 'utf8'));
const evidence = {
  ...template,
  stripe_mode: 'live',
  approved_live_validation: true,
  tested_at: '2026-06-22T10:00:00.000Z',
  workspace: {
    tenant_reference: 'tenant_redacted',
    email_reference: 'redacted',
    temporary_workspace: true,
  },
  checkout: {
    ...template.checkout,
    session_id_redacted: 'cs_live_redacted',
  },
  webhooks: {
    checkout_session_completed_observed: true,
    invoice_paid_or_subscription_active_observed: true,
    invoice_payment_failed_observed: true,
    subscription_deleted_or_cancelled_observed: true,
    event_ids_redacted: ['evt_redacted_checkout', 'evt_redacted_invoice_paid'],
  },
  cleanup: {
    ...template.cleanup,
    stripe_customer_deleted_or_marked_test: true,
    temporary_workspace_cleaned_or_disabled: true,
  },
  safe_smoke_commands: {
    stripe_billing_flow_ok: true,
    stripe_webhook_lifecycle_ok: true,
  },
};

fs.writeFileSync(evidencePath, JSON.stringify(evidence, null, 2));

const recordSource = fs.readFileSync(path.join(root, 'bin/record-stripe-hosted-checkout-observations.js'), 'utf8');
assert(recordSource.includes('assertSafeSerializedEvidence'), 'Recorder must validate the final serialized evidence.');
assert(recordSource.includes('likely VAT ID'), 'Recorder must reject raw VAT IDs in notes/tax summaries.');
assert(recordSource.includes('full Checkout URL'), 'Recorder must reject full hosted Checkout URLs.');

const record = spawnSync('node', [
  'bin/record-stripe-hosted-checkout-observations.js',
  evidencePath,
  '--checkout-completed',
  '--redirected-back',
  '--billing-address',
  '--vat-id',
  '--reverse-charge',
  '--completed-at',
  '2026-06-22T12:30:00+02:00',
  '--tax-result',
  'EU B2B reverse charge observed in Stripe-hosted Checkout with redacted VAT ID.',
  '--portal-opened',
  '--portal-payment-method',
  '--portal-billing-address',
  '--portal-invoice-details',
  '--portal-vat-id',
  '--portal-cancellation',
  '--subscription-cleaned',
  '--notes',
  'Operator observed hosted Checkout and Portal controls; sensitive fields were redacted.',
  '--json',
], {
  cwd: root,
  encoding: 'utf8',
});

assert.strictEqual(record.status, 0, record.stderr || record.stdout);
const summary = JSON.parse(record.stdout);
assert.strictEqual(summary.ok, true);
assert(summary.changes.includes('checkout completed'));
assert(summary.changes.includes('temporary subscription cleaned'));

const verify = spawnSync('node', ['bin/verify-stripe-hosted-checkout-evidence.js', evidencePath, '--json'], {
  cwd: root,
  encoding: 'utf8',
});
assert.strictEqual(verify.status, 0, verify.stderr || verify.stdout);
assert.strictEqual(JSON.parse(verify.stdout).ok, true);

const unsafePath = path.join(tmp, 'unsafe.json');
fs.writeFileSync(unsafePath, JSON.stringify(evidence, null, 2));
const unsafe = spawnSync('node', [
  'bin/record-stripe-hosted-checkout-observations.js',
  unsafePath,
  '--notes',
  'Full URL https://checkout.stripe.com/c/pay/cs_live_secret and VAT CZ12345678',
  '--json',
], {
  cwd: root,
  encoding: 'utf8',
});

assert.notStrictEqual(unsafe.status, 0, 'Recorder must reject sensitive notes.');
const unsafeResult = JSON.parse(unsafe.stdout);
assert.strictEqual(unsafeResult.ok, false);
assert(unsafeResult.errors[0].includes('sensitive data'));

console.log('Stripe hosted Checkout observation recorder OK');
