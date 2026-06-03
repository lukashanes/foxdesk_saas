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

function seedStorageUsage(ownerEmail, fileSizeBytes, storageDriver = 'local') {
  const ticketHash = `stor${storageDriver[0]}${Date.now()}`.slice(0, 16);
  const storageBucket = storageDriver === 'r2' ? 'foxdesk-e2e-r2' : '';
  const storageKey = storageDriver === 'r2' ? `e2e/${ticketHash}.bin` : '';
  dbQuery(`
    INSERT INTO tickets (tenant_id, hash, title, description, user_id, status_id, created_at, updated_at)
    SELECT u.tenant_id, ${sqlString(ticketHash)}, 'Storage usage seed', 'E2E storage usage seed', u.id,
      (SELECT id FROM statuses ORDER BY is_default DESC, id ASC LIMIT 1), NOW(), NOW()
    FROM users u
    WHERE u.email = ${sqlString(ownerEmail)}
    LIMIT 1;
  `);
  dbQuery(`
    INSERT INTO attachments (tenant_id, ticket_id, filename, original_name, mime_type, file_size, storage_driver, storage_bucket, storage_key, uploaded_by, created_at)
    SELECT t.tenant_id, t.id, ${sqlString(`${ticketHash}.bin`)}, 'storage.bin', 'application/octet-stream', ${Number(fileSizeBytes)},
      ${sqlString(storageDriver)}, ${sqlString(storageBucket)}, ${sqlString(storageKey)}, t.user_id, NOW()
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
  await expect(signupPage.locator('#get-started')).toContainText('Create your first ticket');
  await expect(signupPage.locator('#get-started')).toContainText('Review trial and billing');

  await signupPage.goto('/index.php?page=platform');
  await expect(signupPage).toHaveURL(/page=dashboard|dashboard/);
  await signupContext.close();

  const platformContext = await browser.newContext({ baseURL });
  const platformPage = await platformContext.newPage();
  await login(platformPage);
  await platformPage.goto('/index.php?page=platform');
  await expect(platformPage.locator('body')).toContainText('Workspace catalog');
  await expect(platformPage.locator('body')).toContainText(workspaceName);
  await expect(platformPage.locator('body')).toContainText(ownerEmail);
  await expect(platformPage.locator('body')).toContainText('FoxDesk Cloud');

  const workspaceRow = platformPage.locator('[data-workspace-row]').filter({ hasText: workspaceName });
  await expect(workspaceRow).toHaveCount(1);
  await workspaceRow.getByText('Open detail').click();
  await expect(platformPage).toHaveURL(/tenant_id=/);
  await expect(platformPage.locator('#tenant-detail')).toContainText(workspaceName);
  await expect(platformPage.locator('#tenant-detail')).toContainText('Owner access');
  await expect(platformPage.locator('#tenant-detail')).toContainText('Subscription history');
  await expect(platformPage.locator('#tenant-detail')).toContainText('Usage overview');

  const replacementOwner = `replacement.${stamp}@example.test`;
  await platformPage.locator('#tenant-detail input[name="owner_email"]').fill(replacementOwner);
  await platformPage.locator('#tenant-detail input[name="owner_first_name"]').fill('Replacement');
  await platformPage.locator('#tenant-detail input[name="owner_last_name"]').fill('Owner');
  await platformPage.locator('#tenant-detail button', { hasText: 'Invite owner' }).click();
  await expect(platformPage.locator('body')).toContainText('Owner invite sent');
  await expect(platformPage.locator('#tenant-detail')).toContainText(replacementOwner);

  const ownerUpdate = dbQuery(`
    SELECT u.email, u.reset_token IS NOT NULL AS has_reset
    FROM tenants t
    JOIN users u ON u.id = t.owner_user_id
    WHERE t.name = ${sqlString(workspaceName)}
    LIMIT 1;
  `);
  expect(ownerUpdate).toContain(`${replacementOwner}\t1`);
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
  expect(await response.json()).toEqual(expect.objectContaining({
    duplicate: false,
    event_id: `evt_${stamp}`,
    handled: true,
    tenant_id: tenantId,
    type: 'customer.subscription.updated'
  }));

  let output = dbQuery(`
    SELECT status, subscription_status, stripe_customer_id, stripe_subscription_id
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('active\tactive');
  expect(output).toContain(`cus_${stamp}`);
  expect(output).toContain(`sub_${stamp}`);

  output = dbQuery(`
    SELECT event_id, event_type, tenant_id, status, error_message
    FROM billing_stripe_events
    WHERE event_id = ${sqlString(`evt_${stamp}`)}
    LIMIT 1;
  `);
  expect(output).toContain(`evt_${stamp}\tcustomer.subscription.updated\t${tenantId}\tprocessed`);

  dbQuery(`
    UPDATE tenants
    SET status = 'past_due', subscription_status = 'past_due',
      stripe_customer_id = 'cus_duplicate_guard_${stamp}',
      stripe_subscription_id = 'sub_duplicate_guard_${stamp}'
    WHERE id = ${tenantId};
  `);

  const duplicate = await request.post('/index.php?page=stripe-webhook', {
    data: payload,
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': stripeSignature(payload)
    }
  });
  expect(duplicate.status()).toBe(200);
  expect(await duplicate.json()).toEqual(expect.objectContaining({
    duplicate: true,
    event_id: `evt_${stamp}`,
    tenant_id: tenantId,
    status: 'processed',
    type: 'customer.subscription.updated'
  }));

  output = dbQuery(`
    SELECT status, subscription_status, stripe_customer_id, stripe_subscription_id
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('past_due\tpast_due');
  expect(output).toContain(`cus_duplicate_guard_${stamp}`);
  expect(output).toContain(`sub_duplicate_guard_${stamp}`);

  output = dbQuery(`
    SELECT COUNT(*) AS c
    FROM billing_stripe_events
    WHERE event_id = ${sqlString(`evt_${stamp}`)};
  `);
  expect(output).toContain('\n1');
});

test('Stripe checkout and invoice webhooks keep tenant lifecycle in sync', async ({ browser, request }) => {
  const stamp = Date.now();
  const ownerEmail = `invoice.${stamp}@example.test`;
  const workspaceName = `Invoice Workspace ${stamp}`;

  const { context } = await createWorkspaceViaUi(browser, {
    workspaceName,
    ownerEmail,
    firstName: 'Invoice',
    lastName: 'Owner'
  });
  await context.close();

  const tenantId = tenantIdByOwnerEmail(ownerEmail);
  expect(tenantId).toBeGreaterThan(0);

  const checkoutEvent = {
    id: `evt_checkout_${stamp}`,
    type: 'checkout.session.completed',
    data: {
      object: {
        id: `cs_${stamp}`,
        customer: `cus_checkout_${stamp}`,
        subscription: `sub_checkout_${stamp}`,
        client_reference_id: String(tenantId),
        metadata: {}
      }
    }
  };
  const checkoutPayload = JSON.stringify(checkoutEvent);
  const checkoutResponse = await request.post('/index.php?page=stripe-webhook', {
    data: checkoutPayload,
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': stripeSignature(checkoutPayload)
    }
  });
  expect(checkoutResponse.status()).toBe(200);

  let output = dbQuery(`
    SELECT status, subscription_status, stripe_customer_id, stripe_subscription_id
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('active\tactive');
  expect(output).toContain(`cus_checkout_${stamp}`);
  expect(output).toContain(`sub_checkout_${stamp}`);

  const failedInvoice = {
    id: `evt_invoice_failed_${stamp}`,
    type: 'invoice.payment_failed',
    data: {
      object: {
        id: `in_failed_${stamp}`,
        customer: `cus_checkout_${stamp}`,
        subscription: `sub_checkout_${stamp}`
      }
    }
  };
  const failedPayload = JSON.stringify(failedInvoice);
  const failedResponse = await request.post('/index.php?page=stripe-webhook', {
    data: failedPayload,
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': stripeSignature(failedPayload)
    }
  });
  expect(failedResponse.status()).toBe(200);

  output = dbQuery(`
    SELECT status, subscription_status, suspended_at IS NOT NULL AS has_suspended_at, blocked_at IS NULL AS has_no_blocked_at
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('past_due\tpast_due\t1\t1');

  const ownerContext = await browser.newContext({ baseURL });
  const ownerPage = await ownerContext.newPage();
  await login(ownerPage, ownerEmail, 'OwnerPass123!');
  await ownerPage.goto('/index.php?page=tickets');
  await expect(ownerPage).not.toHaveURL(/page=billing/);

  dbQuery(`
    UPDATE tenants
    SET suspended_at = DATE_SUB(NOW(), INTERVAL 5 DAY)
    WHERE id = ${tenantId};
  `);
  const maintenance = JSON.parse(dockerExec(webContainer, ['php', 'bin/run-maintenance.php', '--json']));
  expect(maintenance.past_due_suspension.tenant_ids).toContain(tenantId);

  output = dbQuery(`
    SELECT status, subscription_status, blocked_at IS NOT NULL AS has_blocked_at
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('suspended\tpast_due\t1');

  await ownerPage.goto('/index.php?page=tickets');
  await expect(ownerPage).toHaveURL(/page=billing/);
  await expect(ownerPage.locator('body')).toContainText('Workspace access is suspended');

  const paidInvoice = {
    id: `evt_invoice_paid_${stamp}`,
    type: 'invoice.paid',
    data: {
      object: {
        id: `in_paid_${stamp}`,
        customer: `cus_checkout_${stamp}`,
        subscription: `sub_checkout_${stamp}`
      }
    }
  };
  const paidPayload = JSON.stringify(paidInvoice);
  const paidResponse = await request.post('/index.php?page=stripe-webhook', {
    data: paidPayload,
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': stripeSignature(paidPayload)
    }
  });
  expect(paidResponse.status()).toBe(200);

  output = dbQuery(`
    SELECT status, subscription_status, suspended_at IS NULL AS no_suspended_at, blocked_at IS NULL AS no_blocked_at
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('active\tactive\t1\t1');

  await ownerPage.goto('/index.php?page=tickets');
  await expect(ownerPage).not.toHaveURL(/page=billing/);
  await ownerContext.close();
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
  await expect(page.locator('body')).toContainText('This subscription has been canceled');
  await expect(page.locator('body')).toContainText('Billing');
  await ownerContext.close();
});

test('trial lifecycle emails, expiry, and operator extension work end to end', async ({ browser }) => {
  const stamp = Date.now();
  const ownerEmail = `trial.${stamp}@example.test`;
  const ownerPassword = 'OwnerPass123!';
  const workspaceName = `Trial Lifecycle ${stamp}`;

  const { context: ownerContext, page } = await createWorkspaceViaUi(browser, {
    workspaceName,
    ownerEmail,
    ownerPassword,
    firstName: 'Trial',
    lastName: 'Owner'
  });

  const tenantId = tenantIdByOwnerEmail(ownerEmail);
  expect(tenantId).toBeGreaterThan(0);

  let output = dbQuery(`
    SELECT event_type, status
    FROM billing_trial_email_events
    WHERE tenant_id = ${tenantId} AND event_type = 'trial_started'
    LIMIT 1;
  `);
  expect(output).toContain('trial_started');

  dbQuery(`
    UPDATE tenants
    SET trial_ends_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
    WHERE id = ${tenantId};
  `);
  let maintenance = JSON.parse(dockerExec(webContainer, ['php', 'bin/run-maintenance.php', '--json']));
  expect(maintenance.trial_expiration.tenant_ids).not.toContain(tenantId);

  output = dbQuery(`
    SELECT status, subscription_status
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('trialing\ttrialing');

  await page.goto('/index.php?page=tickets');
  await expect(page).not.toHaveURL(/page=billing/);

  dbQuery(`
    UPDATE tenants
    SET trial_ends_at = DATE_SUB(NOW(), INTERVAL 5 DAY)
    WHERE id = ${tenantId};
  `);
  maintenance = JSON.parse(dockerExec(webContainer, ['php', 'bin/run-maintenance.php', '--json']));
  expect(maintenance.trial_expiration.tenant_ids).toContain(tenantId);

  output = dbQuery(`
    SELECT status, subscription_status
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('trial_expired\ttrial_expired');

  output = dbQuery(`
    SELECT event_type
    FROM billing_trial_email_events
    WHERE tenant_id = ${tenantId} AND event_type = 'trial_expired'
    LIMIT 1;
  `);
  expect(output).toContain('trial_expired');

  await page.goto('/index.php?page=tickets');
  await expect(page).toHaveURL(/page=billing/);
  await expect(page.locator('body')).toContainText('trial has ended');
  await ownerContext.close();

  const platformContext = await browser.newContext({ baseURL });
  const platformPage = await platformContext.newPage();
  await login(platformPage);
  await platformPage.goto('/index.php?page=platform');
  const row = platformPage.locator('tr[data-workspace-row]').filter({ hasText: workspaceName });
  await expect(row).toContainText('trial_expired');
  await row.getByRole('button', { name: '+7d trial' }).click();
  await expect(platformPage.locator('body')).toContainText('Trial extended by 7 days.');

  output = dbQuery(`
    SELECT status, subscription_status, trial_ends_at > NOW() AS future_trial
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('trialing\ttrialing\t1');
  await platformContext.close();
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

  seedStorageUsage(ownerEmail, 2 * 1073741824, 'local');
  seedStorageUsage(ownerEmail, 1 * 1073741824, 'r2');
  const tenantId = tenantIdByOwnerEmail(ownerEmail);
  dbQuery(`UPDATE tenants SET stripe_customer_id = 'cus_usage_${stamp}' WHERE id = ${tenantId};`);

  await page.request.get('/index.php?page=api&action=app-shell');

  await page.goto('/index.php?page=billing');
  await expect(page.locator('body')).toContainText('FoxDesk Cloud');
  await expect(page.locator('body')).toContainText('Unlimited users, clients, agents, and tickets');
  await expect(page.locator('body')).toContainText('3.00 GB / 1.00 GB');
  await expect(page.locator('body')).toContainText('2 GB');
  await expect(page.locator('body')).toContainText('Local storage');
  await expect(page.locator('body')).toContainText('R2 storage');
  await expect(page.locator('body')).toContainText('API requests');
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
