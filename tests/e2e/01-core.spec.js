const fs = require('fs');
const os = require('os');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { dbQuery, php, login } = require('./helpers');
const { baseURL } = require('./env');

function sqlString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function rowObject(output) {
  const lines = output.trim().split('\n');
  const headers = lines[0].split('\t');
  const values = lines[1].split('\t');
  return Object.fromEntries(headers.map((header, index) => [header, values[index]]));
}

test('admin can log in and see dashboard', async ({ page }) => {
  await login(page);
  await expect(page).toHaveURL(/page=dashboard|dashboard|page=platform/);
  await page.goto('/index.php?page=dashboard');
  await expect(page.locator('body')).toContainText('Dashboard');
});

test('admin can collapse the sidebar and keep more workspace width', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page);
  await page.goto('/index.php?page=dashboard');

  const before = await page.locator('#main-content').evaluate(el => parseFloat(getComputedStyle(el).marginLeft));
  await page.locator('#sidebar-collapse-btn').click();
  await expect(page.locator('body')).toHaveClass(/sidebar-compact/);
  await page.waitForFunction(previous => {
    const main = document.getElementById('main-content');
    return main && parseFloat(getComputedStyle(main).marginLeft) < previous;
  }, before);
  const after = await page.locator('#main-content').evaluate(el => parseFloat(getComputedStyle(el).marginLeft));
  expect(after).toBeLessThan(before);

  await page.reload();
  await expect(page.locator('body')).toHaveClass(/sidebar-compact/);

  await page.locator('#sidebar-collapse-btn').click();
  const isCompact = await page.locator('body').evaluate(body => body.classList.contains('sidebar-compact'));
  expect(isCompact).toBe(false);
});

test('admin can create a ticket, upload an attachment, and download it', async ({ page }) => {
  const attachmentPath = path.join(os.tmpdir(), 'foxdesk-e2e-attachment.txt');
  fs.writeFileSync(attachmentPath, 'hello from foxdesk e2e\n');

  await login(page);
  await page.goto('/index.php?page=new-ticket');
  await expect(page.locator('body')).toContainText('New ticket');

  await page.locator('input[name="title"]').fill('E2E ticket with attachment');
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Created by Playwright E2E.</p>';
  });
  await page.locator('#file-input').setInputFiles(attachmentPath);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);

  await expect(page.locator('body')).toContainText('E2E ticket with attachment');
  await expect(page.locator('body')).toContainText('Attachments');
  await expect(page.locator('body')).toContainText('foxdesk-e2e-attachment.txt');

  const attachmentHref = await page.locator('a[href*="attachment.php"]', { hasText: 'foxdesk-e2e-attachment.txt' }).first().getAttribute('href');
  expect(attachmentHref).toBeTruthy();
  const attachmentResponse = await page.request.get(attachmentHref);
  expect(attachmentResponse.ok()).toBeTruthy();
  expect(await attachmentResponse.text()).toContain('hello from foxdesk e2e');
});

test('logout and login flow works', async ({ browser }) => {
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await login(page);
  await page.goto('/index.php?page=logout');
  await expect(page).toHaveURL(/page=login/);
  await login(page);
  await page.goto('/index.php?page=dashboard');
  await expect(page.locator('body')).toContainText('Dashboard');
  await context.close();
});

test('page load triggers throttled pseudo-cron email fallback', async ({ page }) => {
  dbQuery(`
    INSERT INTO settings (setting_key, setting_value) VALUES
      ('pseudo_cron_enabled', '1'),
      ('pseudo_cron_last_email', '0'),
      ('pseudo_cron_email_inline_lock', '0'),
      ('imap_enabled', '0')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
  `);

  await page.goto('/index.php?page=login');

  const output = dbQuery("SELECT setting_value FROM settings WHERE setting_key = 'pseudo_cron_last_email' LIMIT 1;");
  const lastRun = Number(output.trim().split('\n').pop());
  expect(lastRun).toBeGreaterThan(0);
});

test('new ticket does not carry over previous company or assignee selection', async ({ page }) => {
  const stamp = Date.now();
  const orgName = `E2E Carryover Org ${stamp}`;
  const agentEmail = `carryover.agent.${stamp}@example.test`;
  const tenant = rowObject(dbQuery("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1"));
  const tenantId = Number(tenant.id);

  dbQuery(`
    INSERT INTO organizations (tenant_id, name, is_active, created_at)
    VALUES (${tenantId}, ${sqlString(orgName)}, 1, NOW());
  `);
  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, is_active, created_at)
    VALUES (
      ${tenantId},
      ${sqlString(agentEmail)},
      '$2y$10$abcdefghijklmnopqrstuuF0I9oWV6x3p4GmD0Yj6Hf8wd2Kx0D5u',
      'Carryover',
      'Agent',
      'agent',
      1,
      NOW()
    );
  `);

  const ids = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM organizations WHERE name = ${sqlString(orgName)} LIMIT 1) AS org_id,
      (SELECT id FROM users WHERE email = ${sqlString(agentEmail)} LIMIT 1) AS agent_id
  `));

  await login(page);
  await page.goto('/index.php?page=new-ticket');
  await page.locator('input[name="title"]').fill(`Carryover source ${stamp}`);
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>First ticket with company and assignee.</p>';
  });
  await page.locator('select[name="organization_id"]').selectOption(String(ids.org_id));
  await page.locator('details').first().evaluate(details => { details.open = true; });
  await page.locator('select[name="assignee_id"]').selectOption(String(ids.agent_id));
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);

  await page.goto('/index.php?page=new-ticket');
  await expect(page.locator('select[name="organization_id"]')).toHaveValue('');
  await expect(page.locator('select[name="assignee_id"]')).toHaveValue('');
});

