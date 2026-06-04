#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

function usage() {
  return [
    'Usage: node bin/cutover-status.js [--dir=/path/to/cutover-dir] [--result=/path/result.json] [--preflight=/path/cutover-preflight.json] [--postcheck=/path/cutover-postcheck.json] [--output-dir=/path]',
    '',
    'Builds one lifecycle status report from cutover gate, preflight, and postcheck artifacts.',
  ].join('\n');
}

function parseArgs(argv) {
  const options = {
    dir: process.env.FOXDESK_CUTOVER_DIR || '',
    resultPath: process.env.FOXDESK_CUTOVER_RESULT || '',
    preflightPath: process.env.FOXDESK_CUTOVER_PREFLIGHT || '',
    postcheckPath: process.env.FOXDESK_CUTOVER_POSTCHECK || '',
    outputDir: process.env.FOXDESK_CUTOVER_STATUS_DIR || '',
  };

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') {
      options.help = true;
      continue;
    }
    if (arg.startsWith('--dir=')) {
      options.dir = arg.slice('--dir='.length);
      continue;
    }
    if (arg.startsWith('--result=')) {
      options.resultPath = arg.slice('--result='.length);
      continue;
    }
    if (arg.startsWith('--preflight=')) {
      options.preflightPath = arg.slice('--preflight='.length);
      continue;
    }
    if (arg.startsWith('--postcheck=')) {
      options.postcheckPath = arg.slice('--postcheck='.length);
      continue;
    }
    if (arg.startsWith('--output-dir=')) {
      options.outputDir = arg.slice('--output-dir='.length);
      continue;
    }
    if (!arg.startsWith('--') && !options.dir) {
      options.dir = arg;
      continue;
    }
    throw new Error(`Unknown argument: ${arg}`);
  }

  if (options.dir) {
    const dir = path.resolve(options.dir);
    options.resultPath = options.resultPath || path.join(dir, 'result.json');
    options.preflightPath = options.preflightPath || path.join(dir, 'cutover-preflight.json');
    options.postcheckPath = options.postcheckPath || path.join(dir, 'cutover-postcheck.json');
    options.outputDir = options.outputDir || dir;
  }

  return options;
}

function readOptionalJson(filePath) {
  if (!filePath) {
    return { exists: false, path: '', value: null, error: 'path not provided' };
  }
  const resolved = path.resolve(filePath);
  if (!fs.existsSync(resolved)) {
    return { exists: false, path: resolved, value: null, error: 'file not found' };
  }
  try {
    const value = JSON.parse(fs.readFileSync(resolved, 'utf8'));
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return { exists: true, path: resolved, value: null, error: 'file is not a JSON object' };
    }
    return { exists: true, path: resolved, value, error: '' };
  } catch (error) {
    return { exists: true, path: resolved, value: null, error: error.message };
  }
}

function artifactSummary(artifact, fields) {
  if (!artifact.value) {
    return {
      exists: artifact.exists,
      path: artifact.path,
      error: artifact.error,
    };
  }

  const summary = {
    exists: true,
    path: artifact.path,
    error: '',
  };
  for (const field of fields) {
    summary[field] = artifact.value[field];
  }
  return summary;
}

