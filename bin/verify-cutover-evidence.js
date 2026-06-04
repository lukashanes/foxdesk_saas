#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

function usage() {
  return [
    'Usage: node bin/verify-cutover-evidence.js <result.json> [--max-age-hours=24] [--allow-local-target]',
    '',
    'Verifies that a cutover gate evidence result is eligible for manual production cutover review.',
  ].join('\n');
}

function parseArgs(argv) {
  const options = {
    resultPath: process.env.FOXDESK_CUTOVER_RESULT || '',
    maxAgeHours: 24,
    allowLocalTarget: false,
  };

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') {
      options.help = true;
      continue;
    }
    if (arg === '--allow-local-target') {
      options.allowLocalTarget = true;
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

function readResult(resultPath) {
  if (!resultPath) {
    throw new Error('Missing result.json path.');
  }
  if (!fs.existsSync(resultPath)) {
    throw new Error(`Evidence result does not exist: ${resultPath}`);
  }
  const raw = fs.readFileSync(resultPath, 'utf8');
  const result = JSON.parse(raw);
  if (!result || typeof result !== 'object' || Array.isArray(result)) {
    throw new Error('Evidence result must be a JSON object.');
  }
  return result;
}

function isLocalUrl(value) {
  try {
    const url = new URL(String(value));
    return ['localhost', '127.0.0.1', '::1'].includes(url.hostname);
  } catch (_) {
    return true;
  }
}

function verifyEvidence(result, options) {
  const failures = [];
  const now = Date.now();
  const endedAtMs = Date.parse(String(result.endedAt || ''));
  const maxAgeMs = options.maxAgeHours * 60 * 60 * 1000;

  if (result.status !== 'passed') {
    failures.push(`status must be "passed"; got "${result.status || ''}"`);
  }
  if (result.mode !== 'production') {
    failures.push(`mode must be "production"; got "${result.mode || ''}"`);
  }
  if (result.allowMutation !== true) {
    failures.push('FOXDESK_CUTOVER_ALLOW_MUTATION=1 is required for a hold-lifting gate run.');
  }
  if (result.canLiftHold !== true) {
    failures.push('canLiftHold must be true.');
  }
  if (result.holdDecision !== 'eligible_for_manual_cutover_review') {
    failures.push(`holdDecision must be "eligible_for_manual_cutover_review"; got "${result.holdDecision || ''}"`);
  }
  if (!result.baseURL || (!options.allowLocalTarget && isLocalUrl(result.baseURL))) {
    failures.push(`baseURL must be a non-local production URL; got "${result.baseURL || ''}"`);
  }
  if (!Number.isFinite(endedAtMs)) {
    failures.push('endedAt must be a valid ISO timestamp.');
  } else if (Math.abs(now - endedAtMs) > maxAgeMs) {
    failures.push(`evidence is older than ${options.maxAgeHours} hour(s).`);
  }

  const checklist = Array.isArray(result.checklist) ? result.checklist : [];
  if (checklist.length === 0) {
    failures.push('checklist is missing.');
  }
  for (const item of checklist) {
    if (!item || item.passed !== true) {
      failures.push(`checklist item is not passed: ${item && item.label ? item.label : '(unknown)'}`);
    }
  }

  const screenshots = Array.isArray(result.screenshots) ? result.screenshots : [];
  if (screenshots.length === 0) {
    failures.push('screenshots are missing.');
  }
  for (const screenshot of screenshots) {
    if (typeof screenshot !== 'string' || screenshot.trim() === '') {
      failures.push('screenshot path is empty.');
      continue;
    }
    if (!fs.existsSync(screenshot)) {
      failures.push(`screenshot is missing: ${screenshot}`);
      continue;
    }
    const stat = fs.statSync(screenshot);
    if (!stat.isFile() || stat.size <= 0) {
      failures.push(`screenshot is empty or not a file: ${screenshot}`);
    }
  }

  return failures;
}

function main() {
  let options;
  try {
    options = parseArgs(process.argv.slice(2));
    if (options.help) {
      console.log(usage());
      return 0;
    }

    const resultPath = path.resolve(options.resultPath);
    const result = readResult(resultPath);
    const failures = verifyEvidence(result, options);
    if (failures.length > 0) {
      console.error('Cutover evidence is NOT eligible for manual cutover review:');
      for (const failure of failures) {
        console.error(`- ${failure}`);
      }
      return 1;
    }

    console.log(`Cutover evidence OK: ${resultPath}`);
    console.log(`Target: ${result.baseURL}`);
    console.log(`Ended: ${result.endedAt}`);
    console.log('Decision: eligible_for_manual_cutover_review');
    return 0;
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
  readResult,
  verifyEvidence,
};