test('admin can set a per-agent client billable rate for time reports', async ({ page }) => {
  const stamp = Date.now();
  const orgName = `E2E Rate Client ${stamp}`;
  const agentEmail = `rate.agent.${stamp}@example.test`;
  const tenant = rowObject(dbQuery("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1"));
  const tenantId = Number(tenant.id);

  dbQuery(`
    INSERT INTO organizations (tenant_id, name, billable_rate, is_active, created_at)
    VALUES (${tenantId}, ${sqlString(orgName)}, 500, 1, NOW());
  `);
  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, is_active, created_at)
    VALUES (
      ${tenantId},
      ${sqlString(agentEmail)},
      '$2y$10$abcdefghijklmnopqrstuuF0I9oWV6x3p4GmD0Yj6Hf8wd2Kx0D5u',
      'Rate',
      'Agent',
      'agent',
      1,
      NOW()
    );
  `);

  const ids = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM organizations WHERE name = ${sqlString(orgName)} LIMIT 1) AS org_id,
      (SELECT id FROM users WHERE email = ${sqlString(agentEmail)} LIMIT 1) AS agent_id,
      (SELECT id FROM users WHERE email = 'admin@example.test' LIMIT 1) AS admin_id,
      (SELECT id FROM statuses ORDER BY is_default DESC, id ASC LIMIT 1) AS status_id
  `));

  await login(page);
  await page.goto('/index.php?page=admin&section=reports&tab=rates');
  await expect(page.locator('body')).toContainText('Agent client rates');
  await page.locator('select[name="organization_id"]').selectOption(String(ids.org_id));
  await page.locator('select[name="user_id"]').selectOption(String(ids.agent_id));
  await page.locator('input[name="billable_rate"]').fill('750');
  await page.locator('button[name="save_agent_client_rate"]').click();
  await expect(page).toHaveURL(/tab=rates/);
  await expect(page.locator('body')).toContainText(orgName);
  await expect(page.locator('body')).toContainText('Rate Agent');
  await expect(page.locator('body')).toContainText('750');

  const ticketHash = `rate${stamp}`.slice(0, 16);
  dbQuery(`
    INSERT INTO tickets (tenant_id, hash, title, description, user_id, organization_id, status_id, created_at, updated_at)
    VALUES (${tenantId}, ${sqlString(ticketHash)}, 'Rate override ticket', 'Rate override test',
      ${Number(ids.admin_id)}, ${Number(ids.org_id)}, ${Number(ids.status_id)}, NOW(), NOW());
  `);
  const ticket = rowObject(dbQuery(`SELECT id FROM tickets WHERE hash = ${sqlString(ticketHash)} LIMIT 1`));

  const rateResult = php(`
    define('BASE_PATH', '/var/www/html');
    require BASE_PATH . '/config.php';
    require BASE_PATH . '/includes/database.php';
    require BASE_PATH . '/includes/tenant-functions.php';
    require '/var/www/html/includes/functions.php';
    ensure_tenant_baseline();
    $entry_id = add_manual_time_entry(${Number(ticket.id)}, ${Number(ids.agent_id)}, [
      'started_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
      'ended_at' => date('Y-m-d H:i:s'),
      'duration_minutes' => 60,
      'summary' => 'Rate override entry',
      'is_billable' => 1
    ]);
    $entry = db_fetch_one('SELECT billable_rate FROM ticket_time_entries WHERE id = ?', [$entry_id]);
    echo $entry ? $entry['billable_rate'] : 'missing';
  `).trim();

  expect(rateResult).toBe('750.00');
});

