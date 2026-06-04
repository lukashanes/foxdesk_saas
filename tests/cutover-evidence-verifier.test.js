const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const { verifyEvidence } = require('../bin/verify-cutover-evidence.js');

const root = path.dirname(__dirname);
const verifierPath = path.join(root, 'bin', 'verify-cutover-evidence.js');

function makeEvidence(overrides = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-cutover-verifier-'));
  const screenshots = ['desktop-work.png', 'mobile-work.png'].map((name) => {
    const file = path.join(dir, name);
    fs.writeFileSync(file, 'png');
    return file;
  });
  const result = {
    status: 'passed',
    mode: 'production',
    baseURL: 'https://app.foxdesk.net',
    adminEmail: 'operator@example.com',
    allowMutation: true,
    prepareFixtures: false,
    requireSearchCounts: true,
    searchQuery: 'Aenze',
    reportTimeRange: 'last_month',
    startedAt: new Date(Date.now() - 60_000).toISOString(),
    endedAt: new Date().toISOString(),
    durationMs: 60_000,
    canLiftHold: true,
    holdDecision: 'eligible_for_manual_cutover_review',
    checks: [
      'desktop work',
      'mobile work',
      'new ticket create/upload/download',
    ],
    checklist: [
      { label: 'Work queue desktop/mobile', passed: true },
      { label: 'New ticket and attachment flow', passed: true },
    ],
    screenshots,
    evidenceDir: dir,
    error: null,
    ...overrides,
  };
  const resultPath = path.join(dir, 'result.json');
  fs.writeFileSync(resultPath, `${JSON.stringify(result, null, 2)}\n`);
  return { dir, result, resultPath };
}

function runCli(resultPath, args = []) {
  return execFileSync(process.execPath, [verifierPath, resultPath, ...args], {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

{
  const { result, resultPath } = makeEvidence();
  assert.deepStrictEqual(verifyEvidence(result, { maxAgeHours: 24, allowLocalTarget: false }), []);
  const output = runCli(resultPath);
  assert(output.includes('Cutover evidence OK'), 'CLI should accept valid production evidence.');
}

{
  const { result } = makeEvidence({ mode: 'local', canLiftHold: false, holdDecision: 'hold_remains_active' });
  const failures = verifyEvidence(result, { maxAgeHours: 24, allowLocalTarget: false });
  assert(failures.some((failure) => failure.includes('mode must be "production"')));
  assert(failures.some((failure) => failure.includes('canLiftHold must be true')));
}

{
  const { result } = makeEvidence({
    allowMutation: false,
    canLiftHold: false,
    holdDecision: 'hold_remains_active',
    checklist: [
      { label: 'Work queue desktop/mobile', passed: true },
      { label: 'New ticket and attachment flow', passed: false },
    ],
  });
  const failures = verifyEvidence(result, { maxAgeHours: 24, allowLocalTarget: false });
  assert(failures.some((failure) => failure.includes('FOXDESK_CUTOVER_ALLOW_MUTATION=1')));
  assert(failures.some((failure) => failure.includes('New ticket and attachment flow')));
}

{
  const { result } = makeEvidence({
    baseURL: 'http://127.0.0.1:8090',
  });
  const failures = verifyEvidence(result, { maxAgeHours: 24, allowLocalTarget: false });
  assert(failures.some((failure) => failure.includes('non-local production URL')));
  assert.deepStrictEqual(verifyEvidence(result, { maxAgeHours: 24, allowLocalTarget: true }), []);
}

{
  const { result } = makeEvidence({
    endedAt: new Date(Date.now() - 3 * 60 * 60 * 1000).toISOString(),
  });
  const failures = verifyEvidence(result, { maxAgeHours: 1, allowLocalTarget: false });
  assert(failures.some((failure) => failure.includes('older than 1 hour')));
}

{
  const { result } = makeEvidence();
  fs.unlinkSync(result.screenshots[0]);
  const failures = verifyEvidence(result, { maxAgeHours: 24, allowLocalTarget: false });
  assert(failures.some((failure) => failure.includes('screenshot is missing')));
}

console.log('Cutover evidence verifier tests OK');
