const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const { buildPostcheck, checkSourceRedirect, writePostcheck } = require('../bin/cutover-postcheck.js');

const root = path.dirname(__dirname);

function makePreflight(overrides = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-cutover-postcheck-'));
  const preflight = {
    status: 'passed',
    decision: 'approved_for_manual_cutover',
    generatedAt: new Date().toISOString(),
    resultPath: path.join(dir, 'result.json'),
    evidence: {
      status: 'passed',
      mode: 'production',
      baseURL: 'https://app.foxdesk.net',
      endedAt: new Date().toISOString(),
      canLiftHold: true,
      holdDecision: 'eligible_for_manual_cutover_review',
      checklist: [
        { label: 'Work queue desktop/mobile', passed: true },
        { label: 'New ticket and attachment flow', passed: true },
      ],
      screenshots: [],
    },
    productionSmoke: {
      command: 'mocked preflight smoke',
      status: 'passed',
      exitCode: 0,
      stdout: 'Production smoke OK',
      stderr: '',
    },
    failures: [],
    nextSteps: [],
    ...overrides,
  };
  const preflightPath = path.join(dir, 'cutover-preflight.json');
  fs.writeFileSync(preflightPath, `${JSON.stringify(preflight, null, 2)}\n`);
  return { dir, preflightPath };
}

function passedSmoke() {
  return {
    command: 'mocked postcheck smoke',
    status: 'passed',
    exitCode: 0,
    startedAt: new Date().toISOString(),
    endedAt: new Date().toISOString(),
    stdout: 'Production smoke OK',
    stderr: '',
  };
}

async function passedRedirect(sourceUrl, targetUrl) {
  return {
    sourceUrl,
    targetUrl,
    status: 'passed',
    startedAt: new Date().toISOString(),
    endedAt: new Date().toISOString(),
    httpStatus: 302,
    location: `${targetUrl}/index.php?page=login`,
    finalUrl: `${targetUrl}/index.php?page=login`,
    reason: 'source redirects to target host',
  };
}

(async () => {
  {
    const { dir, preflightPath } = makePreflight();
    const postcheck = await buildPostcheck(preflightPath, {
      sourceUrl: 'https://helpdesk.aenze.com',
      targetUrl: 'https://app.foxdesk.net',
    }, root, {
      productionSmoke: passedSmoke(),
      sourceRedirect: passedRedirect,
    });
    assert.strictEqual(postcheck.status, 'passed');
    assert.strictEqual(postcheck.decision, 'cutover_confirmed');
    const output = writePostcheck(postcheck, dir);
    assert(fs.existsSync(output.jsonPath), 'postcheck json should be written');
    assert(fs.existsSync(output.reportPath), 'postcheck markdown should be written');
  }

  {
    const { preflightPath } = makePreflight({ status: 'failed', decision: 'cutover_blocked' });
    const postcheck = await buildPostcheck(preflightPath, {
      sourceUrl: 'https://helpdesk.aenze.com',
      targetUrl: 'https://app.foxdesk.net',
    }, root, {
      productionSmoke: passedSmoke(),
      sourceRedirect: passedRedirect,
    });
    assert.strictEqual(postcheck.status, 'failed');
    assert(postcheck.failures.some((failure) => failure.includes('Preflight status must be "passed"')));
    assert.strictEqual(postcheck.productionSmoke.status, 'skipped');
    assert.strictEqual(postcheck.sourceRedirect.status, 'skipped');
  }

  {
    const { preflightPath } = makePreflight();
    const postcheck = await buildPostcheck(preflightPath, {
      sourceUrl: 'https://helpdesk.aenze.com',
      targetUrl: 'https://app.foxdesk.net',
    }, root, {
      productionSmoke: passedSmoke(),
      sourceRedirect: async (sourceUrl, targetUrl) => ({
        sourceUrl,
        targetUrl,
        status: 'failed',
        startedAt: new Date().toISOString(),
        endedAt: new Date().toISOString(),
        httpStatus: 200,
        location: '',
        finalUrl: sourceUrl,
        reason: 'source did not return an HTTP redirect; got 200',
      }),
    });
    assert.strictEqual(postcheck.status, 'failed');
    assert(postcheck.failures.some((failure) => failure.includes('Source URL is not redirected')));
  }

  {
    const response = {
      status: 302,
      headers: {
        get(name) {
          return name === 'location' ? 'https://app.foxdesk.net/index.php?page=login' : '';
        },
      },
    };
    const redirect = await checkSourceRedirect(
      'https://helpdesk.aenze.com',
      'https://app.foxdesk.net',
      async () => response
    );
    assert.strictEqual(redirect.status, 'passed');
  }

  console.log('Cutover postcheck tests OK');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
