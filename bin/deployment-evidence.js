#!/usr/bin/env node

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { runProductionSmoke } = require('./cutover-preflight.js');

function usage() {
  return [
    'Usage: node bin/deployment-evidence.js [--output-dir=/path] [--restore-evidence=/path/restore.json] [--max-restore-age-days=30] [--skip-prod-smoke]',
    '',
    'Builds deployment evidence. A successful result requires production smoke and fresh backup restore evidence.',
  ].join('\n');
}

function parseArgs(argv) {
  const options = {
    outputDir: process.env.FOXDESK_DEPLOY_EVIDENCE_DIR || '',
    restoreEvidencePath: process.env.FOXDESK_RESTORE_EVIDENCE_PATH || '',
    maxRestoreAgeDays: Number(process.env.FOXDESK_RESTORE_EVIDENCE_MAX_AGE_DAYS || 30),
    skipProdSmoke: process.env.FOXDESK_DEPLOY_SKIP_PROD_SMOKE === '1',
    archive: process.env.FOXDESK_DEPLOY_ARCHIVE !== '0',
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
    if (arg === '--no-archive') {
      options.archive = false;
      continue;
    }
    if (arg.startsWith('--output-dir=')) {
      options.outputDir = arg.slice('--output-dir='.length);
      continue;
    }
    if (arg.startsWith('--restore-evidence=')) {
      options.restoreEvidencePath = arg.slice('--restore-evidence='.length);
      continue;
    }
    if (arg.startsWith('--max-restore-age-days=')) {
      options.maxRestoreAgeDays = Number(arg.slice('--max-restore-age-days='.length));
      continue;
    }
    throw new Error(`Unknown argument: ${arg}`);
  }

  if (!Number.isFinite(options.maxRestoreAgeDays) || options.maxRestoreAgeDays <= 0) {
    throw new Error('--max-restore-age-days must be a positive number.');
  }

  return options;
}

function normalizeEnv(env = process.env) {
  return Object.fromEntries(Object.entries(env).map(([key, value]) => [key, String(value ?? '').trim()]));
}

function isPresent(value) {
  return String(value || '').trim() !== '';
}

function isPlaceholder(value) {
  const normalized = String(value || '').trim();
  return normalized === ''
    || normalized.startsWith('replace_with_')
    || normalized.endsWith('_replace')
    || ['sk_live_replace', 'sk_test_replace', 'whsec_replace', 'price_replace'].includes(normalized);
}

function isHttpsUrl(value) {
  try {
    const url = new URL(value);
    return url.protocol === 'https:' && !['localhost', '127.0.0.1', '::1'].includes(url.hostname);
  } catch (_) {
    return false;
  }
}

