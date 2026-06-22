const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const minimalEnv = {
  PATH: process.env.PATH,
  HOME: process.env.HOME,
};

function run(args = [], env = minimalEnv) {
  return spawnSync('node', ['bin/stripe-hosted-checkout-preflight.js', ...args], {
    cwd: __dirname + '/..',
    encoding: 'utf8',
    env,
  });
}

const runner = fs.readFileSync(path.join(__dirname, '..', 'bin/run-php.sh'), 'utf8');
const preflightSource = fs.readFileSync(path.join(__dirname, '..', 'bin/stripe-hosted-checkout-preflight.js'), 'utf8');
assert(runner.includes('--env $ENV_NAME'), 'Docker PHP fallback must pass whitelisted env values into the container.');
assert(runner.includes('BILLING_ENABLED'), 'Docker PHP fallback must pass billing env values for Stripe evidence smoke.');
assert(runner.includes('STRIPE_SECRET_KEY'), 'Docker PHP fallback must pass Stripe env values for Stripe evidence smoke.');
assert(preflightSource.includes('PHP CLI with pdo_mysql'), 'Preflight must explain the local Docker PHP missing driver failure.');

const missing = run(['--json']);
assert.strictEqual(missing.status, 1, 'Missing env must fail preflight.');
const missingResult = JSON.parse(missing.stdout);
assert.strictEqual(missingResult.ok, false);
assert.strictEqual(missingResult.env_ok, false);
assert(missingResult.env_checks.some((check) => check.key === 'STRIPE_SECRET_KEY' && check.status === 'missing'));
assert(!missing.stdout.includes('sk_test_'), 'Preflight output must never print secret key values.');
assert(!missing.stdout.includes('whsec_'), 'Preflight output must never print webhook secret values.');

const invalid = run(['--json'], {
  ...minimalEnv,
  BILLING_ENABLED: 'true',
  STRIPE_SECRET_KEY: 'not-a-secret',
  STRIPE_PRICE_CLOUD_BASE: 'price_cloud',
  STRIPE_PRICE_STORAGE_OVERAGE: 'price_storage',
  STRIPE_WEBHOOK_SECRET: 'not-a-webhook-secret',
  APP_URL: 'app.foxdesk.net',
});
assert.strictEqual(invalid.status, 1, 'Invalid env must fail preflight.');
const invalidResult = JSON.parse(invalid.stdout);
assert(invalidResult.env_checks.some((check) => check.key === 'STRIPE_SECRET_KEY' && check.status === 'invalid'));
assert(invalidResult.env_checks.some((check) => check.key === 'APP_URL' && check.status === 'invalid'));

const ready = run(['--json'], {
  ...minimalEnv,
  BILLING_ENABLED: 'true',
  STRIPE_SECRET_KEY: 'sk_test_redacted',
  STRIPE_PRICE_CLOUD_BASE: 'price_cloud',
  STRIPE_PRICE_STORAGE_OVERAGE: 'price_storage',
  STRIPE_WEBHOOK_SECRET: 'whsec_redacted',
  APP_URL: 'https://app.foxdesk.net',
});
assert.strictEqual(ready.status, 0, ready.stderr || ready.stdout);
const readyResult = JSON.parse(ready.stdout);
assert.strictEqual(readyResult.ok, true);
assert.strictEqual(readyResult.env_ok, true);
assert.strictEqual(readyResult.smoke, null);
assert(readyResult.next_step.includes('stripe:hosted-checkout:prepare'));

console.log('Stripe hosted Checkout preflight OK');
