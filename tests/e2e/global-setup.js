const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('@playwright/test');
const {
  repoRoot,
  tmpDir,
  network,
  dbContainer,
  webContainer,
  phpImage,
  port,
  baseURL,
  admin
} = require('./env');

function run(command, args, options = {}) {
  return execFileSync(command, args, {
    cwd: options.cwd || repoRoot,
    encoding: 'utf8',
    stdio: options.stdio || ['ignore', 'pipe', 'pipe'],
    input: options.input
  });
}

function docker(args, options = {}) {
  return run('docker', args, options);
}

function cleanupExisting() {
  try {
    docker(['rm', '-f', webContainer, dbContainer], { stdio: 'ignore' });
  } catch (_) {}
  try {
    docker(['network', 'rm', network], { stdio: 'ignore' });
  } catch (_) {}
}

function buildPhpImageIfMissing() {
  try {
    docker(['image', 'inspect', phpImage], { stdio: 'ignore' });
    return;
  } catch (_) {}

  const dockerfile = path.join('/tmp', `${phpImage}.Dockerfile`);
  fs.writeFileSync(
    dockerfile,
    [
      'FROM php:8.2-apache',
      'RUN apt-get update \\',
      ' && apt-get install -y --no-install-recommends libzip-dev \\',
      ' && docker-php-ext-install pdo_mysql mysqli zip \\',
      ' && rm -rf /var/lib/apt/lists/*',
      ''
    ].join('\n')
  );
  docker(['build', '-t', phpImage, '-f', dockerfile, '/tmp'], { stdio: 'inherit' });
}

function waitForDb() {
  const started = Date.now();
  while (Date.now() - started < 60_000) {
    try {
      docker([
        'exec',
        dbContainer,
        'mariadb-admin',
        'ping',
        '-h',
        '127.0.0.1',
        '-uroot',
        '-prootpass',
        '--silent'
      ], { stdio: 'ignore' });
      return;
    } catch (_) {
      Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 1000);
    }
  }
  throw new Error('MariaDB did not become ready in time.');
}

