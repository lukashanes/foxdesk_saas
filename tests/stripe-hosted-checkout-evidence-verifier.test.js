const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-stripe-evidence-'));

function writeEvidence(name, overrides = {}, mutate = null) {
  const template = JSON.parse(fs.readFileSync(path.join(root, 'docs/stripe-hosted-checkout-evidence.template.json'), 'utf8'));
  const evidence = {
    ...template,
    ...overrides,
    workspace: {
      ...template.workspace,
      tenant_reference: 'tenant_redacted',
      temporary_workspace: true,
      ...(overrides.workspace || {}),
    },
    checkout: {
      ...template.checkout,
      completed: true,
      completed_at: '2026-06-22T12:00:00Z',
      redirected_back_to_foxdesk: true,
      billing_address_collected: true,
      vat_id_collected: true,
      tax_result_observed: 'EU B2B reverse charge observed in Stripe-hosted Checkout.',
      reverse_charge_or_zero_rate_observed: true,
      ...(overrides.checkout || {}),
    },
    webhooks: {
      ...template.webhooks,
      checkout_session_completed_observed: true,
      invoice_paid_or_subscription_active_observed: true,
      invoice_payment_failed_observed: true,
      subscription_deleted_or_cancelled_observed: true,
      event_ids_redacted: ['evt_redacted_checkout', 'evt_redacted_invoice_paid'],
      ...(overrides.webhooks || {}),
    },
    customer_portal: {
      ...template.customer_portal,
      opened: true,
      payment_method_update_observed: true,
      billing_address_update_observed: true,
      invoice_details_observed: true,
      vat_id_update_observed: true,
      cancellation_observed: true,
      ...(overrides.customer_portal || {}),
    },
    cleanup: {
      ...template.cleanup,
      subscription_cancelled: true,
      stripe_customer_deleted_or_marked_test: true,
      temporary_workspace_cleaned_or_disabled: true,
      ...(overrides.cleanup || {}),
    },
    safe_smoke_commands: {
      ...template.safe_smoke_commands,
      stripe_billing_flow_ok: true,
      stripe_webhook_lifecycle_ok: true,
      ...(overrides.safe_smoke_commands || {}),
    },
  };

  if (mutate) {
    mutate(evidence);
  }

  const file = path.join(tmp, name);
  fs.writeFileSync(file, JSON.stringify(evidence, null, 2));
  return file;
}

function run(file) {
  return spawnSync('node', ['bin/verify-stripe-hosted-checkout-evidence.js', file, '--json'], {
    cwd: root,
    encoding: 'utf8',
  });
}

const pass = run(writeEvidence('pass.json'));
assert.strictEqual(pass.status, 0, pass.stderr || pass.stdout);
const passResult = JSON.parse(pass.stdout);
assert.strictEqual(passResult.ok, true);
assert(passResult.checks.some((check) => check.name === 'Checkout completed'), 'Verifier must check Checkout completion.');
assert(passResult.checks.some((check) => check.name === 'No full Checkout URL'), 'Verifier must reject unredacted Checkout URLs.');

const incomplete = run(writeEvidence('incomplete.json', {
  checkout: {
    completed: false,
  },
}));
assert.notStrictEqual(incomplete.status, 0, 'Incomplete evidence must fail.');
assert(JSON.parse(incomplete.stdout).failures.some((failure) => failure.includes('Checkout completed')));

const sensitive = run(writeEvidence('sensitive.json', {}, (evidence) => {
  evidence.notes = 'Do not store https://checkout.stripe.com/c/pay/cs_test_secret or 4242 4242 4242 4242 here.';
}));
assert.notStrictEqual(sensitive.status, 0, 'Sensitive evidence must fail.');
const sensitiveFailures = JSON.parse(sensitive.stdout).failures.join('\n');
assert(sensitiveFailures.includes('full Checkout URL'), 'Verifier must catch hosted Checkout URLs.');
assert(sensitiveFailures.includes('test card number'), 'Verifier must catch card numbers.');

const liveUnapproved = run(writeEvidence('live-unapproved.json', {
  stripe_mode: 'live',
  approved_live_validation: false,
}));
assert.notStrictEqual(liveUnapproved.status, 0, 'Live evidence must require explicit approval.');
assert(JSON.parse(liveUnapproved.stdout).failures.some((failure) => failure.includes('Live mode is explicitly approved')));

console.log('Stripe hosted Checkout evidence verifier OK');
