#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const strictPaid = process.argv.includes('--strict-paid');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function exists(rel) {
  return fs.existsSync(path.join(root, rel));
}

function has(content, needle) {
  return content.includes(needle);
}

function all(content, needles) {
  return needles.every((needle) => has(content, needle));
}

function check(name, ok, detail, failStatus = 'blocked') {
  return { name, status: ok ? 'pass' : failStatus, detail };
}

function ack(name, envVar, detail) {
  const ok = ['1', 'true', 'yes', 'done'].includes(String(process.env[envVar] || '').toLowerCase());
  return {
    name,
    status: ok ? 'pass' : (strictPaid ? 'blocked' : 'warn'),
    detail: ok ? `${envVar}=true` : `${detail} Set ${envVar}=true after this is verified.`,
  };
}

function buildChecks() {
  const pkg = JSON.parse(read('package.json'));
  const scripts = pkg.scripts || {};
  const launchDoc = exists('docs/PUBLIC_BETA_GO_NO_GO.md') ? read('docs/PUBLIC_BETA_GO_NO_GO.md') : '';
  const launchReadiness = read('docs/LAUNCH_READINESS.md');
  const prodEnv = read('docs/PRODUCTION_ENV_VALUES.md');
  const cloud = read('pages/cloud.php');
  const signup = read('pages/signup.php');
  const legal = read('pages/legal.php');
  const productionSmoke = read('tests/smoke/production-smoke.js');
  const platformHostContract = read('tests/platform-workspace-host-contract-test.php');
  const emailDoc = read('docs/CLOUDFLARE_EMAIL.md');

  const legalTypes = ['privacy', 'terms', 'dpa', 'refunds', 'security'];

  return [
    check(
      'Go/no-go checklist exists',
      exists('docs/PUBLIC_BETA_GO_NO_GO.md') &&
        all(launchDoc, ['Private Beta GO', 'Paid Public Beta GO', 'Launch decision']) &&
        all(launchDoc, ['app.foxdesk.net', 'platform.foxdesk.net', 'foxdesk.net']),
      'docs/PUBLIC_BETA_GO_NO_GO.md must be the single launch decision checklist.'
    ),
    check(
      'Launch scripts are available',
      all(Object.keys(scripts).join('\n'), ['beta:gate', 'prod:smoke', 'prod:deploy:evidence', 'launch:go-no-go']),
      'package.json must expose beta:gate, prod:smoke, prod:deploy:evidence, and launch:go-no-go.'
    ),
    check(
      'Domain roles are documented',
      all(launchReadiness, [
        'Customer app domain: `https://app.foxdesk.net`',
        'Platform admin domain: `https://platform.foxdesk.net`',
        'Public cloud page: `https://foxdesk.net`',
      ]),
      'Launch readiness must define public site, workspace app, and platform console roles.'
    ),
    check(
      'Host separation is protected by contract tests',
      all(platformHostContract, [
        'app.foxdesk.net',
        'platform.foxdesk.net',
        'foxdesk.net',
        'FOXDESKWORKSPACE',
        'FOXDESKPLATFORM',
        'FOXDESKPUBLIC',
      ]),
      'platform-workspace-host-contract-test.php must lock host routing and session separation.'
    ),
    check(
      'Public footer links legal documents',
      legalTypes.every((type) => cloud.includes(`'type' => '${type}'`)),
      'Public Cloud footer must link Privacy, Terms, DPA, Refunds, and Security.'
    ),
    check(
      'Signup links customer-facing legal terms',
      all(signup, [
        "'type' => 'terms'",
        "'type' => 'privacy'",
        "'type' => 'refunds'",
        'Start a 14-day trial. No card required.',
      ]),
      'Signup must link Terms, Privacy, Refunds, and clearly state the trial.'
    ),
    check(
      'Legal pages are operator-specific and no public subprocessors page exists',
      all(legal, [
        'Aenze s.r.o.',
        'Privacy Policy',
        'Terms of Service',
        'Data Processing Addendum',
        'Refund and Cancellation Policy',
        "'Security'",
        "if ($type === 'subprocessors')",
        'http_response_code(404)',
      ]),
      'Legal page must identify Aenze s.r.o., expose required documents, and keep subprocessors private.'
    ),
    check(
      'Production smoke covers public legal and app health',
      all(productionSmoke, [
        'page=health',
        'page=signup',
        'type=privacy',
        'type=terms',
        'type=dpa',
        'type=refunds',
        'type=security',
      ]),
      'Production smoke must verify health, signup, and all public legal pages.'
    ),
    check(
      'Deployment evidence gate is documented',
      exists('docs/DEPLOYMENT_RECOVERY_EVIDENCE.md') &&
        all(read('docs/DEPLOYMENT_RECOVERY_EVIDENCE.md'), [
          'npm run prod:deploy:evidence',
          'FOXDESK_RESTORE_EVIDENCE_PATH',
          'deployment-evidence.json',
          'foxdesk-deploy-evidence-*.tar.gz',
        ]),
      'Deployment evidence docs must require restore evidence, production smoke, and an archived evidence bundle.'
    ),
    check(
      'R2 and email production checks are documented',
      all(prodEnv, ['php bin/test-r2-storage.php', 'php bin/test-cloudflare-email.php']) &&
        all(emailDoc, ['php bin/test-cloudflare-inbound-archive.php', 'foxdesk-email-archive']),
      'Production env docs must include R2, outbound email, and inbound archive smoke checks.'
    ),
    ack(
      'Legal review acknowledged',
      'FOXDESK_ACK_LEGAL_APPROVED',
      'Have counsel/operator review Privacy, Terms, DPA, Refunds, and Security before paid public launch.'
    ),
    ack(
      'Stripe live billing flow acknowledged',
      'FOXDESK_ACK_STRIPE_LIVE_TESTED',
      'Complete docs/STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md with live or approved test-mode checkout, VAT ID, portal, failed payment, cancellation, and webhook lifecycle evidence.'
    ),
    ack(
      'Cloudflare inbound email acknowledged',
      'FOXDESK_ACK_INBOUND_EMAIL_TESTED',
      'Verify a real ticket notification reply lands back as a public ticket comment with attachment handling.'
    ),
    ack(
      'Restore and monitoring acknowledged',
      'FOXDESK_ACK_RESTORE_MONITORING_READY',
      'Verify restore evidence and monitoring for health, cron, disk, backups, webhook, R2, and email failures.'
    ),
  ];
}

function summarize(checks) {
  const blocked = checks.filter((item) => item.status === 'blocked');
  const warn = checks.filter((item) => item.status === 'warn');
  return {
    mode: strictPaid ? 'paid-public-beta' : 'private-beta',
    status: blocked.length > 0 ? 'blocked' : warn.length > 0 ? 'ready_with_warnings' : 'pass',
    passed: checks.filter((item) => item.status === 'pass').length,
    warnings: warn.length,
    blocked: blocked.length,
    checks,
  };
}

function print(result) {
  console.log(`Launch go/no-go (${result.mode}): ${result.status.toUpperCase()}`);
  console.log('');
  for (const item of result.checks) {
    const label = item.status === 'pass' ? 'PASS' : item.status === 'warn' ? 'WARN' : 'BLOCKED';
    console.log(`${label.padEnd(7)} ${item.name}`);
    console.log(`        ${item.detail}`);
  }
}

const result = summarize(buildChecks());

if (process.argv.includes('--json')) {
  console.log(JSON.stringify(result, null, 2));
} else {
  print(result);
}

process.exit(result.blocked > 0 ? 1 : 0);
