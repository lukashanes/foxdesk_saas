<?php

$root = dirname(__DIR__);

$package = file_get_contents($root . '/package.json');
$env = file_get_contents($root . '/.env.production.example');
$preflight = file_get_contents($root . '/deploy/hetzner/preflight.sh');
$deploy = file_get_contents($root . '/deploy/hetzner/deploy.sh');
$bootstrap = file_get_contents($root . '/deploy/hetzner/bootstrap.sh');
$setup = file_get_contents($root . '/deploy/hetzner/setup-server.sh');
$evidence = file_get_contents($root . '/bin/deployment-evidence.js');
$doc = file_get_contents($root . '/docs/DEPLOYMENT_RECOVERY_EVIDENCE.md');
$template = file_get_contents($root . '/docs/operations/backup-restore-evidence.template.json');
$plan = file_get_contents($root . '/docs/TECHNICAL_DEBT_PLAN.md');

if ($package === false || $env === false || $preflight === false || $deploy === false || $bootstrap === false || $setup === false || $evidence === false || $doc === false || $template === false || $plan === false) {
    fwrite(STDERR, "Unable to read deployment recovery files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($package, '"prod:deploy:evidence"'), 'package.json must expose prod:deploy:evidence.');

foreach ([
    'PROD_BASE_URL',
    'PROD_PUBLIC_URL',
    'FOXDESK_BACKUP_DIR',
    'FOXDESK_RESTORE_EVIDENCE_PATH',
    'FOXDESK_DEPLOY_EVIDENCE_DIR',
    'FOXDESK_MONITORING_HEALTH_URL',
    'FOXDESK_MONITORING_ALERT_EMAIL',
] as $key) {
    $assert(str_contains($env, $key . '='), ".env.production.example must include {$key}.");
    $assert(str_contains($preflight, $key), "Hetzner preflight must validate {$key}.");
    $assert(str_contains($evidence, $key), "Deployment evidence gate must validate {$key}.");
}

$assert(str_contains($preflight, 'require_https_env'), 'Preflight must reject local/non-https production URLs.');
$assert(str_contains($preflight, 'require_absolute_env'), 'Preflight must require absolute backup/evidence paths.');
$assert(str_contains($preflight, 'STRIPE_SECRET_KEY must be a live key for paid production'), 'Preflight must reject test Stripe keys for paid production.');
$assert(str_contains($preflight, 'MAIL_PROVIDER must be cloudflare for SaaS production'), 'Preflight must require Cloudflare mail for SaaS production.');
$assert(str_contains($preflight, 'Missing node_modules. Run npm ci before production deploy.'), 'Preflight must require npm ci before deployment evidence.');
$assert(str_contains($preflight, 'npx playwright install --with-deps chromium'), 'Preflight must tell operators how to install Playwright Chromium.');

$assert(str_contains($bootstrap, 'nodejs npm'), 'Bootstrap must install Node/NPM for production evidence.');
$assert(str_contains($bootstrap, 'npx playwright install --with-deps chromium'), 'Bootstrap next steps must install Playwright Chromium.');
$assert(str_contains($setup, 'nodejs npm'), 'Setup script must install Node/NPM for production evidence.');

$assert(str_contains($deploy, 'npm run prod:deploy:evidence'), 'Deploy script must run deployment evidence before completion.');
$assert(str_contains($deploy, 'FOXDESK_DEPLOY_SKIP_EVIDENCE'), 'Deploy script must explicitly refuse skipped evidence.');

$assert(str_contains($evidence, 'deploy_complete_allowed'), 'Deployment evidence gate must have a deploy completion decision.');
$assert(str_contains($evidence, 'Production smoke was skipped'), 'Deployment evidence gate must fail skipped production smoke.');
$assert(str_contains($evidence, 'Restore evidence file does not exist'), 'Deployment evidence gate must fail missing restore evidence.');
$assert(str_contains($evidence, 'foxdesk-deploy-evidence-'), 'Deployment evidence gate must create a named evidence archive.');
$assert(str_contains($evidence, '.sha256'), 'Deployment evidence gate must create a checksum file.');

$assert(str_contains($doc, 'npm run prod:deploy:evidence'), 'Deployment recovery doc must explain the evidence gate.');
$assert(str_contains($doc, 'deployment-evidence.json'), 'Deployment recovery doc must mention deployment-evidence.json.');
$assert(str_contains($doc, 'foxdesk-deploy-evidence-*.tar.gz'), 'Deployment recovery doc must mention the archive.');
$assert(str_contains($template, '"status": "passed"'), 'Restore evidence template must start from a passed example.');
$assert(str_contains($template, '"sourceBackup"'), 'Restore evidence template must include sourceBackup.');
$assert(str_contains($template, '"restoreTarget"'), 'Restore evidence template must include restoreTarget.');

$assert(str_contains($plan, 'Milestone 8 - Deployment And Recovery Evidence'), 'Technical debt plan must keep milestone 8.');

echo "Deployment recovery contract OK\n";
