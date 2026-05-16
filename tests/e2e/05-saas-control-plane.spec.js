const crypto = require('crypto');
const { test, expect } = require('@playwright/test');
const { dbQuery, dockerExec, login } = require('./helpers');
const { baseURL, webContainer } = require('./env');

function sqlString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function tenantIdByOwnerEmail(email) {
  const output = dbQuery(`SELECT tenant_id FROM users WHERE email = ${sqlString(email)} LIMIT 1;`);
  const lines = output.trim().split(/\r?\n/).filter(Boolean);
  return Number(lines[1] || 0);
}

function seedStorageUsage(ownerEmail, fileSizeBytes) {
  const ticketHash = `stor${Date.now()}`.slice(0, 16);
  dbQuery(`
    INSERT INTO tickets (tenant_id, hash, title, description, user_id, status_id, created_at, updated_at)
    SELECT u.tenant_id, ${sqlString(ticketHash)}, 'Storage usage seed', 'E2E storage usage seed', u.id,
      (SELECT id FROM statuses ORDER BY is_default DESC, id ASC LIMIT 1), NOW(), NOW()
    FROM users u
    WHERE u.email = ${sqlString(ownerEmail)}
    LIMIT 1;
  `);
  dbQuery(`
    INSERT INTO attachments (tenant_id, ticket_id, filename, original_name, mime_type, file_size, uploaded_by, created_at)
    SELECT t.tenant_id, t.id, ${sqlString(`${ticketHash}.bin`)}, 'storage.bin', 'application/octet-stream', ${Number(fileSizeBytes)},
      t.user_id, NOW()
    FROM tickets t
    WHERE t.hash = ${sqlString(ticketHash)}
    LIMIT 1;
  `);
}

function stripeSignature(payload, secret = 'whsec_test') {
  const timestamp = Math.floor(Date.now() / 1000);
  const signature = crypto
    .createHmac('sha256', secret)
    .update(`${timestamp}.${payload}`)
    .digest('hex');
  return `t=${timestamp},v1=${signature}`;
}

async function createWorkspaceViaUi(browser, {
  workspaceName,
  ownerEmail,
  ownerPassword = 'OwnerPass123!',
  firstName = 'Owner',
  lastName = 'SaaS'
}) {
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await page.goto('/index.php?page=signup');
  await page.locator('input[name="workspace_name"]').fill(workspaceName);
  await page.locator('input[name="admin_first_name"]').fill(firstName);
  await page.locator('input[name="admin_last_name"]').fill(lastName);
  await page.locator('input[name="admin_email"]').fill(ownerEmail);
  await page.locator('input[name="password"]').fill(ownerPassword);
  await page.locator('input[name="password_confirm"]').fill(ownerPassword);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=dashboard|dashboard/);
  return { context, page };
}

test('public signup creates an isolated FoxDesk workspace and platform admin can manage it', async ({ browser }) => {
  const stamp = Date.now();
  const workspaceName = `E2E SaaS Workspace ${stamp}`;
  const ownerEmail = `owner.${stamp}@example.test`;
  const ownerPassword = 'OwnerPass123!';

  const { context: signupContext, page: signupPage } = await createWorkspaceViaUi(browser, {
    workspaceName,
    ownerEmail,
    ownerPassword
  });
  await expect(signupPage.locator('body')).toContainText('Dashboard');

  await signupPage.goto('/index.php?page=platform');
  await expect(signupPage).toHaveURL(/page=dashboard|dashboard/);
  await signupContext.close();

  const platformContext = await browser.newContext({ baseURL });
  const platformPage = await platformContext.newPage();
  await login(platformPage);
  await platformPage.goto('/index.php?page=platform');
  await expect(platformPage.locator('body')).toContainText('Customer FoxDesks');
  await expect(platformPage.locator('body')).toContainText(workspaceName);
  await expect(platformPage.locator('body')).toContainText(ownerEmail);
  await expect(platformPage.locator('body')).toContainText('FoxDesk Cloud');
  await platformContext.close();
});

