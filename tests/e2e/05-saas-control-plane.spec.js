const crypto = require('crypto');
const { test, expect } = require('@playwright/test');
const { dbQuery, dockerExec, login } = require('./helpers');
const { baseURL, platformBaseURL, webContainer } = require('./env');

function sqlString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function rowObject(output) {
  const lines = output.trim().split(/\r?\n/).filter(Boolean);
  const headers = (lines[0] || '').split('\t');
  const values = (lines[1] || '').split('\t');
  return Object.fromEntries(headers.map((header, index) => [header, values[index] || '']));
}

function tenantIdByOwnerEmail(email) {
  const output = dbQuery(`SELECT tenant_id FROM users WHERE email = ${sqlString(email)} LIMIT 1;`);
  const lines = output.trim().split(/\r?\n/).filter(Boolean);
  return Number(lines[1] || 0);
}

function latestSignupLinkForEmail(email) {
  const output = dbQuery(`
    SELECT token_hash, consumed_at IS NULL AS is_open, expires_at > NOW() AS is_valid
    FROM signup_magic_links
    WHERE email = ${sqlString(email)}
    ORDER BY id DESC
    LIMIT 1;
  `);
  const lines = output.trim().split(/\r?\n/).filter(Boolean);
  const values = (lines[1] || '').split('\t');
  return {
    tokenHash: values[0] || '',
    isOpen: values[1] === '1',
    isValid: values[2] === '1'
  };
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
  firstName = null,
  lastName = null
}) {
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await page.goto('/index.php?page=signup');
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toHaveCount(0);

  const token = crypto.randomBytes(32).toString('hex');
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  dbQuery(`
    CREATE TABLE IF NOT EXISTS signup_magic_links (
      id INT AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(255) NOT NULL,
      token_hash CHAR(64) NOT NULL UNIQUE,
      expires_at DATETIME NOT NULL,
      consumed_at DATETIME NULL,
      ip VARCHAR(45) NULL,
      user_agent VARCHAR(255) NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_email_created (email, created_at),
      INDEX idx_expires_at (expires_at),
      INDEX idx_consumed_at (consumed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  `);
  dbQuery(`
    INSERT INTO signup_magic_links (email, token_hash, expires_at, ip, user_agent, created_at)
    VALUES (${sqlString(ownerEmail)}, ${sqlString(tokenHash)}, '2099-01-01 00:00:00', '127.0.0.1', 'playwright', NOW());
  `);
  await page.goto(`/index.php?page=signup&token=${token}`);
  await page.waitForURL(/page=work|page=dashboard|dashboard/);
  if (workspaceName) {
    dbQuery(`
      UPDATE tenants t
      JOIN users u ON u.tenant_id = t.id
      SET t.name = ${sqlString(workspaceName)}
      WHERE u.email = ${sqlString(ownerEmail)};
    `);
  }
  if (firstName || lastName) {
    dbQuery(`
      UPDATE users
      SET first_name = ${sqlString(firstName || 'Owner')}, last_name = ${sqlString(lastName || '')}
      WHERE email = ${sqlString(ownerEmail)};
    `);
  }
  if (ownerPassword) {
    const passwordHash = dockerExec(webContainer, [
      'php',
      '-r',
      `echo password_hash(${JSON.stringify(ownerPassword)}, PASSWORD_DEFAULT);`
    ]).trim();
    dbQuery(`
      UPDATE users
      SET password = ${sqlString(passwordHash)}
      WHERE email = ${sqlString(ownerEmail)};
    `);
  }
  return { context, page };
}

