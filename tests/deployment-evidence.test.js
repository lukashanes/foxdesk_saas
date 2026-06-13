const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const {
  validateEnvironment,
  validateRestoreEvidence,
  buildDeploymentEvidence,
  writeDeploymentEvidence,
} = require('../bin/deployment-evidence.js');

const root = path.resolve(__dirname, '..');

function tempDir() {
  return fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-deploy-evidence-'));
}

function productionEnv(overrides = {}) {
  return {
    APP_URL: 'https://app.foxdesk.net',
    PROD_BASE_URL: 'https://app.foxdesk.net',
    PROD_PUBLIC_URL: 'https://foxdesk.net',
    DB_HOST: 'db',
    DB_NAME: 'foxdesk_saas',
    DB_USER: 'foxdesk',
    DB_PASS: 'strong-password',
    MAIL_PROVIDER: 'cloudflare',
    CLOUDFLARE_ACCOUNT_ID: 'account-id',
    CLOUDFLARE_EMAIL_API_TOKEN: 'cf-email-token',
    CLOUDFLARE_EMAIL_FROM: 'noreply@foxdesk.net',
    CLOUDFLARE_EMAIL_REPLY_TO: 'support@foxdesk.net',
    STORAGE_DRIVER: 'r2',
    R2_BUCKET: 'foxdesk-production',
    R2_ENDPOINT: 'https://account-id.r2.cloudflarestorage.com',
    R2_ACCESS_KEY_ID: 'r2-access',
    R2_SECRET_ACCESS_KEY: 'r2-secret',
    BILLING_ENABLED: 'true',
    STRIPE_SECRET_KEY: 'sk_live_example',
    STRIPE_WEBHOOK_SECRET: 'whsec_example',
    STRIPE_PRICE_CLOUD_BASE: 'price_cloud',
    STRIPE_PRICE_STORAGE_OVERAGE: 'price_storage',
    STRIPE_STORAGE_METER_EVENT_NAME: 'foxdesk_storage_extra_gb',
    FOXDESK_BACKUP_DIR: '/var/backups/foxdesk/db',
    FOXDESK_RESTORE_EVIDENCE_PATH: '/var/lib/foxdesk/evidence/restore-latest.json',
    FOXDESK_DEPLOY_EVIDENCE_DIR: '/var/lib/foxdesk/evidence/deployments',
    FOXDESK_MONITORING_HEALTH_URL: 'https://app.foxdesk.net/index.php?page=health',
    FOXDESK_MONITORING_ALERT_EMAIL: 'ops@aenze.com',
    ...overrides,
  };
}

function writeRestoreEvidence(dir, overrides = {}) {
  const evidence = {
    status: 'passed',
    testedAt: new Date().toISOString(),
    operator: 'FoxDesk Test',
    sourceBackup: '/var/backups/foxdesk/db/foxdesk-db-test.sql.gz',
    restoreTarget: 'restore-test-db',
    restoreMethod: 'isolated restore',
    checks: [
      { name: 'database_restore', status: 'passed' },
      { name: 'health_after_restore', status: 'passed' },
    ],
    ...overrides,
  };
  const file = path.join(dir, 'restore-latest.json');
  fs.writeFileSync(file, `${JSON.stringify(evidence, null, 2)}\n`);
  return file;
}

const passedSmoke = {
  command: 'mocked production smoke',
  status: 'passed',
  exitCode: 0,
  startedAt: new Date().toISOString(),
  endedAt: new Date().toISOString(),
  stdout: 'Production smoke OK',
  stderr: '',
};

{
  const envCheck = validateEnvironment(productionEnv());
  assert.strictEqual(envCheck.status, 'passed');
  assert.strictEqual(envCheck.summary.stripeKeyMode, 'live');
}

{
  const envCheck = validateEnvironment(productionEnv({ STRIPE_SECRET_KEY: 'sk_test_example' }));
  assert.strictEqual(envCheck.status, 'failed');
  assert(envCheck.failures.some((failure) => failure.includes('live key')));
}

{
  const dir = tempDir();
  const restorePath = writeRestoreEvidence(dir);
  const restoreCheck = validateRestoreEvidence(restorePath, 30);
  assert.strictEqual(restoreCheck.status, 'passed');

  const evidence = buildDeploymentEvidence({
    env: productionEnv({
      FOXDESK_RESTORE_EVIDENCE_PATH: restorePath,
      FOXDESK_DEPLOY_EVIDENCE_DIR: dir,
    }),
    restoreEvidencePath: restorePath,
    maxRestoreAgeDays: 30,
    skipProdSmoke: false,
  }, root, { productionSmoke: passedSmoke });
  assert.strictEqual(evidence.status, 'passed');
  assert.strictEqual(evidence.decision, 'deploy_complete_allowed');

  const output = writeDeploymentEvidence(evidence, dir);
  assert(fs.existsSync(output.jsonPath), 'deployment evidence json should be written');
  assert(fs.existsSync(output.reportPath), 'deployment evidence markdown should be written');
  assert(fs.existsSync(output.restoreCopyPath), 'restore evidence should be copied into the evidence directory');
  assert(output.archive && fs.existsSync(output.archive.archivePath), 'deployment evidence archive should be created');
  assert(fs.existsSync(output.archive.checksumPath), 'deployment evidence archive checksum should be created');
}

{
  const evidence = buildDeploymentEvidence({
    env: productionEnv(),
    restoreEvidencePath: '/missing/restore-latest.json',
    maxRestoreAgeDays: 30,
    skipProdSmoke: false,
  }, root, { productionSmoke: passedSmoke });
  assert.strictEqual(evidence.status, 'failed');
  assert(evidence.failures.some((failure) => failure.includes('Restore evidence file does not exist')));
}

{
  const dir = tempDir();
  const restorePath = writeRestoreEvidence(dir, { status: 'failed' });
  const evidence = buildDeploymentEvidence({
    env: productionEnv({
      FOXDESK_RESTORE_EVIDENCE_PATH: restorePath,
      FOXDESK_DEPLOY_EVIDENCE_DIR: dir,
    }),
    restoreEvidencePath: restorePath,
    maxRestoreAgeDays: 30,
    skipProdSmoke: true,
  }, root);
  assert.strictEqual(evidence.status, 'failed');
  assert(evidence.failures.some((failure) => failure.includes('Production smoke was skipped')));
  assert(evidence.failures.some((failure) => failure.includes('Restore evidence status')));
}

console.log('Deployment evidence contract OK');
