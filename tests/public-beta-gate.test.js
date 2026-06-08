const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');

const gate = spawnSync('node', ['bin/public-beta-gate.js', '--json'], {
  cwd: root,
  encoding: 'utf8',
});

assert.strictEqual(gate.status, 0, gate.stderr || gate.stdout);

const result = JSON.parse(gate.stdout);
assert.strictEqual(result.status, 'pass');

const checkNames = result.checks.map((check) => check.name);
assert(checkNames.includes('Stripe setup guide'), 'Stripe setup guide check is missing.');
assert(checkNames.includes('Checkout preserves existing trial'), 'Checkout trial preservation check is missing.');
assert(checkNames.includes('Checkout collects VAT ID'), 'VAT ID collection check is missing.');

const billing = fs.readFileSync(path.join(root, 'includes/billing-functions.php'), 'utf8');
assert(billing.includes('function billing_checkout_trial_end_timestamp'), 'Checkout trial helper is missing.');
assert(billing.includes("subscription_data[trial_end]"), 'Checkout must carry the current workspace trial end to Stripe.');
assert(billing.includes("subscription_data[trial_settings][end_behavior][missing_payment_method]"), 'Checkout trial end behavior must be explicit.');

const doc = fs.readFileSync(path.join(root, 'docs/STRIPE_PUBLIC_BETA_SETUP.md'), 'utf8');
assert(doc.includes('Aenze s.r.o.'), 'Stripe setup doc must name the operator.');
assert(doc.includes('https://app.foxdesk.net/index.php?page=stripe-webhook'), 'Stripe webhook URL must be documented.');
assert(doc.includes('STRIPE_PRICE_CLOUD_BASE'), 'Stripe base price env value must be documented.');
assert(doc.includes('STRIPE_PRICE_STORAGE_OVERAGE'), 'Stripe storage price env value must be documented.');