test('email-only signup posts a magic link and creates workspace only after verification', async ({ browser }) => {
  const stamp = Date.now();
  const ownerEmail = `email-only.${stamp}@example.test`;
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();

  await page.goto('/index.php?page=signup');
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="workspace_name"]')).toHaveCount(0);
  await expect(page.locator('input[name="password"]')).toHaveCount(0);
  await page.locator('input[name="email"]').fill(ownerEmail);
  await page.getByRole('button', { name: 'Start free trial' }).click();
  await expect(page.locator('body')).toContainText('Check your email');

  let output = dbQuery(`
    SELECT COUNT(*) AS c
    FROM users
    WHERE email = ${sqlString(ownerEmail)};
  `);
  expect(output).toContain('\n0');

  let firstLink = latestSignupLinkForEmail(ownerEmail);
  expect(firstLink.tokenHash).toMatch(/^[a-f0-9]{64}$/);
  expect(firstLink.isOpen).toBe(true);
  expect(firstLink.isValid).toBe(true);

  await page.getByRole('button', { name: 'Send link again' }).click();
  await expect(page.locator('body')).toContainText('Check your email');

  output = dbQuery(`
    SELECT COUNT(*) AS total, SUM(consumed_at IS NULL) AS open_links
    FROM signup_magic_links
    WHERE email = ${sqlString(ownerEmail)};
  `);
  expect(output).toContain('\n2\t1');
  const secondLink = latestSignupLinkForEmail(ownerEmail);
  expect(secondLink.tokenHash).toMatch(/^[a-f0-9]{64}$/);
  expect(secondLink.tokenHash).not.toBe(firstLink.tokenHash);

  const token = crypto.randomBytes(32).toString('hex');
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  dbQuery(`
    UPDATE signup_magic_links
    SET token_hash = ${sqlString(tokenHash)}
    WHERE email = ${sqlString(ownerEmail)} AND consumed_at IS NULL
    ORDER BY id DESC
    LIMIT 1;
  `);

  await page.goto(`/index.php?page=signup&token=${token}`);
  await page.waitForURL(/page=work/);
  await expect(page.locator('body')).toContainText('Dashboard');
  await expect(page.locator('[data-signup-onboarding]')).toContainText('Trial started');
  await expect(page.locator('[data-signup-onboarding]')).toContainText('Your FoxDesk is ready');

  output = dbQuery(`
    SELECT t.name, t.status, t.subscription_status, u.role, u.password <> '' AS has_password,
      o.name AS organization_name
    FROM users u
    JOIN tenants t ON t.id = u.tenant_id
    JOIN organizations o ON o.tenant_id = t.id
    WHERE u.email = ${sqlString(ownerEmail)}
    LIMIT 1;
  `);
  expect(output).toContain('Example\ttrialing\ttrialing\tadmin\t1\tExample');

  await context.close();
});

test('public signup creates an isolated FoxDesk workspace and platform admin can manage it', async ({ browser }) => {
  const stamp = Date.now();
  const workspaceName = `E2e Saas Workspace ${stamp}`;
  const ownerEmail = `owner@e2e-saas-workspace-${stamp}.test`;

  const { context: signupContext, page: signupPage } = await createWorkspaceViaUi(browser, {
    workspaceName,
    ownerEmail
  });
  await expect(signupPage.locator('body')).toContainText('Dashboard');
  await signupPage.goto('/index.php?page=dashboard');
  await expect(signupPage.locator('body')).toContainText('Dashboard');
  await expect(signupPage.locator('#get-started')).toContainText('Create your first ticket');
  await expect(signupPage.locator('#get-started')).toContainText('Review trial and billing');

  await signupPage.goto('/index.php?page=platform');
  await expect(signupPage).toHaveURL(/platform\.localhost.*page=login/);
  await signupContext.close();

  const platformContext = await browser.newContext({ baseURL: platformBaseURL });
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
  await expect(ownerPage.locator('body')).toContainText('We could not process payment');

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
  await expect(page.locator('body')).toContainText('This plan was canceled');
  await expect(page.locator('body')).toContainText('Billing');
  await ownerContext.close();
});