async function waitForWeb() {
  const started = Date.now();
  while (Date.now() - started < 60_000) {
    try {
      const response = await fetch(`${baseURL}/index.php?page=login`);
      if (response.ok) return;
    } catch (_) {}
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  throw new Error('FoxDesk web container did not become ready in time.');
}

function sqlString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function writeTestConfig() {
  const config = `<?php
define('DB_HOST', '${dbContainer}');
define('DB_PORT', '3306');
define('DB_NAME', 'foxdesk');
define('DB_USER', 'foxdesk');
define('DB_PASS', 'foxpass');
define('SECRET_KEY', 'e2e_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
define('APP_NAME', 'FoxDesk E2E');
define('APP_URL', '${baseURL}');
define('APP_DEBUG', true);
define('BILLING_ENABLED', false);
define('STRIPE_SECRET_KEY', 'sk_test_e2e');
define('STRIPE_WEBHOOK_SECRET', 'whsec_test');
define('STRIPE_PRICE_CLOUD_BASE', 'price_cloud_e2e');
define('STRIPE_PRICE_STORAGE_OVERAGE', 'price_storage_e2e');
define('STRIPE_STORAGE_METER_EVENT_NAME', 'foxdesk_storage_extra_gb');
define('BILLING_CURRENCY', 'EUR');
define('BILLING_CLOUD_BASE_PRICE_CENTS', 990);
define('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 190);
define('BILLING_INCLUDED_STORAGE_BYTES', 1073741824);
define('BILLING_TRIAL_DAYS', 14);
define('BILLING_TRIAL_GRACE_DAYS', 2);
define('BILLING_PAST_DUE_GRACE_DAYS', 2);
define('STRIPE_SUCCESS_URL', APP_URL . '/index.php?page=billing&checkout=success');
define('STRIPE_CANCEL_URL', APP_URL . '/index.php?page=billing&checkout=cancelled');
define('MAIL_PROVIDER', 'log');
define('IMAP_ENABLED', false);
define('STORAGE_DRIVER', 'local');
define('UPLOAD_DIR', 'uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
date_default_timezone_set('Europe/Prague');
`;
  fs.writeFileSync(path.join(tmpDir, 'config.php'), config);
}

function importSchema() {
  docker([
    'exec',
    '-i',
    dbContainer,
    'mariadb',
    '-ufoxdesk',
    '-pfoxpass',
    'foxdesk'
  ], {
    input: fs.readFileSync(path.join(tmpDir, 'includes/schema.sql')),
    stdio: ['pipe', 'pipe', 'pipe']
  });
}

function dbQuery(sql) {
  return docker([
    'exec',
    dbContainer,
    'mariadb',
    '-ufoxdesk',
    '-pfoxpass',
    '--batch',
    '--raw',
    'foxdesk',
    '-e',
    sql
  ]);
}

function php(code) {
  return docker(['exec', webContainer, 'php', '-r', code]);
}

function seedBaseline() {
  const passwordHash = php(`echo password_hash(${JSON.stringify(admin.password)}, PASSWORD_DEFAULT);`).trim();
  const tenantUuid = '00000000-0000-4000-8000-000000000001';

  dbQuery(`
    INSERT INTO tenants (uuid, name, slug, status, subscription_status, billing_email, trial_ends_at, created_at)
    VALUES (${sqlString(tenantUuid)}, 'Default Workspace', 'default', 'active', 'active', ${sqlString(admin.email)}, DATE_ADD(NOW(), INTERVAL 14 DAY), NOW())
    ON DUPLICATE KEY UPDATE
      name = VALUES(name),
      status = VALUES(status),
      subscription_status = VALUES(subscription_status),
      billing_email = VALUES(billing_email);
  `);

  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, is_platform_admin, is_active, language, created_at)
    VALUES (
      (SELECT id FROM tenants WHERE slug = 'default' LIMIT 1),
      ${sqlString(admin.email)},
      ${sqlString(passwordHash)},
      'Admin',
      'E2E',
      'admin',
      1,
      1,
      'en',
      NOW()
    )
    ON DUPLICATE KEY UPDATE
      tenant_id = VALUES(tenant_id),
      password = VALUES(password),
      first_name = VALUES(first_name),
      last_name = VALUES(last_name),
      role = VALUES(role),
      is_platform_admin = VALUES(is_platform_admin),
      is_active = VALUES(is_active),
      deleted_at = NULL;
  `);

  dbQuery(`
    UPDATE tenants
    SET owner_user_id = (SELECT id FROM users WHERE email = ${sqlString(admin.email)} LIMIT 1)
    WHERE slug = 'default';
  `);

  dbQuery(`
    INSERT INTO statuses (name, slug, color, sort_order, is_default, is_closed) VALUES
      ('New', 'new', '#0a84ff', 1, 1, 0),
      ('Testing', 'testing', '#5e5ce6', 2, 0, 0),
      ('Waiting for customer', 'waiting', '#ff9f0a', 3, 0, 0),
      ('In progress', 'processing', '#30b0c7', 4, 0, 0),
      ('Done', 'done', '#34c759', 5, 0, 1),
      ('Cancelled', 'cancelled', '#ff3b30', 6, 0, 1)
    ON DUPLICATE KEY UPDATE
      color = VALUES(color),
      sort_order = VALUES(sort_order),
      is_default = VALUES(is_default),
      is_closed = VALUES(is_closed);
  `);

  dbQuery(`
    INSERT INTO priorities (name, slug, color, icon, sort_order, is_default) VALUES
      ('Low', 'low', '#34c759', 'fa-arrow-down', 1, 0),
      ('Medium', 'medium', '#0a84ff', 'fa-minus', 2, 1),
      ('High', 'high', '#ff9f0a', 'fa-arrow-up', 3, 0),
      ('Urgent', 'urgent', '#ff3b30', 'fa-exclamation', 4, 0)
    ON DUPLICATE KEY UPDATE
      color = VALUES(color),
      icon = VALUES(icon),
      sort_order = VALUES(sort_order),
      is_default = VALUES(is_default);
  `);

  dbQuery(`
    INSERT INTO ticket_types (name, slug, icon, color, sort_order, is_default, is_active) VALUES
      ('General', 'general', 'fa-file-alt', '#0a84ff', 1, 1, 1),
      ('Quote request', 'quote', 'fa-coins', '#ff9f0a', 2, 0, 1),
      ('Inquiry', 'inquiry', 'fa-question-circle', '#5e5ce6', 3, 0, 1),
      ('Bug report', 'bug', 'fa-bug', '#ff3b30', 4, 0, 1)
    ON DUPLICATE KEY UPDATE
      icon = VALUES(icon),
      color = VALUES(color),
      sort_order = VALUES(sort_order),
      is_default = VALUES(is_default),
      is_active = VALUES(is_active);
  `);

  dbQuery(`
    INSERT INTO settings (setting_key, setting_value) VALUES
      ('app_name', 'FoxDesk E2E'),
      ('app_language', 'en'),
      ('time_format', '24'),
      ('email_notifications_enabled', '0'),
      ('imap_enabled', '0'),
      ('pseudo_cron_enabled', '1'),
      ('pseudo_cron_last_email', '0'),
      ('pseudo_cron_email_inline_lock', '0')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
  `);

  dbQuery(`
    INSERT INTO email_templates (template_key, language, subject, body, is_active) VALUES
      ('status_change', 'en', 'Status changed for ticket #{ticket_id}: {ticket_title}', 'Hello,\\n\\nThe status of your ticket "{ticket_title}" has changed.\\n\\nPrevious status: {old_status}\\nNew status: {new_status}\\n\\nView ticket: {ticket_url}\\n\\nRegards,\\n{app_name}', 1),
      ('new_comment', 'en', 'New comment on ticket #{ticket_id}: {ticket_title}', 'Hello,\\n\\nA new comment was added to your ticket "{ticket_title}".\\n\\nFrom: {commenter_name}\\nTime spent: {time_spent}\\nAttachments: {attachments}\\n\\n---\\n{comment_text}\\n---\\n\\nView comment: {comment_url}\\n\\nRegards,\\n{app_name}', 1),
      ('new_ticket', 'en', 'New ticket #{ticket_id}: {ticket_title}', 'Hello,\\n\\nA new ticket has been created.\\n\\nSubject: {ticket_title}\\nType: {ticket_type}\\nPriority: {priority}\\nFrom: {user_name} ({user_email})\\n\\nView ticket: {ticket_url}\\n\\nRegards,\\n{app_name}', 1),
      ('password_reset', 'en', 'Password reset', 'Hello,\\n\\nYou requested a password reset. Click the link below:\\n{reset_link}\\n\\nThis link is valid for 1 hour.\\n\\nIf you did not request a password reset, please ignore this email.\\n\\nRegards,\\n{app_name}', 1)
    ON DUPLICATE KEY UPDATE
      subject = VALUES(subject),
      body = VALUES(body),
      is_active = VALUES(is_active);
  `);
}

function updateCookieJar(cookieJar, response) {
  const setCookie = response.headers.getSetCookie ? response.headers.getSetCookie() : [];
  for (const cookie of setCookie) {
    const pair = cookie.split(';')[0];
    const eq = pair.indexOf('=');
    if (eq > 0) {
      cookieJar[pair.slice(0, eq)] = pair.slice(eq + 1);
    }
  }
}

function cookieHeader(cookieJar) {
  return Object.entries(cookieJar).map(([key, value]) => `${key}=${value}`).join('; ');
}

function extractCsrf(html) {
  const match = html.match(/name="csrf_token" value="([^"]+)"/);
  if (!match) {
    throw new Error('Installer CSRF token not found.');
  }
  return match[1];
}

async function request(cookieJar, urlPath, options = {}) {
  const response = await fetch(`${baseURL}${urlPath}`, {
    redirect: 'manual',
    ...options,
    headers: {
      ...(options.headers || {}),
      Cookie: cookieHeader(cookieJar)
    }
  });
  updateCookieJar(cookieJar, response);
  return response;
}

async function installApp() {
  const cookies = {};

  let response = await request(cookies, '/install.php');
  let html = await response.text();
  let csrf = extractCsrf(html);

  response = await request(cookies, '/install.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      csrf_token: csrf,
      db_host: dbContainer,
      db_port: '3306',
      db_name: 'foxdesk',
      db_user: 'foxdesk',
      db_pass: 'foxpass'
    })
  });
  if (response.status !== 302) {
    throw new Error(`Installer database step failed with HTTP ${response.status}: ${await response.text()}`);
  }

  response = await request(cookies, '/install.php?step=2');
  html = await response.text();
  csrf = extractCsrf(html);

  response = await request(cookies, '/install.php?step=2', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      csrf_token: csrf,
      app_name: 'FoxDesk E2E',
      admin_email: admin.email,
      admin_name: 'Admin',
      admin_surname: 'E2E',
      admin_pass: admin.password,
      admin_pass2: admin.password
    })
  });
  if (response.status !== 302) {
    throw new Error(`Installer admin step failed with HTTP ${response.status}: ${await response.text()}`);
  }
}

async function saveAdminStorageState() {
  const authDir = path.join(__dirname, '.auth');
  fs.mkdirSync(authDir, { recursive: true });

  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL });
  await page.goto('/index.php?page=login');
  await page.locator('input[name="email"]').fill(admin.email);
  await page.locator('input[name="password"]').fill(admin.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=dashboard|dashboard|page=platform/);
  await page.context().storageState({ path: path.join(authDir, 'admin.json') });
  await browser.close();
}

module.exports = async function globalSetup() {
  cleanupExisting();
  fs.rmSync(tmpDir, { recursive: true, force: true });
  run('rsync', [
    '-a',
    '--exclude', '.git',
    '--exclude', '.env',
    '--exclude', '.env.production',
    '--exclude', 'deploy/hetzner/.env.prod',
    '--exclude', 'config.php',
    '--exclude', 'uploads',
    '--exclude', 'storage',
    '--exclude', 'backups',
    '--exclude', 'node_modules',
    '--exclude', 'test-results',
    '--exclude', 'playwright-report',
    '--exclude', 'tests/e2e/.auth',
    `${repoRoot}/`,
    `${tmpDir}/`
  ]);
  writeTestConfig();

  buildPhpImageIfMissing();
  docker(['network', 'create', network], { stdio: 'ignore' });
  docker([
    'run',
    '-d',
    '--name',
    dbContainer,
    '--network',
    network,
    '-e',
    'MARIADB_ROOT_PASSWORD=rootpass',
    '-e',
    'MARIADB_DATABASE=foxdesk',
    '-e',
    'MARIADB_USER=foxdesk',
    '-e',
    'MARIADB_PASSWORD=foxpass',
    'mariadb:11'
  ], { stdio: 'ignore' });
  docker([
    'run',
    '-d',
    '--name',
    webContainer,
    '--network',
    network,
    '-e',
    'STRIPE_WEBHOOK_SECRET=whsec_test',
    '-p',
    `${port}:80`,
    '-v',
    `${tmpDir}:/var/www/html`,
    phpImage
  ], { stdio: 'ignore' });

  waitForDb();
  importSchema();
  seedBaseline();
  await waitForWeb();
  await saveAdminStorageState();
};
