#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { readResult, verifyEvidence } = require('./verify-cutover-evidence.js');

function usage() {
  return [
    'Usage: node bin/cutover-preflight.js <result.json> [--output-dir=/path] [--max-age-hours=24] [--skip-prod-smoke] [--deploy-evidence=/path/deployment-evidence.json] [--restore-evidence=/path/restore.json]',
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
    deployEvidencePath: process.env.FOXDESK_DEPLOY_EVIDENCE_PATH || '',
    restoreEvidencePath: process.env.FOXDESK_RESTORE_EVIDENCE_PATH || '',
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
    if (arg.startsWith('--deploy-evidence=')) {
      options.deployEvidencePath = arg.slice('--deploy-evidence='.length);
      continue;
    }
    if (arg.startsWith('--restore-evidence=')) {
      options.restoreEvidencePath = arg.slice('--restore-evidence='.length);
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

function readOptionalJson(filePath, label) {
  if (!filePath) {
    return {
      status: 'skipped',
      path: '',
      failures: [],
      summary: null,
    };
  }

  if (!fs.existsSync(filePath)) {
    return {
      status: 'failed',
      path: filePath,
      failures: [`${label} evidence file does not exist: ${filePath}`],
      summary: null,
    };
  }

  try {
    const value = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    return {
      status: 'loaded',
      path: filePath,
      value,
      failures: [],
      summary: null,
    };
  } catch (error) {
    return {
      status: 'failed',
      path: filePath,
      failures: [`${label} evidence JSON is invalid: ${error.message}`],
      summary: null,
    };
  }
}

function validateDeploymentEvidence(filePath) {
  const evidence = readOptionalJson(filePath, 'Deployment');
  if (evidence.status !== 'loaded') {
    return evidence;
  }

  const value = evidence.value;
  const failures = [];
  if (value.status !== 'passed') {
    failures.push(`Deployment evidence status must be "passed"; got "${value.status || ''}".`);
  }
  if (value.decision !== 'deploy_complete_allowed') {
    failures.push(`Deployment evidence decision must be "deploy_complete_allowed"; got "${value.decision || ''}".`);
  }
  if (value.productionSmoke?.status !== 'passed') {
    failures.push('Deployment evidence must include passed production smoke.');
  }
  if (value.restoreEvidence?.status !== 'passed') {
    failures.push('Deployment evidence must include passed restore evidence.');
  }

  return {
    status: failures.length === 0 ? 'passed' : 'failed',
    path: filePath,
    failures,
    summary: {
      generatedAt: value.generatedAt || '',
      decision: value.decision || '',
      productionSmoke: value.productionSmoke?.status || '',
      restoreEvidence: value.restoreEvidence?.status || '',
    },
  };
}

function validateRestoreEvidence(filePath) {
  const evidence = readOptionalJson(filePath, 'Restore');
  if (evidence.status !== 'loaded') {
    return evidence;
  }

  const value = evidence.value;
  const failures = [];
  if (value.status !== 'passed') {
    failures.push(`Restore evidence status must be "passed"; got "${value.status || ''}".`);
  }
  if (!value.testedAt && !value.completedAt) {
    failures.push('Restore evidence must include testedAt or completedAt.');
  }
  if (!value.sourceBackup) {
    failures.push('Restore evidence must include sourceBackup.');
  }
  if (!value.restoreTarget) {
    failures.push('Restore evidence must include restoreTarget.');
  }
  if (!Array.isArray(value.checks) || value.checks.length === 0) {
    failures.push('Restore evidence must include checks.');
  } else {
    const failedChecks = value.checks.filter((check) => !['pass', 'passed'].includes(String(check.status || '').toLowerCase()));
    if (failedChecks.length > 0) {
      failures.push(`Restore evidence has failing checks: ${failedChecks.map((check) => check.name || check.label || 'unnamed').join(', ')}`);
    }
  }

  return {
    status: failures.length === 0 ? 'passed' : 'failed',
    path: filePath,
    failures,
    summary: {
      testedAt: value.testedAt || value.completedAt || '',
      sourceBackup: value.sourceBackup || '',
      restoreTarget: value.restoreTarget || '',
      checkCount: Array.isArray(value.checks) ? value.checks.length : 0,
    },
  };
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
  const deploymentEvidence = validateDeploymentEvidence(options.deployEvidencePath || '');
  const restoreEvidence = validateRestoreEvidence(options.restoreEvidencePath || '');
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
  for (const failure of deploymentEvidence.failures || []) {
    failures.push(`Deployment evidence: ${failure}`);
  }
  for (const failure of restoreEvidence.failures || []) {
    failures.push(`Restore evidence: ${failure}`);
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
    deploymentEvidence,
    restoreEvidence,
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
    `Deployment evidence: ${preflight.deploymentEvidence.status}`,
    `Restore evidence: ${preflight.restoreEvidence.status}`,
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

  if (preflight.deploymentEvidence.status !== 'skipped' || preflight.restoreEvidence.status !== 'skipped') {
    lines.push('', '## Deployment And Restore Evidence', '');
    lines.push(`- Deployment evidence: ${preflight.deploymentEvidence.status} ${preflight.deploymentEvidence.path || ''}`.trim());
    lines.push(`- Restore evidence: ${preflight.restoreEvidence.status} ${preflight.restoreEvidence.path || ''}`.trim());
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
  validateDeploymentEvidence,
  validateRestoreEvidence,
};
