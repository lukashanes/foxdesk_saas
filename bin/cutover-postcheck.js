#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { runProductionSmoke } = require('./cutover-preflight.js');

function usage() {
  return [
    'Usage: node bin/cutover-postcheck.js <cutover-preflight.json> [--source-url=https://helpdesk.aenze.com] [--target-url=https://app.foxdesk.net] [--output-dir=/path]',
    '',
    'Runs post-cutover checks. It verifies that the approved preflight exists, SaaS still passes production smoke, and the old source URL redirects to the SaaS target.',
  ].join('\n');
}

function parseArgs(argv) {
  const options = {
    preflightPath: process.env.FOXDESK_CUTOVER_PREFLIGHT || '',
    sourceUrl: process.env.FOXDESK_CUTOVER_SOURCE_URL || 'https://helpdesk.aenze.com',
    targetUrl: process.env.FOXDESK_CUTOVER_TARGET_URL || '',
    outputDir: process.env.FOXDESK_CUTOVER_POSTCHECK_DIR || '',
  };

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') {
      options.help = true;
      continue;
    }
    if (arg.startsWith('--source-url=')) {
      options.sourceUrl = arg.slice('--source-url='.length);
      continue;
    }
    if (arg.startsWith('--target-url=')) {
      options.targetUrl = arg.slice('--target-url='.length);
      continue;
    }
    if (arg.startsWith('--output-dir=')) {
      options.outputDir = arg.slice('--output-dir='.length);
      continue;
    }
    if (!arg.startsWith('--') && !options.preflightPath) {
      options.preflightPath = arg;
      continue;
    }
    throw new Error(`Unknown argument: ${arg}`);
  }

  return options;
}

function readJson(filePath, label) {
  if (!filePath) {
    throw new Error(`Missing ${label} path.`);
  }
  if (!fs.existsSync(filePath)) {
    throw new Error(`${label} does not exist: ${filePath}`);
  }
  const value = JSON.parse(fs.readFileSync(filePath, 'utf8'));
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    throw new Error(`${label} must be a JSON object.`);
  }
  return value;
}

function hostname(value) {
  try {
    return new URL(String(value)).hostname;
  } catch (_) {
    return '';
  }
}

function sameHostOrSubdomain(actualHost, expectedHost) {
  return actualHost === expectedHost || actualHost.endsWith(`.${expectedHost}`);
}

async function checkSourceRedirect(sourceUrl, targetUrl, fetchImpl = fetch) {
  const startedAt = new Date();
  const targetHost = hostname(targetUrl);
  const result = {
    sourceUrl,
    targetUrl,
    status: 'failed',
    startedAt: startedAt.toISOString(),
    endedAt: '',
    httpStatus: 0,
    location: '',
    finalUrl: '',
    reason: '',
  };

  try {
    const response = await fetchImpl(sourceUrl, {
      redirect: 'manual',
      headers: {
        'User-Agent': 'FoxDesk cutover postcheck',
      },
    });
    result.httpStatus = response.status;
    result.location = response.headers.get('location') || '';

    if (response.status >= 300 && response.status < 400 && result.location) {
      const resolved = new URL(result.location, sourceUrl).toString();
      const redirectHost = hostname(resolved);
      result.finalUrl = resolved;
      if (targetHost && sameHostOrSubdomain(redirectHost, targetHost)) {
        result.status = 'passed';
        result.reason = 'source redirects to target host';
      } else {
        result.reason = `source redirects away from target host: ${resolved}`;
      }
    } else {
      result.reason = `source did not return an HTTP redirect; got ${response.status}`;
    }
  } catch (error) {
    result.reason = error.message;
  } finally {
    result.endedAt = new Date().toISOString();
  }

  return result;
}