test('platform admin can grant free access and restore workspace access', async ({ browser }) => {
  const stamp = Date.now();
  const workspaceName = `Free Override ${stamp}`;
  const ownerEmail = `free.${stamp}@example.test`;
  const ownerPassword = 'OwnerPass123!';

  const { context: ownerContext, page } = await createWorkspaceViaUi(browser, {
    workspaceName,
    ownerEmail,
    ownerPassword,
    firstName: 'Free',
    lastName: 'Owner'
  });

  const tenantId = tenantIdByOwnerEmail(ownerEmail);
  expect(tenantId).toBeGreaterThan(0);
  dbQuery(`UPDATE tenants SET status = 'canceled', subscription_status = 'canceled', blocked_at = NOW(), suspended_at = NOW() WHERE id = ${tenantId};`);

  await page.goto('/index.php?page=tickets');
  await expect(page).toHaveURL(/page=billing/);
  await expect(page.locator('body')).toContainText('This plan was canceled');

  const platformContext = await browser.newContext({ baseURL: platformBaseURL });
  const platformPage = await platformContext.newPage();
  await login(platformPage);

  dbQuery(`UPDATE tenants SET status = 'active', subscription_status = 'active', blocked_at = NULL, suspended_at = NULL WHERE id = ${tenantId};`);
  await platformPage.goto(`/index.php?page=platform&tenant_id=${tenantId}#tenant-detail`);
  const activeDetail = platformPage.locator('#tenant-detail');
  await expect(activeDetail.getByRole('button', { name: 'Free access' })).toHaveCount(1);
  await expect(activeDetail.getByRole('button', { name: 'Block workspace' })).toHaveCount(1);
  await expect(activeDetail.getByRole('button', { name: 'Extend trial' })).toHaveCount(0);
  await expect(activeDetail.getByRole('button', { name: 'Reactivate' })).toHaveCount(0);

  dbQuery(`UPDATE tenants SET status = 'canceled', subscription_status = 'canceled', blocked_at = NOW(), suspended_at = NOW() WHERE id = ${tenantId};`);
  await platformPage.goto('/index.php?page=platform');
  const workspaceRow = platformPage.locator('[data-workspace-row]').filter({ hasText: workspaceName });
  await expect(workspaceRow).toHaveCount(1);
  await workspaceRow.getByRole('button', { name: 'Free access' }).click();
  await expect(platformPage.locator('body')).toContainText('Workspace marked free by platform override.');
  await expect(workspaceRow.getByRole('button', { name: 'Free access' })).toHaveCount(0);
  await expect(workspaceRow.getByRole('button', { name: '+7d trial' })).toHaveCount(0);
  await expect(workspaceRow.getByRole('button', { name: 'Reactivate' })).toHaveCount(0);
  await expect(workspaceRow.getByRole('button', { name: 'Block' })).toHaveCount(1);

  const output = dbQuery(`
    SELECT status, subscription_status, suspended_at IS NULL AS no_suspended_at, blocked_at IS NULL AS no_blocked_at
    FROM tenants
    WHERE id = ${tenantId}
    LIMIT 1;
  `);
  expect(output).toContain('active\tfree\t1\t1');

  await platformPage.goto(`/index.php?page=platform&tenant_id=${tenantId}#tenant-detail`);
  const freeDetail = platformPage.locator('#tenant-detail');
  await expect(freeDetail.getByRole('button', { name: 'Free access' })).toHaveCount(0);
  await expect(freeDetail.getByRole('button', { name: 'Extend trial' })).toHaveCount(0);
  await expect(freeDetail.getByRole('button', { name: 'Reactivate' })).toHaveCount(0);
  await expect(freeDetail.getByRole('button', { name: 'Block workspace' })).toHaveCount(1);

  await page.goto('/index.php?page=tickets');
  await expect(page).not.toHaveURL(/page=billing/);
  await page.goto('/index.php?page=billing');
  await expect(page.locator('body')).toContainText('Included access');
  await expect(page.locator('body')).toContainText('Your workspace has active access');
  await expect(page.getByRole('button', { name: 'Start plan' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Restart plan' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Add billing' })).toHaveCount(0);

  await platformContext.close();
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

  const platformContext = await browser.newContext({ baseURL: platformBaseURL });
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

test('migration bridge syncs attachments once and records cutover evidence', async ({ page }) => {
  const stamp = Date.now();
  const token = `fdmig_${crypto.randomBytes(24).toString('hex')}`;
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  const sourceTicketId = 810000 + Math.floor(stamp % 100000);
  const sourceAttachmentId = sourceTicketId + 1;
  const ticketHash = `mig${stamp}`.slice(0, 16);
  const payload = Buffer.from(`migration attachment runtime ${stamp}\n`, 'utf8');
  const checksum = crypto.createHash('sha256').update(payload).digest('hex');

  dockerExec(webContainer, [
    'php',
    '-r',
    `
      define('BASE_PATH', '/var/www/html');
      require_once BASE_PATH . '/config.php';
      require_once BASE_PATH . '/includes/database.php';
      require_once BASE_PATH . '/includes/tenant-functions.php';
      require_once BASE_PATH . '/includes/migration-functions.php';
      migration_bridge_ensure_connections_table();
      migration_bridge_ensure_object_map_table();
    `
  ]);

  const admin = rowObject(dbQuery(`
    SELECT id, tenant_id
    FROM users
    WHERE email = 'admin@example.test'
    LIMIT 1;
  `));
  const status = rowObject(dbQuery(`
    SELECT id
    FROM statuses
    WHERE is_default = 1
    ORDER BY sort_order ASC, id ASC
    LIMIT 1;
  `));

  dbQuery(`
    INSERT INTO migration_connections (tenant_id, token_hash, label, status, created_by, created_at, expires_at)
    VALUES (${Number(admin.tenant_id)}, ${sqlString(tokenHash)}, 'E2E attachment sync', 'issued', ${Number(admin.id)}, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY));
  `);
  const connection = rowObject(dbQuery(`
    SELECT id
    FROM migration_connections
    WHERE token_hash = ${sqlString(tokenHash)}
    LIMIT 1;
  `));

  dbQuery(`
    INSERT INTO tickets (tenant_id, hash, title, description, user_id, status_id, created_at, updated_at)
    VALUES (${Number(admin.tenant_id)}, ${sqlString(ticketHash)}, ${sqlString(`Migration attachment target ${stamp}`)}, 'Migration attachment target', ${Number(admin.id)}, ${Number(status.id)}, NOW(), NOW());
  `);
  const targetTicket = rowObject(dbQuery(`
    SELECT id
    FROM tickets
    WHERE hash = ${sqlString(ticketHash)}
    LIMIT 1;
  `));
  dbQuery(`
    INSERT INTO migration_object_map (connection_id, tenant_id, source_table, source_id, target_id, row_hash, created_at)
    VALUES (
      ${Number(connection.id)},
      ${Number(admin.tenant_id)},
      'tickets',
      ${sourceTicketId},
      ${Number(targetTicket.id)},
      ${sqlString(crypto.createHash('sha256').update(String(sourceTicketId)).digest('hex'))},
      NOW()
    );
  `);

  const metadata = {
    id: sourceAttachmentId,
    ticket_id: sourceTicketId,
    filename: `self-hosted-${sourceAttachmentId}.txt`,
    original_name: `self-hosted-${sourceAttachmentId}.txt`,
    mime_type: 'text/plain',
    file_size: payload.length,
    uploaded_by: 999999,
    created_at: '2026-06-22 12:00:00'
  };

  const uploadOnce = async () => {
    const response = await page.request.post('/index.php?page=api&action=migration-push-attachment', {
      headers: {
        Authorization: `Bearer ${token}`
      },
      multipart: {
        metadata: JSON.stringify(metadata),
        checksum,
        file: {
          name: metadata.original_name,
          mimeType: 'text/plain',
          buffer: payload
        }
      }
    });
    expect(response.ok()).toBeTruthy();
    return response.json();
  };

  const first = await uploadOnce();
  expect(first.success).toBe(true);
  expect(first.mapped).toBe(false);
  expect(first.attachment.created).toBe(true);
  expect(first.attachment.attachment_id).toBeGreaterThan(0);
  expect(first.attachment_sync.count_increment).toBe(1);
  expect(first.attachment_sync.bytes_increment).toBe(payload.length);

  let attachment = rowObject(dbQuery(`
    SELECT tenant_id, ticket_id, original_name, file_size, storage_driver, storage_key
    FROM attachments
    WHERE id = ${Number(first.attachment.attachment_id)}
    LIMIT 1;
  `));
  expect(Number(attachment.tenant_id)).toBe(Number(admin.tenant_id));
  expect(Number(attachment.ticket_id)).toBe(Number(targetTicket.id));
  expect(attachment.original_name).toBe(metadata.original_name);
  expect(Number(attachment.file_size)).toBe(payload.length);
  expect(attachment.storage_driver).toBe('local');
  expect(attachment.storage_key).toMatch(/^uploads\/migrated_/);

  let evidence = rowObject(dbQuery(`
    SELECT attachment_sync_count, attachment_sync_bytes, attachment_sync_last_checksum, attachment_sync_last_source_id
    FROM migration_connections
    WHERE id = ${Number(connection.id)}
    LIMIT 1;
  `));
  expect(Number(evidence.attachment_sync_count)).toBe(1);
  expect(Number(evidence.attachment_sync_bytes)).toBe(payload.length);
  expect(evidence.attachment_sync_last_checksum).toBe(checksum);
  expect(Number(evidence.attachment_sync_last_source_id)).toBe(sourceAttachmentId);

  const second = await uploadOnce();
  expect(second.success).toBe(true);
  expect(second.mapped).toBe(true);
  expect(second.attachment.mapped).toBe(true);
  expect(second.attachment.created).toBe(false);
  expect(Number(second.attachment.attachment_id)).toBe(Number(first.attachment.attachment_id));
  expect(second.attachment_sync.count_increment).toBe(0);
  expect(second.attachment_sync.bytes_increment).toBe(0);

  evidence = rowObject(dbQuery(`
    SELECT attachment_sync_count, attachment_sync_bytes, attachment_sync_last_checksum, attachment_sync_last_source_id
    FROM migration_connections
    WHERE id = ${Number(connection.id)}
    LIMIT 1;
  `));
  expect(Number(evidence.attachment_sync_count)).toBe(1);
  expect(Number(evidence.attachment_sync_bytes)).toBe(payload.length);
  expect(evidence.attachment_sync_last_checksum).toBe(checksum);
  expect(Number(evidence.attachment_sync_last_source_id)).toBe(sourceAttachmentId);

  const mapped = rowObject(dbQuery(`
    SELECT target_id
    FROM migration_object_map
    WHERE connection_id = ${Number(connection.id)} AND source_table = 'attachments' AND source_id = ${sourceAttachmentId}
    LIMIT 1;
  `));
  expect(Number(mapped.target_id)).toBe(Number(first.attachment.attachment_id));
});