function validateEnvironment(env = process.env) {
  const values = normalizeEnv(env);
  const failures = [];
  const required = [
    'APP_URL',
    'PROD_BASE_URL',
    'PROD_PUBLIC_URL',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'MAIL_PROVIDER',
    'CLOUDFLARE_ACCOUNT_ID',
    'CLOUDFLARE_EMAIL_API_TOKEN',
    'CLOUDFLARE_EMAIL_FROM',
    'CLOUDFLARE_EMAIL_REPLY_TO',
    'STORAGE_DRIVER',
    'R2_BUCKET',
    'R2_ENDPOINT',
    'R2_ACCESS_KEY_ID',
    'R2_SECRET_ACCESS_KEY',
    'BILLING_ENABLED',
    'STRIPE_SECRET_KEY',
    'STRIPE_WEBHOOK_SECRET',
    'STRIPE_PRICE_CLOUD_BASE',
    'STRIPE_PRICE_STORAGE_OVERAGE',
    'STRIPE_STORAGE_METER_EVENT_NAME',
    'FOXDESK_BACKUP_DIR',
    'FOXDESK_RESTORE_EVIDENCE_PATH',
    'FOXDESK_DEPLOY_EVIDENCE_DIR',
    'FOXDESK_MONITORING_HEALTH_URL',
    'FOXDESK_MONITORING_ALERT_EMAIL',
  ];

  for (const key of required) {
    if (isPlaceholder(values[key])) {
      failures.push(`Missing or placeholder production value: ${key}`);
    }
  }

  for (const key of ['APP_URL', 'PROD_BASE_URL', 'PROD_PUBLIC_URL', 'FOXDESK_MONITORING_HEALTH_URL']) {
    if (isPresent(values[key]) && !isHttpsUrl(values[key])) {
      failures.push(`${key} must be a non-local https URL.`);
    }
  }

  if (values.STORAGE_DRIVER !== 'r2') {
    failures.push('STORAGE_DRIVER must be r2 for production deployment evidence.');
  }

  if (values.MAIL_PROVIDER !== 'cloudflare') {
    failures.push('MAIL_PROVIDER must be cloudflare for production deployment evidence.');
  }

  if (values.BILLING_ENABLED !== 'true') {
    failures.push('BILLING_ENABLED must be true for paid production deployment evidence.');
  }

  if (isPresent(values.STRIPE_SECRET_KEY) && !values.STRIPE_SECRET_KEY.startsWith('sk_live_')) {
    failures.push('STRIPE_SECRET_KEY must be a live key for paid production deployment evidence.');
  }

  if (isPresent(values.STRIPE_WEBHOOK_SECRET) && !values.STRIPE_WEBHOOK_SECRET.startsWith('whsec_')) {
    failures.push('STRIPE_WEBHOOK_SECRET must start with whsec_.');
  }

  if (isPresent(values.R2_ENDPOINT) && !/\.r2\.cloudflarestorage\.com\/?$/.test(values.R2_ENDPOINT)) {
    failures.push('R2_ENDPOINT must use the Cloudflare R2 S3 endpoint.');
  }

  for (const key of ['FOXDESK_BACKUP_DIR', 'FOXDESK_DEPLOY_EVIDENCE_DIR']) {
    if (isPresent(values[key]) && !path.isAbsolute(values[key])) {
      failures.push(`${key} must be an absolute path.`);
    }
  }

  if (isPresent(values.FOXDESK_RESTORE_EVIDENCE_PATH) && !path.isAbsolute(values.FOXDESK_RESTORE_EVIDENCE_PATH)) {
    failures.push('FOXDESK_RESTORE_EVIDENCE_PATH must be an absolute JSON file path.');
  }

  return {
    status: failures.length === 0 ? 'passed' : 'failed',
    failures,
    summary: {
      appUrl: values.APP_URL || '',
      publicUrl: values.PROD_PUBLIC_URL || '',
      storageDriver: values.STORAGE_DRIVER || '',
      r2Bucket: values.R2_BUCKET || '',
      mailProvider: values.MAIL_PROVIDER || '',
      billingEnabled: values.BILLING_ENABLED || '',
      stripeKeyMode: values.STRIPE_SECRET_KEY?.startsWith('sk_live_') ? 'live' : values.STRIPE_SECRET_KEY?.startsWith('sk_test_') ? 'test' : 'missing_or_unknown',
      backupDirConfigured: isPresent(values.FOXDESK_BACKUP_DIR),
      restoreEvidencePathConfigured: isPresent(values.FOXDESK_RESTORE_EVIDENCE_PATH),
      deployEvidenceDirConfigured: isPresent(values.FOXDESK_DEPLOY_EVIDENCE_DIR),
      monitoringHealthUrlConfigured: isPresent(values.FOXDESK_MONITORING_HEALTH_URL),
      monitoringAlertEmailConfigured: isPresent(values.FOXDESK_MONITORING_ALERT_EMAIL),
    },
  };
}

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function validateRestoreEvidence(filePath, maxAgeDays, now = new Date()) {
  const failures = [];
  if (!filePath) {
    return {
      status: 'failed',
      path: '',
      failures: ['Missing restore evidence path. Set FOXDESK_RESTORE_EVIDENCE_PATH.'],
      summary: null,
    };
  }

  if (!fs.existsSync(filePath)) {
    return {
      status: 'failed',
      path: filePath,
      failures: [`Restore evidence file does not exist: ${filePath}`],
      summary: null,
    };
  }

  let evidence;
  try {
    evidence = readJson(filePath);
  } catch (error) {
    return {
      status: 'failed',
      path: filePath,
      failures: [`Restore evidence JSON is invalid: ${error.message}`],
      summary: null,
    };
  }

  const testedAt = new Date(evidence.testedAt || evidence.completedAt || '');
  if (evidence.status !== 'passed') {
    failures.push(`Restore evidence status must be "passed"; got "${evidence.status || ''}".`);
  }
  if (!Number.isFinite(testedAt.getTime())) {
    failures.push('Restore evidence must include testedAt as an ISO date.');
  } else {
    const ageMs = now.getTime() - testedAt.getTime();
    if (ageMs < 0) {
      failures.push('Restore evidence testedAt cannot be in the future.');
    }
    if (ageMs > maxAgeDays * 24 * 60 * 60 * 1000) {
      failures.push(`Restore evidence is older than ${maxAgeDays} days.`);
    }
  }
  if (!isPresent(evidence.sourceBackup)) {
    failures.push('Restore evidence must include sourceBackup.');
  }
  if (!isPresent(evidence.restoreTarget)) {
    failures.push('Restore evidence must include restoreTarget.');
  }
  if (!Array.isArray(evidence.checks) || evidence.checks.length === 0) {
    failures.push('Restore evidence must include at least one check.');
  } else {
    const failedChecks = evidence.checks.filter((check) => !['pass', 'passed'].includes(String(check.status || '').toLowerCase()));
    if (failedChecks.length > 0) {
      failures.push(`Restore evidence has failing checks: ${failedChecks.map((check) => check.name || check.label || 'unnamed').join(', ')}`);
    }
  }

  return {
    status: failures.length === 0 ? 'passed' : 'failed',
    path: filePath,
    failures,
    summary: {
      testedAt: evidence.testedAt || evidence.completedAt || '',
      operator: evidence.operator || '',
      sourceBackup: evidence.sourceBackup || '',
      restoreTarget: evidence.restoreTarget || '',
      restoreMethod: evidence.restoreMethod || '',
      checkCount: Array.isArray(evidence.checks) ? evidence.checks.length : 0,
    },
    raw: evidence,
  };
}