async function buildPostcheck(preflightPath, options, rootDir, hooks = {}) {
  const preflight = readJson(preflightPath, 'preflight');
  const targetUrl = options.targetUrl || preflight.evidence?.baseURL || 'https://app.foxdesk.net';
  const failures = [];

  if (preflight.status !== 'passed') {
    failures.push(`Preflight status must be "passed"; got "${preflight.status || ''}"`);
  }
  if (preflight.decision !== 'approved_for_manual_cutover') {
    failures.push(`Preflight decision must be "approved_for_manual_cutover"; got "${preflight.decision || ''}"`);
  }

  const productionSmoke = failures.length > 0
    ? {
        command: 'skipped because preflight is not approved',
        status: 'skipped',
        exitCode: null,
        startedAt: new Date().toISOString(),
        endedAt: new Date().toISOString(),
        stdout: '',
        stderr: '',
      }
    : (hooks.productionSmoke || runProductionSmoke(rootDir));

  if (productionSmoke.status !== 'passed') {
    failures.push(`Production smoke failed after cutover with status "${productionSmoke.status}".`);
  }

  const sourceRedirect = failures.length > 0
    ? {
        sourceUrl: options.sourceUrl,
        targetUrl,
        status: 'skipped',
        startedAt: new Date().toISOString(),
        endedAt: new Date().toISOString(),
        httpStatus: 0,
        location: '',
        finalUrl: '',
        reason: 'skipped because earlier post-cutover checks failed',
      }
    : await (hooks.sourceRedirect
        ? hooks.sourceRedirect(options.sourceUrl, targetUrl)
        : checkSourceRedirect(options.sourceUrl, targetUrl));

  if (sourceRedirect.status !== 'passed') {
    failures.push(`Source URL is not redirected to SaaS target: ${sourceRedirect.reason}`);
  }

  return {
    status: failures.length === 0 ? 'passed' : 'failed',
    decision: failures.length === 0 ? 'cutover_confirmed' : 'rollback_or_hold_required',
    generatedAt: new Date().toISOString(),
    preflightPath,
    sourceUrl: options.sourceUrl,
    targetUrl,
    preflight: {
      status: preflight.status,
      decision: preflight.decision,
      generatedAt: preflight.generatedAt || '',
      evidenceTarget: preflight.evidence?.baseURL || '',
    },
    productionSmoke,
    sourceRedirect,
    failures,
    nextSteps: failures.length === 0
      ? [
          'Keep monitoring health, email delivery, and customer support activity.',
          'Leave the self-hosted system disabled for active ingest and notifications.',
          'Keep the self-hosted backup available until the retention window expires.',
        ]
      : [
          'Do not consider cutover complete.',
          'Keep or restore the self-hosted system as the active support path if customer traffic is impacted.',
          'Fix the failing redirect or SaaS health condition, then rerun postcheck.',
        ],
  };
}

function renderMarkdown(postcheck) {
  const lines = [
    '# FoxDesk Cutover Postcheck',
    '',
    `Status: ${postcheck.status}`,
    `Decision: ${postcheck.decision}`,
    `Generated: ${postcheck.generatedAt}`,
    `Source URL: ${postcheck.sourceUrl}`,
    `Target URL: ${postcheck.targetUrl}`,
    `Production smoke: ${postcheck.productionSmoke.status}`,
    `Source redirect: ${postcheck.sourceRedirect.status}`,
    '',
    '## Verdict',
    '',
  ];

  if (postcheck.decision === 'cutover_confirmed') {
    lines.push('The post-cutover checks passed. SaaS is healthy and the old source URL redirects to the SaaS target.');
  } else {
    lines.push('The post-cutover checks failed. Treat the cutover as incomplete and keep rollback or hold actions available.');
  }

  if (postcheck.failures.length > 0) {
    lines.push('', '## Failures', '');
    for (const failure of postcheck.failures) {
      lines.push(`- ${failure}`);
    }
  }

  lines.push('', '## Source Redirect', '');
  lines.push(`- HTTP status: ${postcheck.sourceRedirect.httpStatus}`);
  lines.push(`- Location: ${postcheck.sourceRedirect.location || '(none)'}`);
  lines.push(`- Final URL: ${postcheck.sourceRedirect.finalUrl || '(none)'}`);
  lines.push(`- Reason: ${postcheck.sourceRedirect.reason || '(none)'}`);

  lines.push('', '## Next Steps', '');
  for (const step of postcheck.nextSteps) {
    lines.push(`- ${step}`);
  }

  if (postcheck.productionSmoke.stdout || postcheck.productionSmoke.stderr) {
    lines.push('', '## Production Smoke Output', '', '```text');
    if (postcheck.productionSmoke.stdout) {
      lines.push(postcheck.productionSmoke.stdout);
    }
    if (postcheck.productionSmoke.stderr) {
      lines.push(postcheck.productionSmoke.stderr);
    }
    lines.push('```');
  }

  return `${lines.join('\n')}\n`;
}

function writePostcheck(postcheck, outputDir) {
  fs.mkdirSync(outputDir, { recursive: true });
  const jsonPath = path.join(outputDir, 'cutover-postcheck.json');
  const reportPath = path.join(outputDir, 'cutover-postcheck.md');
  fs.writeFileSync(jsonPath, `${JSON.stringify(postcheck, null, 2)}\n`);
  fs.writeFileSync(reportPath, renderMarkdown(postcheck));
  return { jsonPath, reportPath };
}

async function main() {
  try {
    const options = parseArgs(process.argv.slice(2));
    if (options.help) {
      console.log(usage());
      return 0;
    }
    if (!options.preflightPath) {
      throw new Error('Missing cutover-preflight.json path.');
    }

    const rootDir = path.dirname(__dirname);
    const preflightPath = path.resolve(options.preflightPath);
    const outputDir = path.resolve(options.outputDir || path.dirname(preflightPath));
    const postcheck = await buildPostcheck(preflightPath, options, rootDir);
    const output = writePostcheck(postcheck, outputDir);

    console.log(JSON.stringify({ ...postcheck, output }, null, 2));
    return postcheck.status === 'passed' ? 0 : 1;
  } catch (error) {
    console.error(error.message);
    console.error('');
    console.error(usage());
    return 1;
  }
}

if (require.main === module) {
  main().then((code) => {
    process.exitCode = code;
  });
}

module.exports = {
  parseArgs,
  readJson,
  checkSourceRedirect,
  buildPostcheck,
  renderMarkdown,
  writePostcheck,
};
