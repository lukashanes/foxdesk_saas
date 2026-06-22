const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');

function writeStripeEvidence() {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-launch-stripe-evidence-'));
  const template = JSON.parse(fs.readFileSync(path.join(root, 'docs/stripe-hosted-checkout-evidence.template.json'), 'utf8'));
  const evidence = {
    ...template,
    workspace: {
      ...template.workspace,
      tenant_reference: 'tenant_redacted',
      temporary_workspace: true,
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
    },
    webhooks: {
      ...template.webhooks,
      checkout_session_completed_observed: true,
      invoice_paid_or_subscription_active_observed: true,
      invoice_payment_failed_observed: true,
      subscription_deleted_or_cancelled_observed: true,
      event_ids_redacted: ['evt_redacted_checkout', 'evt_redacted_invoice_paid'],
    },
    customer_portal: {
      ...template.customer_portal,
      opened: true,
      payment_method_update_observed: true,
      billing_address_update_observed: true,
      invoice_details_observed: true,
      vat_id_update_observed: true,
      cancellation_observed: true,
    },
    cleanup: {
      ...template.cleanup,
      subscription_cancelled: true,
      stripe_customer_deleted_or_marked_test: true,
      temporary_workspace_cleaned_or_disabled: true,
    },
    safe_smoke_commands: {
      ...template.safe_smoke_commands,
      stripe_billing_flow_ok: true,
      stripe_webhook_lifecycle_ok: true,
    },
  };
  const file = path.join(tmp, 'stripe-hosted-checkout-evidence.json');
  fs.writeFileSync(file, JSON.stringify(evidence, null, 2));
  return file;
}

const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
assert(pkg.scripts['launch:go-no-go'], 'package.json must expose launch:go-no-go.');
assert(pkg.scripts['prod:deploy:evidence'], 'package.json must expose prod:deploy:evidence.');

const script = fs.readFileSync(path.join(root, 'bin/launch-go-no-go.js'), 'utf8');
assert(script.includes('FOXDESK_ACK_LEGAL_APPROVED'), 'Launch gate must require legal acknowledgement for paid public beta.');
assert(script.includes('FOXDESK_ACK_STRIPE_LIVE_TESTED'), 'Launch gate must require Stripe live-flow acknowledgement.');
assert(script.includes('STRIPE_HOSTED_CHECKOUT_EVIDENCE_PATH'), 'Launch gate must require Stripe hosted Checkout evidence path.');
assert(script.includes('validateEvidence'), 'Launch gate must verify Stripe hosted Checkout evidence.');
assert(script.includes('FOXDESK_ACK_INBOUND_EMAIL_TESTED'), 'Launch gate must require inbound email acknowledgement.');
assert(script.includes('FOXDESK_ACK_RESTORE_MONITORING_READY'), 'Launch gate must require restore/monitoring acknowledgement.');
assert(script.includes('prod:deploy:evidence'), 'Launch gate must require deployment evidence script.');

const doc = fs.readFileSync(path.join(root, 'docs/PUBLIC_BETA_GO_NO_GO.md'), 'utf8');
for (const required of [
  'Private Beta GO',
  'Paid Public Beta GO',
  'foxdesk.net',
  'app.foxdesk.net',
  'platform.foxdesk.net',
  'Aenze s.r.o.',
  'foxdesk-email-archive',
  'prod:deploy:evidence',
]) {
  assert(doc.includes(required), `Go/no-go doc must include ${required}.`);
}

const run = spawnSync('node', ['bin/launch-go-no-go.js', '--json'], {
  cwd: root,
  encoding: 'utf8',
});
assert.strictEqual(run.status, 0, run.stderr || run.stdout);
const result = JSON.parse(run.stdout);
assert.strictEqual(result.status, 'ready_with_warnings');
assert(result.warnings >= 1, 'Default private beta launch gate should expose paid-launch warnings.');
assert.strictEqual(result.blocked, 0, 'Default private beta launch gate should not be blocked when code checks pass.');
assert(result.checks.some((check) => check.name === 'Public footer links legal documents'), 'Legal footer check is missing.');
assert(result.checks.some((check) => check.name === 'Production smoke covers public legal and app health'), 'Production smoke coverage check is missing.');
assert(result.checks.some((check) => check.name === 'Deployment evidence gate is documented'), 'Deployment evidence gate check is missing.');

const strictRun = spawnSync('node', ['bin/launch-go-no-go.js', '--json', '--strict-paid'], {
  cwd: root,
  encoding: 'utf8',
});
assert.strictEqual(strictRun.status, 1, 'Strict paid gate must fail until manual acknowledgements are provided.');
const strictResult = JSON.parse(strictRun.stdout);
assert.strictEqual(strictResult.status, 'blocked');
assert(strictResult.blocked >= 1, 'Strict paid gate should report blocked manual launch checks.');

const acknowledged = spawnSync('node', ['bin/launch-go-no-go.js', '--json', '--strict-paid'], {
  cwd: root,
  encoding: 'utf8',
  env: {
    ...process.env,
    FOXDESK_ACK_LEGAL_APPROVED: 'true',
    FOXDESK_ACK_STRIPE_LIVE_TESTED: 'true',
    FOXDESK_ACK_INBOUND_EMAIL_TESTED: 'true',
    FOXDESK_ACK_RESTORE_MONITORING_READY: 'true',
  },
});
assert.strictEqual(acknowledged.status, 1, 'Stripe acknowledgement must be blocked without verified evidence.');
const missingEvidenceResult = JSON.parse(acknowledged.stdout);
assert.strictEqual(missingEvidenceResult.status, 'blocked');
assert(missingEvidenceResult.checks.some((check) => check.name === 'Stripe live billing flow acknowledged' && check.status === 'blocked'));

const evidencePath = writeStripeEvidence();
const acknowledgedWithEvidence = spawnSync('node', ['bin/launch-go-no-go.js', '--json', '--strict-paid'], {
  cwd: root,
  encoding: 'utf8',
  env: {
    ...process.env,
    FOXDESK_ACK_LEGAL_APPROVED: 'true',
    FOXDESK_ACK_STRIPE_LIVE_TESTED: 'true',
    STRIPE_HOSTED_CHECKOUT_EVIDENCE_PATH: evidencePath,
    FOXDESK_ACK_INBOUND_EMAIL_TESTED: 'true',
    FOXDESK_ACK_RESTORE_MONITORING_READY: 'true',
  },
});
assert.strictEqual(acknowledgedWithEvidence.status, 0, acknowledgedWithEvidence.stderr || acknowledgedWithEvidence.stdout);
const acknowledgedWithEvidenceResult = JSON.parse(acknowledgedWithEvidence.stdout);
assert.strictEqual(acknowledgedWithEvidenceResult.status, 'pass');

console.log('Launch go/no-go contract OK');
