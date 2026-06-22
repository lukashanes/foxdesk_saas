#!/usr/bin/env node
/**
 * Safe preflight for BILLING-002 hosted Checkout evidence.
 *
 * The default check only reports whether required environment knobs appear set;
 * it never prints secret values. Use --run-safe-smoke to run the existing
 * production-safe smoke scripts that read the real PHP config, create temporary
 * Stripe sessions, and clean them without completing payment.
 */

const { spawnSync } = require('child_process');
const path = require('path');

const root = path.resolve(__dirname, '..');
const args = process.argv.slice(2);

const requiredEnv = [
  {
    key: 'BILLING_ENABLED',
    label: 'Billing enabled',
    validate: (value) => /^(1|true|yes|on)$/i.test(String(value || '').trim()),
    message: 'Set BILLING_ENABLED=true for the hosted Checkout evidence environment.',
  },
  {
    key: 'STRIPE_SECRET_KEY',
    label: 'Stripe secret key',
    validate: (value) => /^sk_(test|live)_/.test(String(value || '').trim()),
    message: 'Set STRIPE_SECRET_KEY to a Stripe test/live secret key.',
  },
  {
    key: 'STRIPE_PRICE_CLOUD_BASE',
    label: 'Base subscription price',
    validate: (value) => /^price_/.test(String(value || '').trim()),
    message: 'Set STRIPE_PRICE_CLOUD_BASE to the FoxDesk Cloud recurring price id.',
  },
  {
    key: 'STRIPE_PRICE_STORAGE_OVERAGE',
    label: 'Storage overage price',
    validate: (value) => /^price_/.test(String(value || '').trim()),
    message: 'Set STRIPE_PRICE_STORAGE_OVERAGE to the metered storage overage price id.',
  },
  {
    key: 'STRIPE_WEBHOOK_SECRET',
    label: 'Stripe webhook signing secret',
    validate: (value) => /^whsec_/.test(String(value || '').trim()),
    message: 'Set STRIPE_WEBHOOK_SECRET before proving signed hosted Checkout webhooks.',
  },
  {
    key: 'APP_URL',
    label: 'Application URL',
    validate: (value) => /^https?:\/\//.test(String(value || '').trim()),
    message: 'Set APP_URL so Stripe success/cancel redirects point back to FoxDesk.',
  },
];

function usage() {
  console.error(`Usage: node bin/stripe-hosted-checkout-preflight.js [options]

Options:
  --json             Print JSON.
  --run-safe-smoke   Run production-safe billing and webhook smoke checks.
  --help             Show this help.
`);
}

function checkEnv(env = process.env) {
  return requiredEnv.map((item) => {
    const value = env[item.key];
    const present = typeof value === 'string' && value.trim() !== '';
    const valid = present && item.validate(value);
    return {
      key: item.key,
      label: item.label,
      status: valid ? 'ok' : (present ? 'invalid' : 'missing'),
      message: valid ? '' : item.message,
    };
  });
}

function runJsonCommand(script) {
  const result = spawnSync('./bin/run-php.sh', [script, '--json'], {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  let parsed = null;
  if (result.stdout) {
    try {
      parsed = JSON.parse(result.stdout);
    } catch (_) {
      parsed = null;
    }
  }

  const errors = [];
  if (parsed && Array.isArray(parsed.errors)) {
    errors.push(...parsed.errors);
  }
  if (result.error) errors.push(result.error.message);
  if (result.stderr && result.stderr.trim()) errors.push(result.stderr.trim());
  if (!parsed && result.stdout && result.stdout.trim()) errors.push(result.stdout.trim());

  return {
    ok: result.status === 0 && parsed && parsed.ok === true,
    exit_status: result.status,
    status: parsed && parsed.status ? parsed.status : path.basename(script),
    errors,
    warnings: parsed && Array.isArray(parsed.warnings) ? parsed.warnings : [],
  };
}

function runSafeSmoke() {
  return {
    billing_flow: runJsonCommand('bin/test-stripe-billing-flow.php'),
    webhook_lifecycle: runJsonCommand('bin/test-stripe-webhook-lifecycle.php'),
  };
}

function main() {
  if (args.includes('--help') || args.includes('-h')) {
    usage();
    process.exit(0);
  }

  const json = args.includes('--json');
  const runSmoke = args.includes('--run-safe-smoke');
  const env_checks = checkEnv();
  const env_ok = env_checks.every((check) => check.status === 'ok');
  const result = {
    ok: env_ok,
    env_ok,
    env_checks,
    smoke: null,
    next_step: env_ok
      ? 'Run npm run stripe:hosted-checkout:prepare -- --run-smoke --mode test --out tmp/stripe-hosted-checkout-evidence.json'
      : 'Set the missing/invalid values in the Stripe evidence environment, or run with --run-safe-smoke where config.php is already populated.',
  };

  if (runSmoke) {
    result.smoke = runSafeSmoke();
    const smoke_ok = result.smoke.billing_flow.ok && result.smoke.webhook_lifecycle.ok;
    result.ok = result.ok && smoke_ok;
    result.next_step = smoke_ok
      ? 'Complete hosted Checkout and Customer Portal observations, then verify the evidence JSON.'
      : 'Fix the reported smoke configuration/runtime errors, then rerun this preflight.';
  }

  if (json) {
    console.log(JSON.stringify(result, null, 2));
  } else {
    console.log(`Stripe hosted Checkout preflight: ${result.ok ? 'ready' : 'not ready'}`);
    for (const check of env_checks) {
      const marker = check.status === 'ok' ? '[ok]' : `[${check.status}]`;
      console.log(`${marker} ${check.key} - ${check.label}${check.message ? `: ${check.message}` : ''}`);
    }
    if (result.smoke) {
      for (const [name, smoke] of Object.entries(result.smoke)) {
        console.log(`${smoke.ok ? '[ok]' : '[failed]'} ${name} - ${smoke.status}`);
        for (const error of smoke.errors) console.log(`  - ${error}`);
      }
    }
    console.log(`Next: ${result.next_step}`);
  }

  process.exit(result.ok ? 0 : 1);
}

main();
