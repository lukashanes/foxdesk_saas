const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { buildManifest, writeManifest } = require('../bin/cutover-manifest.js');

function makeDir() {
  return fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-cutover-manifest-'));
}

function write(dir, name, body) {
  fs.writeFileSync(path.join(dir, name), body);
}

function writeJson(dir, name, value) {
  write(dir, name, `${JSON.stringify(value, null, 2)}\n`);
}

function writeCompleteBundle(dir) {
  writeJson(dir, 'result.json', {
    status: 'passed',
    holdDecision: 'eligible_for_manual_cutover_review',
  });
  write(dir, 'report.md', '# Gate\n');
  writeJson(dir, 'cutover-preflight.json', {
    status: 'passed',
    decision: 'approved_for_manual_cutover',
  });
  write(dir, 'cutover-preflight.md', '# Preflight\n');
  writeJson(dir, 'cutover-postcheck.json', {
    status: 'passed',
    decision: 'cutover_confirmed',
  });
  write(dir, 'cutover-postcheck.md', '# Postcheck\n');
  writeJson(dir, 'cutover-status.json', {
    status: 'ok',
    phase: 'cutover_confirmed',
    decision: 'cutover_complete',
  });
  write(dir, 'cutover-status.md', '# Status\n');
  write(dir, 'desktop-work.png', 'png');
  write(dir, 'mobile-work.png', 'png');
}

{
  const dir = makeDir();
  writeCompleteBundle(dir);
  const manifest = buildManifest(dir);
  assert.strictEqual(manifest.summary.missingCoreFiles.length, 0);
  assert.strictEqual(manifest.summary.screenshotsPresent, 2);
  assert.strictEqual(manifest.lifecycle.postcheckDecision, 'cutover_confirmed');
  assert.strictEqual(manifest.lifecycle.statusPhase, 'cutover_confirmed');
  assert(/^[a-f0-9]{64}$/.test(manifest.manifestSha256), 'manifest hash should be sha256 hex');
  const output = writeManifest(manifest, dir);
  assert(fs.existsSync(output.jsonPath), 'manifest json should be written');
  assert(fs.existsSync(output.reportPath), 'manifest markdown should be written');
}

{
  const dir = makeDir();
  writeJson(dir, 'result.json', {
    status: 'passed',
    holdDecision: 'hold_remains_active',
  });
  const manifest = buildManifest(dir);
  assert(manifest.summary.missingCoreFiles.includes('cutover-preflight.json'));
  assert(manifest.summary.missingCoreFiles.includes('cutover-status.md'));
  assert.strictEqual(manifest.lifecycle.gateDecision, 'hold_remains_active');
}

{
  const dir = makeDir();
  writeCompleteBundle(dir);
  const first = buildManifest(dir);
  write(dir, 'desktop-work.png', 'changed');
  const second = buildManifest(dir);
  assert.notStrictEqual(first.manifestSha256, second.manifestSha256);
}

console.log('Cutover manifest tests OK');
