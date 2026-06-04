const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { buildStatus, writeStatus } = require('../bin/cutover-status.js');

function makeDir() {
  return fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-cutover-status-'));
}

function writeJson(dir, name, value) {
  const file = path.join(dir, name);
  fs.writeFileSync(file, `${JSON.stringify(value, null, 2)}\n`);
  return file;
}

function result(overrides = {}) {
  return {
    status: 'passed',
    mode: 'production',
    baseURL: 'https://app.foxdesk.net',
    endedAt: new Date().toISOString(),
    canLiftHold: true,
    holdDecision: 'eligible_for_manual_cutover_review',
    ...overrides,
  };
}

function preflight(overrides = {}) {
  return {
    status: 'passed',
    decision: 'approved_for_manual_cutover',
    generatedAt: new Date().toISOString(),
    ...overrides,
  };
}

function postcheck(overrides = {}) {
  return {
    status: 'passed',
    decision: 'cutover_confirmed',
    generatedAt: new Date().toISOString(),
    ...overrides,
  };
}

{
  const dir = makeDir();
  writeJson(dir, 'result.json', result());
  const status = buildStatus({ dir, resultPath: path.join(dir, 'result.json') });
  assert.strictEqual(status.status, 'ok');
  assert.strictEqual(status.phase, 'evidence_ready');
  assert.strictEqual(status.decision, 'run_preflight');
}

{
  const dir = makeDir();
  writeJson(dir, 'result.json', result());
  writeJson(dir, 'cutover-preflight.json', preflight());
  const status = buildStatus({
    dir,
    resultPath: path.join(dir, 'result.json'),
    preflightPath: path.join(dir, 'cutover-preflight.json'),
  });
  assert.strictEqual(status.status, 'ok');
  assert.strictEqual(status.phase, 'preflight_approved');
  assert.strictEqual(status.decision, 'manual_cutover_allowed');
}

{
  const dir = makeDir();
  writeJson(dir, 'result.json', result());
  writeJson(dir, 'cutover-preflight.json', preflight());
  writeJson(dir, 'cutover-postcheck.json', postcheck());
  const status = buildStatus({
    dir,
    resultPath: path.join(dir, 'result.json'),
    preflightPath: path.join(dir, 'cutover-preflight.json'),
    postcheckPath: path.join(dir, 'cutover-postcheck.json'),
  });
  assert.strictEqual(status.status, 'ok');
  assert.strictEqual(status.phase, 'cutover_confirmed');
  assert.strictEqual(status.decision, 'cutover_complete');
  const output = writeStatus(status, dir);
  assert(fs.existsSync(output.jsonPath), 'status json should be written');
  assert(fs.existsSync(output.reportPath), 'status markdown should be written');
}

{
  const dir = makeDir();
  writeJson(dir, 'result.json', result({ canLiftHold: false, holdDecision: 'hold_remains_active' }));
  writeJson(dir, 'cutover-preflight.json', preflight({ status: 'failed', decision: 'cutover_blocked' }));
  const status = buildStatus({
    dir,
    resultPath: path.join(dir, 'result.json'),
    preflightPath: path.join(dir, 'cutover-preflight.json'),
  });
  assert.strictEqual(status.status, 'attention_required');
  assert.strictEqual(status.phase, 'preflight_blocked');
  assert.strictEqual(status.decision, 'cutover_blocked');
}

{
  const dir = makeDir();
  const status = buildStatus({ dir, resultPath: path.join(dir, 'result.json') });
  assert.strictEqual(status.status, 'attention_required');
  assert.strictEqual(status.phase, 'hold_active');
  assert(status.failures.some((failure) => failure.includes('Gate evidence missing')));
}

console.log('Cutover status tests OK');
