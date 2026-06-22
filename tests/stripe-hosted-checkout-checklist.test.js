const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-stripe-checklist-'));
const template = JSON.parse(fs.readFileSync(path.join(root, 'docs/stripe-hosted-checkout-evidence.template.json'), 'utf8'));

function writeEvidence(name, overrides = {}) {
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
      host: 'checkout.stripe.com',
      ...(overrides.checkout || {}),
    },
    webhooks: {
      ...template.webhooks,
      event_ids_redacted: ['evt_redacted_checkout'],
      ...(overrides.webhooks || {}),
    },
    customer_portal: {
      ...template.customer_portal,
      host: 'billing.stripe.com',
      ...(overrides.customer_portal || {}),
    },
    cleanup: {
      ...template.cleanup,
      ...(overrides.cleanup || {}),
    },
    safe_smoke_commands: {
      ...template.safe_smoke_commands,
      stripe_billing_flow_ok: true,
      stripe_webhook_lifecycle_ok: true,
      ...(overrides.safe_smoke_commands || {}),
    },
  };
  const file = path.join(tmp, name);
  fs.writeFileSync(file, `${JSON.stringify(evidence, null, 2)}\n`);
  return file;
}

function run(file, args = []) {
  return spawnSync('node', ['bin/stripe-hosted-checkout-checklist.js', file, ...args], {
    cwd: root,
    encoding: 'utf8',
  });
}

const incompleteFile = writeEvidence('incomplete.json', {
  stripe_mode: 'live',
  approved_live_validation: true,
  webhooks: {
    checkout_session_completed_observed: true,
    invoice_paid_or_subscription_active_observed: true,
    invoice_payment_failed_observed: true,
    subscription_deleted_or_cancelled_observed: true,
  },
  cleanup: {
    stripe_customer_deleted_or_marked_test: true,
    temporary_workspace_cleaned_or_disabled: true,
  },
});

const incomplete = run(incompleteFile, ['--json']);
assert.strictEqual(incomplete.status, 1, 'Incomplete checklist must exit non-zero.');
const incompleteResult = JSON.parse(incomplete.stdout);
assert.strictEqual(incompleteResult.ok, false);
assert(incompleteResult.groups['Hosted Checkout'].some((item) => item.check === 'Checkout completed'));
assert(incompleteResult.groups['Customer Portal'].some((item) => item.check === 'Customer Portal opened'));
assert(incompleteResult.groups.Cleanup.some((item) => item.check === 'Temporary subscription cleaned'));

const text = run(incompleteFile);
assert.notStrictEqual(text.status, 0);
assert(text.stdout.includes('Hosted Checkout:'));
assert(text.stdout.includes('- [ ] Checkout completed:'));

const completeFile = writeEvidence('complete.json', {
  stripe_mode: 'test',
  checkout: {
    completed: true,
    completed_at: '2026-06-22T12:00:00Z',
    redirected_back_to_foxdesk: true,
    billing_address_collected: true,
    vat_id_collected: true,
    reverse_charge_or_zero_rate_observed: true,
    tax_result_observed: 'EU B2B reverse charge observed in Stripe-hosted Checkout.',
  },
  webhooks: {
    checkout_session_completed_observed: true,
    invoice_paid_or_subscription_active_observed: true,
    invoice_payment_failed_observed: true,
    subscription_deleted_or_cancelled_observed: true,
  },
  customer_portal: {
    opened: true,
    payment_method_update_observed: true,
    billing_address_update_observed: true,
    invoice_details_observed: true,
    vat_id_update_observed: true,
    cancellation_observed: true,
  },
  cleanup: {
    subscription_cancelled: true,
    stripe_customer_deleted_or_marked_test: true,
    temporary_workspace_cleaned_or_disabled: true,
  },
});

const complete = run(completeFile, ['--json']);
assert.strictEqual(complete.status, 0, complete.stderr || complete.stdout);
const completeResult = JSON.parse(complete.stdout);
assert.strictEqual(completeResult.ok, true);
assert.strictEqual(completeResult.missing_count, 0);

console.log('Stripe hosted Checkout checklist OK');