function skippedSmoke() {
  const now = new Date().toISOString();
  return {
    command: 'skipped',
    status: 'skipped',
    exitCode: null,
    startedAt: now,
    endedAt: now,
    stdout: '',
    stderr: '',
  };
}

function buildDeploymentEvidence(options, rootDir, hooks = {}) {
  const generatedAt = new Date();
  const envCheck = validateEnvironment(options.env || process.env);
  const restoreEvidence = hooks.restoreEvidence || validateRestoreEvidence(
    options.restoreEvidencePath,
    options.maxRestoreAgeDays,
    generatedAt
  );
  const productionSmoke = hooks.productionSmoke || (options.skipProdSmoke ? skippedSmoke() : runProductionSmoke(rootDir));
  const failures = [];

  for (const failure of envCheck.failures) {
    failures.push(`Environment: ${failure}`);
  }
  for (const failure of restoreEvidence.failures || []) {
    failures.push(`Restore evidence: ${failure}`);
  }
  if (productionSmoke.status !== 'passed') {
    failures.push(options.skipProdSmoke
      ? 'Production smoke was skipped.'
      : `Production smoke failed with exit code ${productionSmoke.exitCode}.`);
  }

  return {
    status: failures.length === 0 ? 'passed' : 'failed',
    decision: failures.length === 0 ? 'deploy_complete_allowed' : 'deploy_blocked',
    generatedAt: generatedAt.toISOString(),
    environment: envCheck,
    restoreEvidence: {
      status: restoreEvidence.status,
      path: restoreEvidence.path,
      summary: restoreEvidence.summary,
      failures: restoreEvidence.failures || [],
    },
    productionSmoke,
    failures,
    nextSteps: failures.length === 0
      ? [
          'Store the deployment evidence archive outside the app server.',
          'Keep health, cron, backup, webhook, R2, and email monitoring enabled.',
          'Keep the restore evidence current after every backup or schema-sensitive release.',
        ]
      : [
          'Do not mark the production deploy complete.',
          'Fix the failed environment, restore, or smoke condition.',
          'Rerun deployment evidence after the fix.',
        ],
  };
}