test('Stripe webhook updates tenant billing state and rejects invalid signatures', async ({ browser, request }) => {
  const stamp = Date.now();
  const ownerEmail = `billing.${stamp}@example.test`;
  const workspaceName = `Billing Workspace ${stamp}`;

  const { context } = await createWorkspaceViaUi(browser, {
    workspaceName,
    ownerEmail,
    firstName: 'Billing',
    lastName: 'Owner'
  });
  await context.close();

  const tenantId = tenantIdByOwnerEmail(ownerEmail);
  expect(tenantId).toBeGreaterThan(0);

  const event = {
    id: `evt_${stamp}`,
    type: 'customer.subscription.updated',
    data: {
      object: {
        id: `sub_${stamp}`,
        customer: `cus_${stamp}`,
        status: 'active',
        trial_end: null,
        metadata: { tenant_id: String(tenantId) }
      }
    }
  };
  const payload = JSON.stringify(event);

  const invalid = await request.post('/index.php?page=stripe-webhook', {
    data: payload,
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': 't=1,v1=bad'
    }
  });
  expect(invalid.status()).toBe(400);

  const response = await request.post('/index.php?page=stripe-webhook', {
    data: payload,
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': stripeSignature(payload)
    }
  });
  expect(response.status()).toBe(200);
  await expect(response).toBeOK();

  const output = dbQuery(`
    SELECT status, subscription_status, stripe_customer_id, stripe_subscription_id
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('active\tactive');
  expect(output).toContain(`cus_${stamp}`);
  expect(output).toContain(`sub_${stamp}`);
});

test('blocked tenant admins are redirected to billing instead of app pages', async ({ browser }) => {
  const stamp = Date.now();
  const ownerEmail = `blocked.${stamp}@example.test`;
  const ownerPassword = 'OwnerPass123!';

  const { context: ownerContext, page } = await createWorkspaceViaUi(browser, {
    workspaceName: `Blocked Workspace ${stamp}`,
    ownerEmail,
    ownerPassword,
    firstName: 'Blocked',
    lastName: 'Owner'
  });

  const tenantId = tenantIdByOwnerEmail(ownerEmail);
  expect(tenantId).toBeGreaterThan(0);
  dbQuery(`UPDATE tenants SET status = 'canceled', subscription_status = 'canceled' WHERE id = ${tenantId};`);

  await page.goto('/index.php?page=tickets');
  await expect(page).toHaveURL(/page=billing/);
  await expect(page.locator('body')).toContainText('Workspace access is restricted');
  await expect(page.locator('body')).toContainText('Billing');
  await ownerContext.close();
});

test('single cloud plan shows unlimited usage and storage overage', async ({ browser }) => {
  const stamp = Date.now();
  const ownerEmail = `usage.${stamp}@example.test`;

  const { context, page } = await createWorkspaceViaUi(browser, {
    workspaceName: `Usage Workspace ${stamp}`,
    ownerEmail,
    firstName: 'Usage',
    lastName: 'Owner'
  });

  seedStorageUsage(ownerEmail, 3 * 1073741824);
  const tenantId = tenantIdByOwnerEmail(ownerEmail);
  dbQuery(`UPDATE tenants SET stripe_customer_id = 'cus_usage_${stamp}' WHERE id = ${tenantId};`);

  await page.goto('/index.php?page=billing');
  await expect(page.locator('body')).toContainText('FoxDesk Cloud');
  await expect(page.locator('body')).toContainText('Unlimited users, clients, agents, and tickets');
  await expect(page.locator('body')).toContainText('3.00 GB / 1.00 GB');
  await expect(page.locator('body')).toContainText('2 GB');
  await expect(page.locator('body')).toContainText('EUR 3.80');

  const report = JSON.parse(dockerExec(webContainer, ['php', 'bin/report-billing-usage.php', '--dry-run', '--json']));
  expect(report.ok).toBe(true);
  expect(report.dry_run).toBeGreaterThanOrEqual(1);
  expect(report.tenants).toEqual(expect.arrayContaining([
    expect.objectContaining({
      tenant_id: tenantId,
      status: 'dry_run',
      quantity: 2
    })
  ]));

  const usageReport = dbQuery(`
    SELECT quantity, status
    FROM billing_usage_reports
    WHERE tenant_id = ${tenantId}
    LIMIT 1;
  `);
  expect(usageReport).toContain('2\tdry_run');
  await context.close();
});
