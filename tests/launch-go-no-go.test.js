const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');

const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
assert(pkg.scripts['launch:go-no-go'], 'package.json must expose launch:go-no-go.');
assert(pkg.scripts['prod:deploy:evidence'], 'package.json must expose prod:deploy:evidence.');

const script = fs.readFileSync(path.join(root, 'bin/launch-go-no-go.js'), 'utf8');
assert(script.includes('FOXDESK_ACK_LEGAL_APPROVED'), 'Launch gate must require legal acknowledgement for paid public beta.');
assert(script.includes('FOXDESK_ACK_STRIPE_LIVE_TESTED'), 'Launch gate must require Stripe live-flow acknowledgement.');
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
assert.strictEqual(acknowledged.status, 0, acknowledged.stderr || acknowledged.stdout);
const acknowledgedResult = JSON.parse(acknowledged.stdout);
assert.strictEqual(acknowledgedResult.status, 'pass');

console.log('Launch go/no-go contract OK');