function buildStatus(options) {
  const result = readOptionalJson(options.resultPath);
  const preflight = readOptionalJson(options.preflightPath);
  const postcheck = readOptionalJson(options.postcheckPath);
  const failures = [];

  if (!result.value) {
    failures.push(`Gate evidence missing or invalid: ${result.error}`);
  }
  if (preflight.exists && !preflight.value) {
    failures.push(`Preflight artifact invalid: ${preflight.error}`);
  }
  if (postcheck.exists && !postcheck.value) {
    failures.push(`Postcheck artifact invalid: ${postcheck.error}`);
  }

  let phase = 'hold_active';
  let decision = 'cutover_not_ready';

  if (result.value?.canLiftHold === true && result.value?.holdDecision === 'eligible_for_manual_cutover_review') {
    phase = 'evidence_ready';
    decision = 'run_preflight';
  }
  if (preflight.value?.decision === 'approved_for_manual_cutover') {
    phase = 'preflight_approved';
    decision = 'manual_cutover_allowed';
  } else if (preflight.value?.decision === 'cutover_blocked') {
    phase = 'preflight_blocked';
    decision = 'cutover_blocked';
  }
  if (postcheck.value?.decision === 'cutover_confirmed') {
    phase = 'cutover_confirmed';
    decision = 'cutover_complete';
  } else if (postcheck.value?.decision === 'rollback_or_hold_required') {
    phase = 'postcheck_failed';
    decision = 'rollback_or_hold_required';
  }

  if (phase === 'cutover_confirmed') {
    failures.length = 0;
  }

  return {
    status: failures.length === 0 && phase !== 'hold_active' && phase !== 'preflight_blocked' && phase !== 'postcheck_failed'
      ? 'ok'
      : 'attention_required',
    phase,
    decision,
    generatedAt: new Date().toISOString(),
    artifacts: {
      result: artifactSummary(result, ['status', 'mode', 'baseURL', 'endedAt', 'canLiftHold', 'holdDecision']),
      preflight: artifactSummary(preflight, ['status', 'decision', 'generatedAt']),
      postcheck: artifactSummary(postcheck, ['status', 'decision', 'generatedAt']),
    },
    failures,
    nextSteps: nextStepsForPhase(phase),
  };
}

function nextStepsForPhase(phase) {
  switch (phase) {
    case 'evidence_ready':
      return ['Run cutover:preflight against the latest production result.json.'];
    case 'preflight_approved':
      return ['Perform final sync and manual cutover, then run cutover:postcheck.'];
    case 'cutover_confirmed':
      return ['Keep monitoring SaaS health, mail delivery, and customer activity.'];
    case 'preflight_blocked':
      return ['Keep the cutover hold active and rerun the production gate/preflight after fixes.'];
    case 'postcheck_failed':
      return ['Treat cutover as incomplete and keep rollback or hold actions available.'];
    default:
      return ['Run the production cutover gate with mutation enabled and collect evidence.'];
  }
}

function renderMarkdown(status) {
  const lines = [
    '# FoxDesk Cutover Status',
    '',
    `Status: ${status.status}`,
    `Phase: ${status.phase}`,
    `Decision: ${status.decision}`,
    `Generated: ${status.generatedAt}`,
    '',
    '## Artifacts',
    '',
    `- Gate evidence: ${status.artifacts.result.exists ? status.artifacts.result.path : 'missing'}`,
    `- Preflight: ${status.artifacts.preflight.exists ? status.artifacts.preflight.path : 'missing'}`,
    `- Postcheck: ${status.artifacts.postcheck.exists ? status.artifacts.postcheck.path : 'missing'}`,
    '',
    '## Next Steps',
    '',
  ];

  for (const step of status.nextSteps) {
    lines.push(`- ${step}`);
  }

  if (status.failures.length > 0) {
    lines.push('', '## Failures', '');
    for (const failure of status.failures) {
      lines.push(`- ${failure}`);
    }
  }

  return `${lines.join('\n')}\n`;
}

function writeStatus(status, outputDir) {
  fs.mkdirSync(outputDir, { recursive: true });
  const jsonPath = path.join(outputDir, 'cutover-status.json');
  const reportPath = path.join(outputDir, 'cutover-status.md');
  fs.writeFileSync(jsonPath, `${JSON.stringify(status, null, 2)}\n`);
  fs.writeFileSync(reportPath, renderMarkdown(status));
  return { jsonPath, reportPath };
}

function main() {
  try {
    const options = parseArgs(process.argv.slice(2));
    if (options.help) {
      console.log(usage());
      return 0;
    }

    if (!options.resultPath && !options.preflightPath && !options.postcheckPath) {
      throw new Error('Provide --dir or at least one artifact path.');
    }

    const outputDir = path.resolve(options.outputDir || options.dir || process.cwd());
    const status = buildStatus(options);
    const output = writeStatus(status, outputDir);
    console.log(JSON.stringify({ ...status, output }, null, 2));
    return status.status === 'ok' ? 0 : 1;
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
  readOptionalJson,
  buildStatus,
  renderMarkdown,
  writeStatus,
};
