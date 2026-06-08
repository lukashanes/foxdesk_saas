const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'bin/configure-stripe-beta.js'), 'utf8');
const docs = fs.readFileSync(path.join(root, 'docs/STRIPE_PUBLIC_BETA_SETUP.md'), 'utf8');
const gitignore = fs.readFileSync(path.join(root, '.gitignore'), 'utf8');

assert(script.includes('checkout.session.completed'), 'Webhook configurator must include checkout completion.');
assert(script.includes('customer.subscription.updated'), 'Webhook configurator must include subscription updates.');
assert(script.includes('invoice.payment_failed'), 'Webhook configurator must include payment failures.');
assert(script.includes('features[customer_update][allowed_updates][]'), 'Portal must allow customer detail updates.');
assert(script.includes("'tax_id'"), 'Portal must allow VAT/tax ID updates.');
assert(script.includes('features[payment_method_update][enabled]'), 'Portal must allow payment method updates.');
assert(script.includes('features[invoice_history][enabled]'), 'Portal must allow invoice history.');
assert(script.includes('features[subscription_cancel][mode]'), 'Portal must define cancellation mode.');
assert(script.includes('STRIPE_WEBHOOK_URL'), 'Webhook URL must be configurable.');
assert(script.includes('maskSecret'), 'Configurator must mask secrets in normal output.');
assert(script.includes('--write-env='), 'Configurator must support writing generated secrets to an env file.');
assert(script.includes("key.endsWith('[]')"), 'Array form fields must not append duplicate [] suffixes.');
assert(gitignore.includes('.stripe.generated.env'), 'Generated Stripe env file must be gitignored.');

const dryRun = spawnSync('node', ['bin/configure-stripe-beta.js', '--dry-run', '--json'], {
  cwd: root,
  encoding: 'utf8',
  env: {
    ...process.env,
    STRIPE_SECRET_KEY: 'sk_live_fake_for_contract',
  },
});

assert.strictEqual(dryRun.status, 0, dryRun.stderr || dryRun.stdout);
const result = JSON.parse(dryRun.stdout);
assert.strictEqual(result.dry_run, true);
assert.strictEqual(result.portal.action, 'dry_run');
assert.strictEqual(result.webhook.action, 'dry_run');
assert.strictEqual(result.webhook.url, 'https://app.foxdesk.net/index.php?page=stripe-webhook');

assert(docs.includes('price_1TduGWLE0xWWZe199qWeD07B'), 'Docs must include current cloud base price id.');
assert(docs.includes('price_1TduGXLE0xWWZe19fwYt9nIF'), 'Docs must include current storage price id.');