test('admin can bulk adjust billable report items', async ({ page }) => {
  const stamp = Date.now();
  const orgName = `E2E Billing Adjust Client ${stamp}`;
  const agentEmail = `billing.adjust.${stamp}@example.test`;
  const tenant = rowObject(dbQuery("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1"));
  const tenantId = Number(tenant.id);

  dbQuery(`
    INSERT INTO organizations (tenant_id, name, billable_rate, is_active, created_at)
    VALUES (${tenantId}, ${sqlString(orgName)}, 1000, 1, NOW());
  `);
  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, is_active, created_at)
    VALUES (
      ${tenantId},
      ${sqlString(agentEmail)},
      '$2y$10$abcdefghijklmnopqrstuuF0I9oWV6x3p4GmD0Yj6Hf8wd2Kx0D5u',
      'Billing',
      'Adjust',
      'agent',
      1,
      NOW()
    );
  `);

  const ids = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM organizations WHERE name = ${sqlString(orgName)} LIMIT 1) AS org_id,
      (SELECT id FROM users WHERE email = ${sqlString(agentEmail)} LIMIT 1) AS agent_id,
      (SELECT id FROM users WHERE email = 'admin@example.test' LIMIT 1) AS admin_id,
      (SELECT id FROM statuses ORDER BY is_default DESC, id ASC LIMIT 1) AS status_id
  `));

  const ticketHash = `billadj${stamp}`.slice(0, 16);
  dbQuery(`
    INSERT INTO tickets (tenant_id, hash, title, description, user_id, organization_id, status_id, created_at, updated_at)
    VALUES (${tenantId}, ${sqlString(ticketHash)}, 'Bulk billing adjustment ticket', 'Bulk billing adjustment test',
      ${Number(ids.admin_id)}, ${Number(ids.org_id)}, ${Number(ids.status_id)}, NOW(), NOW());
  `);
  const ticket = rowObject(dbQuery(`SELECT id FROM tickets WHERE hash = ${sqlString(ticketHash)} LIMIT 1`));

  dbQuery(`
    INSERT INTO ticket_time_entries
      (tenant_id, ticket_id, user_id, started_at, ended_at, duration_minutes, is_billable, billable_rate, is_manual, summary, created_at)
    VALUES
      (${tenantId}, ${Number(ticket.id)}, ${Number(ids.agent_id)}, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 1 HOUR, 60, 1, 1000, 1, 'Bulk item one', NOW()),
      (${tenantId}, ${Number(ticket.id)}, ${Number(ids.agent_id)}, NOW() - INTERVAL 1 HOUR, NOW(), 60, 1, 1000, 1, 'Bulk item two', NOW());
  `);

  await login(page);
  await page.goto(`/index.php?page=admin&section=reports&tab=detailed&time_range=this_month&organizations%5B%5D=${ids.org_id}&show_money=1`);
  await expect(page.locator('body')).toContainText('Bulk billing adjustment ticket');
  await expect(page.locator('body')).toContainText('Bulk billing adjustments');
  await expect(page.locator('.entry-billing-form')).toHaveCount(2);
  await expect(page.locator('#detail-billable-amount')).toContainText(/2\s000[,.]00 CZK/);

  const firstEntry = rowObject(dbQuery(`
    SELECT id
    FROM ticket_time_entries
    WHERE ticket_id = ${Number(ticket.id)}
    ORDER BY id ASC
    LIMIT 1;
  `));

  const firstEntryForm = page.locator(`.entry-billing-form[data-entry-id="${firstEntry.id}"]`);
  await firstEntryForm.locator('select[name="entry_adjust_action"]').selectOption('target_total');
  await firstEntryForm.locator('input[name="entry_adjust_value"]').fill('750');
  await expect(page.locator('#detail-billable-amount')).toContainText(/1\s750[,.]00 CZK/);
  await firstEntryForm.locator('button[name="adjust_billable_entry"]').click();
  await expect(page.locator('body')).toContainText('Billable item adjustment updated.');

  let firstRate = rowObject(dbQuery(`
    SELECT FORMAT(billable_rate, 2) AS rate
    FROM ticket_time_entries
    WHERE id = ${Number(firstEntry.id)}
    LIMIT 1;
  `));
  expect(firstRate.rate).toBe('750.00');

  await page.locator('#bulk-select-all').check();
  await page.locator('select[name="bulk_action"]').selectOption('discount_percent');
  await page.locator('input[name="bulk_discount_percent"]').fill('10');
  await expect(page.locator('#detail-billable-amount')).toContainText(/1\s575[,.]00 CZK/);
  await page.locator('#bulk-billing-form button[type="submit"]').click();
  await expect(page.locator('body')).toContainText('Billable item adjustments updated: 2.');

  let output = dbQuery(`
    SELECT GROUP_CONCAT(FORMAT(billable_rate, 2) ORDER BY id SEPARATOR ',') AS rates
    FROM ticket_time_entries
    WHERE ticket_id = ${Number(ticket.id)};
  `);
  expect(rowObject(output).rates).toBe('675.00,900.00');

  await page.locator('#bulk-select-all').check();
  await page.locator('select[name="bulk_action"]').selectOption('target_total');
  await page.locator('input[name="bulk_target_total"]').fill('1000');
  await expect(page.locator('#detail-billable-amount')).toContainText(/1\s000[,.]00 CZK/);
  await page.locator('#bulk-billing-form button[type="submit"]').click();
  await expect(page.locator('body')).toContainText('Billable item adjustments updated: 2.');

  output = dbQuery(`
    SELECT GROUP_CONCAT(FORMAT(billable_rate, 2) ORDER BY id SEPARATOR ',') AS rates
    FROM ticket_time_entries
    WHERE ticket_id = ${Number(ticket.id)};
  `);
  expect(rowObject(output).rates).toBe('500.00,500.00');
});