function renderMarkdown(evidence) {
  const lines = [
    '# FoxDesk Production Deployment Evidence',
    '',
    `Status: ${evidence.status}`,
    `Decision: ${evidence.decision}`,
    `Generated: ${evidence.generatedAt}`,
    `App URL: ${evidence.environment.summary.appUrl || ''}`,
    `Public URL: ${evidence.environment.summary.publicUrl || ''}`,
    `Production smoke: ${evidence.productionSmoke.status}`,
    `Restore evidence: ${evidence.restoreEvidence.status}`,
    '',
    '## Verdict',
    '',
  ];

  if (evidence.decision === 'deploy_complete_allowed') {
    lines.push('The deployment evidence passed. The deploy can be marked complete after the operator stores the archive.');
  } else {
    lines.push('The deployment evidence failed. Do not mark the deploy complete.');
  }

  if (evidence.failures.length > 0) {
    lines.push('', '## Failures', '');
    for (const failure of evidence.failures) {
      lines.push(`- ${failure}`);
    }
  }

  lines.push('', '## Environment Summary', '');
  for (const [key, value] of Object.entries(evidence.environment.summary)) {
    lines.push(`- ${key}: ${value}`);
  }

  lines.push('', '## Restore Evidence', '');
  if (evidence.restoreEvidence.summary) {
    for (const [key, value] of Object.entries(evidence.restoreEvidence.summary)) {
      lines.push(`- ${key}: ${value}`);
    }
  } else {
    lines.push('- unavailable');
  }

  lines.push('', '## Next Steps', '');
  for (const step of evidence.nextSteps) {
    lines.push(`- ${step}`);
  }

  if (evidence.productionSmoke.stdout || evidence.productionSmoke.stderr) {
    lines.push('', '## Production Smoke Output', '', '```text');
    if (evidence.productionSmoke.stdout) {
      lines.push(evidence.productionSmoke.stdout);
    }
    if (evidence.productionSmoke.stderr) {
      lines.push(evidence.productionSmoke.stderr);
    }
    lines.push('```');
  }

  return `${lines.join('\n')}\n`;
}

function sha256File(filePath) {
  const hash = crypto.createHash('sha256');
  hash.update(fs.readFileSync(filePath));
  return hash.digest('hex');
}

function archiveFiles(outputDir, fileNames, generatedAt) {
  const safeStamp = generatedAt.replace(/[:.]/g, '-');
  const archiveName = `foxdesk-deploy-evidence-${safeStamp}.tar.gz`;
  const archivePath = path.join(outputDir, archiveName);
  const tar = spawnSync('tar', ['-czf', archivePath, '-C', outputDir, ...fileNames], {
    encoding: 'utf8',
  });
  if (tar.status !== 0) {
    throw new Error(`Unable to create deployment evidence archive: ${tar.stderr || tar.stdout || 'tar failed'}`);
  }
  const checksum = sha256File(archivePath);
  const checksumPath = `${archivePath}.sha256`;
  fs.writeFileSync(checksumPath, `${checksum}  ${archiveName}\n`);
  return { archivePath, checksumPath, sha256: checksum };
}

function writeDeploymentEvidence(evidence, outputDir, options = {}) {
  fs.mkdirSync(outputDir, { recursive: true });
  const jsonPath = path.join(outputDir, 'deployment-evidence.json');
  const reportPath = path.join(outputDir, 'deployment-evidence.md');
  fs.writeFileSync(jsonPath, `${JSON.stringify(evidence, null, 2)}\n`);
  fs.writeFileSync(reportPath, renderMarkdown(evidence));

  const fileNames = [path.basename(jsonPath), path.basename(reportPath)];
  let restoreCopyPath = '';
  if (evidence.restoreEvidence.path && fs.existsSync(evidence.restoreEvidence.path)) {
    restoreCopyPath = path.join(outputDir, 'backup-restore-evidence.json');
    fs.copyFileSync(evidence.restoreEvidence.path, restoreCopyPath);
    fileNames.push(path.basename(restoreCopyPath));
  }

  const archive = options.archive === false
    ? null
    : archiveFiles(outputDir, fileNames, evidence.generatedAt);

  return { jsonPath, reportPath, restoreCopyPath, archive };
}

function main() {
  try {
    const options = parseArgs(process.argv.slice(2));
    if (options.help) {
      console.log(usage());
      return 0;
    }
    if (!options.outputDir) {
      throw new Error('Missing output directory. Set FOXDESK_DEPLOY_EVIDENCE_DIR or pass --output-dir.');
    }

    const rootDir = path.dirname(__dirname);
    const outputDir = path.resolve(options.outputDir);
    const evidence = buildDeploymentEvidence(options, rootDir);
    const output = writeDeploymentEvidence(evidence, outputDir, { archive: options.archive });

    console.log(JSON.stringify({ ...evidence, output }, null, 2));
    return evidence.status === 'passed' ? 0 : 1;
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
  validateEnvironment,
  validateRestoreEvidence,
  buildDeploymentEvidence,
  renderMarkdown,
  writeDeploymentEvidence,
};
