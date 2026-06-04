#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { readResult, verifyEvidence } = require('./verify-cutover-evidence.js');

function usage() {
  return [
    'Usage: node bin/cutover-preflight.js <result.json> [--output-dir=/path] [--max-age-hours=24] [--skip-prod-smoke]',
    '',
    'Runs the final manual cutover preflight. It does not perform DNS, ingest, or migration cutover changes.',
  ].join('\n');
}

function parseArgs(argv) {
  const options = {
    resultPath: process.env.FOXDESK_CUTOVER_RESULT || '',
    outputDir: process.env.FOXDESK_CUTOVER_PREFLIGHT_DIR || '',
    maxAgeHours: 24,
    skipProdSmoke: process.env.FOXDESK_CUTOVER_SKIP_PROD_SMOKE === '1',
  };

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') {
      options.help = true;
      continue;
    }
    if (arg === '--skip-prod-smoke') {
      options.skipProdSmoke = true;
      continue;
    }
    if (arg.startsWith('--output-dir=')) {
      options.outputDir = arg.slice('--output-dir='.length);
      continue;
    }
    if (arg.startsWith('--max-age-hours=')) {
      options.maxAgeHours = Number(arg.slice('--max-age-hours='.length));
      continue;
    }
    if (!arg.startsWith('--') && !options.resultPath) {
      options.resultPath = arg;
      continue;
    }
    throw new Error(`Unknown argument: ${arg}`);
  }

  if (!Number.isFinite(options.maxAgeHours) || options.maxAgeHours <= 0) {
    throw new Error('--max-age-hours must be a positive number.');
  }

  return options;
}

function runProductionSmoke(rootDir) {
  const command = process.execPath;
  const args = [path.join(rootDir, 'tests', 'smoke', 'production-smoke.js')];
  const startedAt = new Date();
  const result = spawnSync(command, args, {
    cwd: rootDir,
    encoding: 'utf8',
    env: process.env,
  });
  const endedAt = new Date();
  return {
    command: [command, ...args].join(' '),
    status: result.status === 0 ? 'passed' : 'failed',
    exitCode: result.status,
    startedAt: startedAt.toISOString(),
    endedAt: endedAt.toISOString(),
    stdout: (result.stdout || '').trim(),
    stderr: (result.stderr || '').trim(),
  };
}

function buildPreflight(resultPath, options, rootDir) {
  const evidence = readResult(resultPath);
  const evidenceFailures = verifyEvidence(evidence, {
    maxAgeHours: options.maxAgeHours,
    allowLocalTarget: false,
  });
  const smoke = options.productionSmoke || (options.skipProdSmoke
    ? {
        command: 'skipped',
        status: 'skipped',
        exitCode: null,
        startedAt: new Date().toISOString(),
        endedAt: new Date().toISOString(),
        stdout: '',
        stderr: '',
      }
    : runProductionSmoke(rootDir));

  const failures = [];
  for (const failure of evidenceFailures) {
    failures.push(`Evidence: ${failure}`);
  }
  if (smoke.status !== 'passed') {
    failures.push(options.skipProdSmoke
      ? 'Production smoke was skipped.'
      : `Production smoke failed with exit code ${smoke.exitCode}.`);
  }

  return {
    status: failures.length === 0 ? 'passed' : 'failed',
    decision: failures.length === 0 ? 'approved_for_manual_cutover' : 'cutover_blocked',
    generatedAt: new Date().toISOString(),
    resultPath,
    evidence: {
      status: evidence.status,
      mode: evidence.mode,
      baseURL: evidence.baseURL,
      endedAt: evidence.endedAt,
      canLiftHold: evidence.canLiftHold,
      holdDecision: evidence.holdDecision,
      checklist: evidence.checklist || [],
      screenshots: evidence.screenshots || [],
    },
    productionSmoke: smoke,
    failures,
    nextSteps: failures.length === 0
      ? [
          'Review the screenshots and imported workspace data one last time.',
          'Confirm the self-hosted source is paused for final sync.',
          'Perform final sync, then trigger single-active cutover.',
          'Only then change DNS or redirects for the customer-facing domain.',
        ]
      : [
          'Keep the cutover hold active.',
          'Fix the failed evidence or production smoke condition.',
          'Run the production cutover gate again and rerun this preflight.',
        ],
  };
}

function renderMarkdown(preflight) {
  const lines = [
    '# FoxDesk Cutover Preflight',
    '',
    `Status: ${preflight.status}`,
    `Decision: ${preflight.decision}`,
    `Generated: ${preflight.generatedAt}`,
    `Evidence: ${preflight.resultPath}`,
    `Target: ${preflight.evidence.baseURL || ''}`,
    `Evidence ended: ${preflight.evidence.endedAt || ''}`,
    `Production smoke: ${preflight.productionSmoke.status}`,
    '',
    '## Verdict',
    '',
  ];

  if (preflight.decision === 'approved_for_manual_cutover') {
    lines.push('The preflight passed. Manual cutover can proceed only after the operator reviews the evidence screenshots and imported workspace data.');
  } else {
    lines.push('The preflight failed. Keep the cutover hold active and do not switch DNS, disable self-hosted ingest, or mark the migration as single-active.');
  }

  if (preflight.failures.length > 0) {
    lines.push('', '## Failures', '');
    for (const failure of preflight.failures) {
      lines.push(`- ${failure}`);
    }
  }

  lines.push('', '## Evidence Checklist', '');
  for (const item of preflight.evidence.checklist || []) {
    lines.push(`- ${item.passed ? '[x]' : '[ ]'} ${item.label || '(unknown)'}`);
  }

  lines.push('', '## Next Steps', '');
  for (const step of preflight.nextSteps) {
    lines.push(`- ${step}`);
  }

  if (preflight.productionSmoke.stdout || preflight.productionSmoke.stderr) {
    lines.push('', '## Production Smoke Output', '', '```text');
    if (preflight.productionSmoke.stdout) {
      lines.push(preflight.productionSmoke.stdout);
    }
    if (preflight.productionSmoke.stderr) {
      lines.push(preflight.productionSmoke.stderr);
    }
    lines.push('```');
  }

  return `${lines.join('\n')}\n`;
}

function writePreflight(preflight, outputDir) {
  fs.mkdirSync(outputDir, { recursive: true });
  const jsonPath = path.join(outputDir, 'cutover-preflight.json');
  const reportPath = path.join(outputDir, 'cutover-preflight.md');
  fs.writeFileSync(jsonPath, `${JSON.stringify(preflight, null, 2)}\n`);
  fs.writeFileSync(reportPath, renderMarkdown(preflight));
  return { jsonPath, reportPath };
}

function main() {
  try {
    const options = parseArgs(process.argv.slice(2));
    if (options.help) {
      console.log(usage());
      return 0;
    }
    if (!options.resultPath) {
      throw new Error('Missing result.json path.');
    }

    const rootDir = path.dirname(__dirname);
    const resultPath = path.resolve(options.resultPath);
    const resultDir = path.dirname(resultPath);
    const outputDir = path.resolve(options.outputDir || resultDir);
    const preflight = buildPreflight(resultPath, options, rootDir);
    const output = writePreflight(preflight, outputDir);

    console.log(JSON.stringify({ ...preflight, output }, null, 2));
    return preflight.status === 'passed' ? 0 : 1;
  } catch (error) {
    console.error(error.message);
    console.error('');
    console.error(usage());
    return 1;
  }
}

if (require.main === module) {
  process.exitCode = main();
}

module.exports = {
  parseArgs,
  runProductionSmoke,
  buildPreflight,
  renderMarkdown,
  writePreflight,
};
