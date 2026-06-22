const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');

function run(args = []) {
  return spawnSync('node', ['bin/feature-story-status.js', ...args], {
    cwd: root,
    encoding: 'utf8',
  });
}

const current = run(['--json']);
assert.strictEqual(current.status, 0, current.stderr || current.stdout);
const currentResult = JSON.parse(current.stdout);
assert.strictEqual(currentResult.total, 62);
assert.strictEqual(currentResult.status, 'incomplete');
assert.strictEqual(currentResult.open, 1);
assert.strictEqual(currentResult.open_items[0].feature_id, 'BILLING-002');
assert.strictEqual(currentResult.open_items[0].test_status, 'needs_external_smoke');

const strict = run(['--strict', '--json']);
assert.strictEqual(strict.status, 1, 'Strict tracker status must fail while BILLING-002 is open.');
assert.strictEqual(JSON.parse(strict.stdout).open_items[0].feature_id, 'BILLING-002');

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-feature-stories-'));
const source = fs.readFileSync(path.join(root, 'docs/feature-user-stories.csv'), 'utf8');
const completed = source.replace(
  /BILLING-002,saas,Billing,"Stripe checkout, portal, tax and VAT",Workspace admin,"As a workspace admin I want to add billing details, VAT ID and manage subscription securely.","Stripe checkout\/portal\/webhook support trial conversion, VAT\/tax, cancellation, failed payment recovery and usage\/state updates.",([^,]+(?:,[^,]+)*?),testing,needs_external_smoke,/,
  (match) => match.replace(',testing,needs_external_smoke,', ',retested_pass,retested_pass,')
);
const completedPath = path.join(tmp, 'feature-user-stories-complete.csv');
fs.writeFileSync(completedPath, completed);

const completedRun = run(['--strict', '--json', '--file', completedPath]);
assert.strictEqual(completedRun.status, 0, completedRun.stderr || completedRun.stdout);
const completedResult = JSON.parse(completedRun.stdout);
assert.strictEqual(completedResult.status, 'complete');
assert.strictEqual(completedResult.open, 0);

console.log('Feature story status report OK');
