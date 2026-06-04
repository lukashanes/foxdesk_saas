const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const { buildArchive, safeArchiveName } = require('../bin/cutover-archive.js');

function makeDir() {
  return fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-cutover-archive-'));
}

function write(dir, name, body) {
  fs.writeFileSync(path.join(dir, name), body);
}

function writeJson(dir, name, value) {
  write(dir, name, `${JSON.stringify(value, null, 2)}\n`);
}

function sha256(filePath) {
  const hash = crypto.createHash('sha256');
  hash.update(fs.readFileSync(filePath));
  return hash.digest('hex');
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
}

{
  assert.strictEqual(safeArchiveName('../bad name'), 'bad-name.tar.gz');
  assert.strictEqual(safeArchiveName('bundle.tar.gz'), 'bundle.tar.gz');
}

{
  const dir = makeDir();
  const out = makeDir();
  writeCompleteBundle(dir);
  const archive = buildArchive({
    dir,
    outputDir: out,
    archiveName: 'foxdesk-test-cutover.tar.gz',
    allowMissingCore: false,
  });
  assert(fs.existsSync(archive.archivePath), 'archive should exist');
  assert(fs.existsSync(archive.sha256Path), 'archive sha file should exist');
  assert.strictEqual(archive.archiveSha256, sha256(archive.archivePath));
  assert(archive.files.includes('cutover-manifest.json'), 'archive should include manifest json');
  assert(archive.files.includes('desktop-work.png'), 'archive should include screenshot');
  const listing = execFileSync('tar', ['-tzf', archive.archivePath], { encoding: 'utf8' });
  assert(listing.includes('cutover-manifest.json'));
  assert(listing.includes('desktop-work.png'));
}

{
  const dir = makeDir();
  writeJson(dir, 'result.json', {
    status: 'passed',
    holdDecision: 'hold_remains_active',
  });
  assert.throws(() => buildArchive({
    dir,
    outputDir: dir,
    archiveName: 'incomplete.tar.gz',
    allowMissingCore: false,
  }), /Cannot archive incomplete cutover bundle/);
}

{
  const dir = makeDir();
  writeJson(dir, 'result.json', {
    status: 'passed',
    holdDecision: 'hold_remains_active',
  });
  const archive = buildArchive({
    dir,
    outputDir: dir,
    archiveName: 'incomplete-allowed.tar.gz',
    allowMissingCore: true,
  });
  assert(fs.existsSync(archive.archivePath), 'override should archive incomplete bundle');
}

console.log('Cutover archive tests OK');
