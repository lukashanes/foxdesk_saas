const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { buildPreflight, writePreflight } = require('../bin/cutover-preflight.js');

const root = path.dirname(__dirname);

function makeEvidence(overrides = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-cutover-preflight-'));
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
    checks: ['desktop work', 'mobile work', 'new ticket create/upload/download'],
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
  return { dir, resultPath };
}

{
  const { dir, resultPath } = makeEvidence();
  const approved = buildPreflight(resultPath, {
    maxAgeHours: 24,
    skipProdSmoke: false,
    productionSmoke: {
      command: 'mocked production smoke',
      status: 'passed',
      exitCode: 0,
      startedAt: new Date().toISOString(),
      endedAt: new Date().toISOString(),
      stdout: 'Production smoke OK',
      stderr: '',
    },
  }, root);
  assert.strictEqual(approved.status, 'passed');
  assert.strictEqual(approved.decision, 'approved_for_manual_cutover');
  const output = writePreflight(approved, dir);
  assert(fs.existsSync(output.jsonPath), 'preflight json should be written');
  assert(fs.existsSync(output.reportPath), 'preflight markdown should be written');
}

{
  const { resultPath } = makeEvidence();
  const preflight = buildPreflight(resultPath, { maxAgeHours: 24, skipProdSmoke: true }, root);
  assert.strictEqual(preflight.status, 'failed');
  assert.strictEqual(preflight.decision, 'cutover_blocked');
  assert(preflight.failures.some((failure) => failure.includes('Production smoke was skipped')));
}

{
  const { resultPath } = makeEvidence({
    mode: 'local',
    baseURL: 'http://127.0.0.1:8090',
    canLiftHold: false,
    holdDecision: 'hold_remains_active',
  });
  const preflight = buildPreflight(resultPath, { maxAgeHours: 24, skipProdSmoke: true }, root);
  assert.strictEqual(preflight.status, 'failed');
  assert(preflight.failures.some((failure) => failure.includes('mode must be "production"')));
  assert(preflight.failures.some((failure) => failure.includes('non-local production URL')));
}

{
  const { resultPath } = makeEvidence({
    allowMutation: false,
    canLiftHold: false,
    holdDecision: 'hold_remains_active',
    checklist: [
      { label: 'Work queue desktop/mobile', passed: true },
      { label: 'New ticket and attachment flow', passed: false },
    ],
  });
  const preflight = buildPreflight(resultPath, { maxAgeHours: 24, skipProdSmoke: true }, root);
  assert.strictEqual(preflight.status, 'failed');
  assert(preflight.failures.some((failure) => failure.includes('FOXDESK_CUTOVER_ALLOW_MUTATION=1')));
  assert(preflight.failures.some((failure) => failure.includes('New ticket and attachment flow')));
}

console.log('Cutover preflight tests OK');
