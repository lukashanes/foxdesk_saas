#!/usr/bin/env node
/**
 * Prepare a redacted evidence file for BILLING-002.
 *
 * This does not complete hosted Checkout. It creates the machine-checkable JSON
 * shell and can attach the safe smoke outputs so the operator only fills the
 * hosted Stripe UI observations that cannot be proven by API-only smoke.
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const args = process.argv.slice(2);

function usage() {
  console.error(`Usage: node bin/prepare-stripe-hosted-checkout-evidence.js [options]

Options:
  --out <file>                 Output evidence JSON path.
  --mode <test|live>           Stripe mode for the hosted run. Default: test.
  --approved-live              Mark live mode as intentionally approved.
  --operator <name>            Operator name. Default: Aenze s.r.o.
  --run-smoke                  Run safe Stripe smoke commands and merge results.
  --smoke-runner <local|compose-prod>
                               Where --run-smoke executes. Default: local.
  --billing-smoke-json <file>  Merge a saved bin/test-stripe-billing-flow.php --json output.
  --webhook-smoke-json <file>  Merge a saved bin/test-stripe-webhook-lifecycle.php --json output.
  --json                       Print a machine-readable summary.
`);
}

function argValue(name, fallback = '') {
  const prefix = `${name}=`;
  const inline = args.find((arg) => arg.startsWith(prefix));
  if (inline) return inline.slice(prefix.length);
  const index = args.indexOf(name);
  if (index >= 0 && args[index + 1] && !args[index + 1].startsWith('--')) return args[index + 1];
  return fallback;
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(path.resolve(root, file), 'utf8'));
}

function runJsonCommand(command, commandArgs) {
  const result = spawnSync(command, commandArgs, {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  if (result.status !== 0) {
    const details = [];
    if (result.error) details.push(result.error.message);
    if (result.stderr) details.push(result.stderr.trim());
    if (result.stdout) {
      try {
        const parsed = JSON.parse(result.stdout);
        if (Array.isArray(parsed.errors) && parsed.errors.length > 0) {
          details.push(parsed.errors.join('; '));
        } else {
          details.push(result.stdout.trim());
        }
      } catch (_) {
        details.push(result.stdout.trim());
      }
    }
    throw new Error(`${command} ${commandArgs.join(' ')} failed: ${details.filter(Boolean).join(' ') || `exit ${result.status}`}`);
  }
  return JSON.parse(result.stdout);
}

function runPhpJson(script, scriptArgs = []) {
  return runJsonCommand('./bin/run-php.sh', [script, ...scriptArgs]);
}

const composeSmokeEnv = [
  'BILLING_ENABLED',
  'STRIPE_SECRET_KEY',
  'STRIPE_PRICE_CLOUD_BASE',
  'STRIPE_PRICE_STORAGE_OVERAGE',
  'STRIPE_WEBHOOK_SECRET',
  'APP_URL',
  'STRIPE_TAX_ENABLED',
  'STRIPE_TAX_ID_COLLECTION_ENABLED',
  'STRIPE_TAX_ID_COLLECTION_REQUIRED',
  'STRIPE_SUCCESS_URL',
  'STRIPE_CANCEL_URL',
];

function runComposePhpJson(script, scriptArgs = []) {
  const envArgs = composeSmokeEnv.flatMap((name) => ['-e', name]);
  return runJsonCommand('docker', [
    'compose',
    '-f',
    'docker-compose.prod.yml',
    'exec',
    '-T',
    ...envArgs,
    'app',
    'php',
    script,
    ...scriptArgs,
  ]);
}

function runSmokeJson(script, scriptArgs = []) {
  const runner = argValue('--smoke-runner', 'local');
  if (runner === 'local') return runPhpJson(script, scriptArgs);
  if (runner === 'compose-prod') return runComposePhpJson(script, scriptArgs);
  throw new Error('--smoke-runner must be local or compose-prod.');
}

function redactStripeId(value, fallback) {
  const id = String(value || '').trim();
  if (!id) return fallback;
  if (id.startsWith('cs_test_')) return 'cs_test_redacted';
  if (id.startsWith('cs_live_')) return 'cs_live_redacted';
  if (id.startsWith('cus_')) return 'cus_redacted';
  if (id.startsWith('sub_')) return 'sub_redacted';
  if (id.startsWith('evt_')) return 'evt_redacted';
  return fallback;
}

function safeEventRefs(webhookSmoke) {
  const events = webhookSmoke && typeof webhookSmoke.events === 'object' ? Object.keys(webhookSmoke.events) : [];
  return events.map((name) => `evt_redacted_${name.replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '').toLowerCase()}`);
}

function assertSafeSerializedEvidence(source) {
  const forbidden = [
    ['Stripe secret key', /sk_(live|test)_[A-Za-z0-9]+/],
    ['full Checkout URL', /https:\/\/checkout\.stripe\.com\/[^\s"']+/],
    ['full Portal URL', /https:\/\/billing\.stripe\.com\/[^\s"']+/],
    ['test card number', /4242[\s-]?4242[\s-]?4242[\s-]?4242/],
    ['likely email address', /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i],
  ];
  const failures = forbidden.filter(([, pattern]) => pattern.test(source)).map(([label]) => label);
  if (failures.length > 0) {
    throw new Error(`Prepared evidence would contain sensitive data: ${failures.join(', ')}`);
  }
}

function mergeSmoke(evidence, billingSmoke, webhookSmoke) {
  if (billingSmoke) {
    evidence.stripe_mode = ['test', 'live'].includes(String(billingSmoke.key_mode || ''))
      ? String(billingSmoke.key_mode)
      : evidence.stripe_mode;
    evidence.workspace.tenant_reference = billingSmoke.tenant_id ? `tenant_${billingSmoke.tenant_id}_redacted` : evidence.workspace.tenant_reference;
    evidence.checkout.host = billingSmoke.checkout_host || evidence.checkout.host;
    evidence.checkout.session_id_redacted = redactStripeId(billingSmoke.checkout_session_id, evidence.checkout.session_id_redacted);
    evidence.customer_portal.host = billingSmoke.portal_host || evidence.customer_portal.host;
    evidence.safe_smoke_commands.stripe_billing_flow_ok = billingSmoke.ok === true;
    evidence.cleanup.stripe_customer_deleted_or_marked_test = Boolean(billingSmoke.cleanup && billingSmoke.cleanup.stripe_customer_deleted);
    evidence.cleanup.temporary_workspace_cleaned_or_disabled = Boolean(billingSmoke.cleanup && billingSmoke.cleanup.db_cleaned);
  }

  if (webhookSmoke) {
    evidence.safe_smoke_commands.stripe_webhook_lifecycle_ok = webhookSmoke.ok === true;
    evidence.webhooks.checkout_session_completed_observed = Boolean(webhookSmoke.checks && webhookSmoke.checks.checkout_completed_handled);
    evidence.webhooks.invoice_paid_or_subscription_active_observed = Boolean(webhookSmoke.checks && webhookSmoke.checks.paid_invoice_reactivates);
    evidence.webhooks.invoice_payment_failed_observed = Boolean(webhookSmoke.checks && webhookSmoke.checks.failed_payment_marks_past_due);
    evidence.webhooks.subscription_deleted_or_cancelled_observed = Boolean(webhookSmoke.checks && webhookSmoke.checks.subscription_deleted_cancels);
    evidence.webhooks.event_ids_redacted = safeEventRefs(webhookSmoke);
  }

  evidence.notes = [
    'Prepared evidence file. Complete the hosted Stripe UI fields after running the manual Checkout and Customer Portal steps.',
    'Do not paste full Checkout URLs, Portal URLs, card numbers, VAT IDs, email addresses, customer IDs, subscription IDs, or secret keys.',
  ].join(' ');
}

function main() {
  if (args.includes('--help') || args.includes('-h')) {
    usage();
    process.exit(0);
  }

  const json = args.includes('--json');
  const mode = argValue('--mode', 'test');
  if (!['test', 'live'].includes(mode)) {
    throw new Error('--mode must be test or live.');
  }

  const output = path.resolve(
    root,
    argValue('--out', `tmp/stripe-hosted-checkout-evidence-${new Date().toISOString().replace(/[:.]/g, '-')}.json`)
  );
  const template = readJson('docs/stripe-hosted-checkout-evidence.template.json');
  const evidence = {
    ...template,
    stripe_mode: mode,
    approved_live_validation: mode === 'live' && args.includes('--approved-live'),
    operator: argValue('--operator', 'Aenze s.r.o.'),
    tested_at: new Date().toISOString(),
    workspace: {
      ...template.workspace,
      tenant_reference: 'tenant_redacted_after_safe_smoke_or_manual_run',
      email_reference: 'redacted',
      temporary_workspace: true,
    },
  };

  let billingSmoke = null;
  let webhookSmoke = null;
  if (args.includes('--run-smoke')) {
    billingSmoke = runSmokeJson('bin/test-stripe-billing-flow.php', ['--json']);
    webhookSmoke = runSmokeJson('bin/test-stripe-webhook-lifecycle.php', ['--json']);
  }
  const billingSmokeFile = argValue('--billing-smoke-json');
  const webhookSmokeFile = argValue('--webhook-smoke-json');
  if (billingSmokeFile) billingSmoke = readJson(billingSmokeFile);
  if (webhookSmokeFile) webhookSmoke = readJson(webhookSmokeFile);

  mergeSmoke(evidence, billingSmoke, webhookSmoke);

  const serialized = JSON.stringify(evidence, null, 2);
  assertSafeSerializedEvidence(serialized);
  fs.mkdirSync(path.dirname(output), { recursive: true });
  fs.writeFileSync(output, `${serialized}\n`);

  const summary = {
    ok: true,
    file: output,
    stripe_mode: evidence.stripe_mode,
    billing_smoke_merged: Boolean(billingSmoke),
    webhook_smoke_merged: Boolean(webhookSmoke),
    next_step: 'Complete hosted Checkout and Customer Portal observations, then run npm run stripe:hosted-checkout:verify -- <file>.',
  };
  if (json) {
    console.log(JSON.stringify(summary, null, 2));
  } else {
    console.log(`Prepared Stripe hosted Checkout evidence: ${output}`);
    console.log(summary.next_step);
  }
}

try {
  main();
} catch (error) {
  console.error(`Stripe hosted Checkout evidence prepare FAILED: ${error.message}`);
  process.exit(1);
}
