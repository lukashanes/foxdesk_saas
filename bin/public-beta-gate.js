#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function exists(rel) {
  return fs.existsSync(path.join(root, rel));
}

function has(content, needle) {
  return content.includes(needle);
}

function check(name, ok, status, detail) {
  return {
    name,
    status: ok ? 'pass' : status,
    detail,
  };
}

function buildChecks() {
  const packageJson = JSON.parse(read('package.json'));
  const scripts = packageJson.scripts || {};
  const config = read('config.production.example.php');
  const billing = read('includes/billing-functions.php');
  const stripeDoc = read('docs/STRIPE_PUBLIC_BETA_SETUP.md');
  const prodEnvDoc = read('docs/PRODUCTION_ENV_VALUES.md');
  const envExample = read('.env.production.example');
  const launchReadinessDoc = exists('docs/LAUNCH_READINESS.md') ? read('docs/LAUNCH_READINESS.md') : '';
  const goNoGoDoc = exists('docs/PUBLIC_BETA_GO_NO_GO.md') ? read('docs/PUBLIC_BETA_GO_NO_GO.md') : '';
  const technicalDebtDoc = exists('docs/TECHNICAL_DEBT_PLAN.md') ? read('docs/TECHNICAL_DEBT_PLAN.md') : '';

  const requiredScripts = [
    'lint:php',
    'e2e',
    'local:smoke',
    'prod:smoke',
    'prod:deploy:evidence',
    'launch:go-no-go',
    'test:csp-ui',
    'cutover:preflight',
    'cutover:postcheck',
  ];

  const requiredEnv = [
    'BILLING_ENABLED',
    'STRIPE_SECRET_KEY',
    'STRIPE_WEBHOOK_SECRET',
    'STRIPE_PRICE_CLOUD_BASE',
    'STRIPE_PRICE_STORAGE_OVERAGE',
    'STRIPE_STORAGE_METER_EVENT_NAME',
    'STRIPE_TAX_ENABLED',
    'STRIPE_TAX_ID_COLLECTION_ENABLED',
    'STRIPE_TAX_ID_COLLECTION_REQUIRED',
    'BILLING_TRIAL_DAYS',
    'R2_BUCKET',
    'R2_ENDPOINT',
    'R2_ACCESS_KEY_ID',
    'R2_SECRET_ACCESS_KEY',
    'CLOUDFLARE_EMAIL_API_TOKEN',
  ];
  const requiredDeployEnv = [
    'PROD_BASE_URL',
    'PROD_PUBLIC_URL',
    'FOXDESK_BACKUP_DIR',
    'FOXDESK_RESTORE_EVIDENCE_PATH',
    'FOXDESK_DEPLOY_EVIDENCE_DIR',
    'FOXDESK_MONITORING_HEALTH_URL',
    'FOXDESK_MONITORING_ALERT_EMAIL',
  ];

  return [
    ...requiredScripts.map((script) => check(
      `npm script: ${script}`,
      typeof scripts[script] === 'string' && scripts[script].trim() !== '',
      'blocked',
      scripts[script] || 'missing'
    )),
    ...requiredEnv.map((name) => check(
      `production env: ${name}`,
      has(config, name) && has(prodEnvDoc, name),
      'blocked',
      has(config, name) ? 'present in config template' : 'missing from config template'
    )),
    ...requiredDeployEnv.map((name) => check(
      `deployment env: ${name}`,
      has(envExample, name) && has(prodEnvDoc, name),
      'blocked',
      has(envExample, name) ? 'present in env template' : 'missing from env template'
    )),
    check(
      'R2 storage smoke command',
      has(read('bin/test-r2-storage.php'), 'storage_r2_healthcheck') && has(goNoGoDoc, 'php bin/test-r2-storage.php'),
      'blocked',
      'R2 write/read/delete roundtrip is documented for production verification'
    ),
    check(
      'Stripe setup guide',
      has(stripeDoc, '14-day trial without payment') && has(stripeDoc, 'Customer Portal') && has(stripeDoc, 'tax_id_collection'),
      'blocked',
      'docs/STRIPE_PUBLIC_BETA_SETUP.md covers trial, portal, VAT, and webhook setup'
    ),
    check(
      'Checkout preserves existing trial',
      has(billing, 'function billing_checkout_trial_end_timestamp') && has(billing, "subscription_data[trial_end]"),
      'blocked',
      'Checkout sends subscription_data[trial_end] for trialing workspaces'
    ),
    check(
      'Checkout collects VAT ID',
      has(billing, "tax_id_collection[enabled]") && has(billing, "billing_address_collection"),
      'blocked',
      'Checkout collects billing address and tax ID when enabled'
    ),
    check(
      'Stripe webhook idempotency',
      has(billing, 'function billing_reserve_stripe_event') && has(billing, 'billing_finish_stripe_event'),
      'blocked',
      'Stripe events are reserved and finished idempotently'
    ),
    check(
      'Launch readiness doc',
      has(launchReadinessDoc, 'Public Beta') || has(launchReadinessDoc, 'Launch'),
      'warn',
      'docs/LAUNCH_READINESS.md exists and should remain the release checklist'
    ),
    check(
      'Launch go/no-go checklist',
      has(goNoGoDoc, 'Private Beta GO') &&
        has(goNoGoDoc, 'Paid Public Beta GO') &&
        has(goNoGoDoc, 'app.foxdesk.net') &&
        has(goNoGoDoc, 'platform.foxdesk.net') &&
        has(goNoGoDoc, 'foxdesk.net'),
      'blocked',
      'docs/PUBLIC_BETA_GO_NO_GO.md defines the human launch decision gates'
    ),
    check(
      'Technical debt plan',
      has(technicalDebtDoc, 'Primary product track: FoxDesk SaaS') && has(technicalDebtDoc, 'Milestone 10'),
      'warn',
      'technical debt plan keeps SaaS as primary track'
    ),
  ];
}

function summarize(checks) {
  const blocked = checks.filter((item) => item.status === 'blocked');
  const warn = checks.filter((item) => item.status === 'warn');
  return {
    status: blocked.length > 0 ? 'blocked' : 'pass',
    passed: checks.filter((item) => item.status === 'pass').length,
    warnings: warn.length,
    blocked: blocked.length,
    checks,
  };
}

function printHuman(result) {
  console.log(`Public beta gate: ${result.status.toUpperCase()}`);
  console.log('');
  for (const item of result.checks) {
    const marker = item.status === 'pass' ? 'PASS' : item.status === 'warn' ? 'WARN' : 'BLOCKED';
    console.log(`${marker.padEnd(7)} ${item.name}`);
    console.log(`        ${item.detail}`);
  }
}

const result = summarize(buildChecks());
if (process.argv.includes('--json')) {
  console.log(JSON.stringify(result, null, 2));
} else {
  printHuman(result);
}

process.exit(result.status === 'blocked' ? 1 : 0);
