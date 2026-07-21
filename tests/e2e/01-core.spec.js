const fs = require('fs');
const crypto = require('crypto');
const os = require('os');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { dbQuery, php, login, getCsrf, dockerExec } = require('./helpers');
const { baseURL, webContainer } = require('./env');

function sqlString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function rowObject(output) {
  const lines = output.trim().split('\n');
  const headers = lines[0].split('\t');
  const values = lines[1].split('\t');
  return Object.fromEntries(headers.map((header, index) => [header, values[index]]));
}

function seedDueRecurringTask(title, description = 'Created by recurring runtime E2E.') {
  const adminRow = rowObject(dbQuery(`
    SELECT id, tenant_id
    FROM users
    WHERE email = 'admin@example.test'
    LIMIT 1;
  `));
  const statusRow = rowObject(dbQuery(`
    SELECT id
    FROM statuses
    WHERE is_default = 1
    ORDER BY sort_order ASC, id ASC
    LIMIT 1;
  `));

  dbQuery(`
    INSERT INTO recurring_tasks (
      tenant_id,
      title,
      description,
      status_id,
      recurrence_type,
      recurrence_interval,
      start_date,
      next_run_date,
      is_active,
      send_email_notification,
      created_by_user_id,
      created_at
    ) VALUES (
      ${Number(adminRow.tenant_id)},
      ${sqlString(title)},
      ${sqlString(description)},
      ${Number(statusRow.id)},
      'daily',
      1,
      DATE_SUB(CURDATE(), INTERVAL 2 DAY),
      DATE_SUB(NOW(), INTERVAL 10 MINUTE),
      1,
      0,
      ${Number(adminRow.id)},
      NOW()
    );
  `);

  return rowObject(dbQuery(`
    SELECT id
    FROM recurring_tasks
    WHERE title = ${sqlString(title)}
    LIMIT 1;
  `));
}

test('admin can log in and see the work home', async ({ page }) => {
  await login(page);
  await expect(page).toHaveURL(/page=work|page=dashboard|dashboard|page=platform/);
  await page.goto('/index.php?page=work');
  await expect(page.locator('body')).toContainText('Dashboard');
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

test('opening and cancelling New ticket does not create a ticket or start a timer', async ({ page }) => {
  const admin = rowObject(dbQuery(`
    SELECT id, tenant_id
    FROM users
    WHERE email = 'admin@example.test'
    LIMIT 1
  `));
  const before = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE tenant_id = ${Number(admin.tenant_id)}) AS ticket_count,
      (SELECT COUNT(*) FROM ticket_time_entries WHERE user_id = ${Number(admin.id)} AND ended_at IS NULL) AS active_timer_count
  `));

  await login(page);
  const newTicket = page.locator('a[href*="page=new-ticket"]').filter({ hasText: 'New ticket' }).first();
  await expect(newTicket).toBeVisible();
  await expect(page.locator('[data-quick-start-work]')).toHaveCount(0);
  await newTicket.click();
  await page.waitForURL(/page=new-ticket/);
  await expect(page.locator('form#new-ticket-form')).toBeVisible();

  await page.goBack();

  const after = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE tenant_id = ${Number(admin.tenant_id)}) AS ticket_count,
      (SELECT COUNT(*) FROM ticket_time_entries WHERE user_id = ${Number(admin.id)} AND ended_at IS NULL) AS active_timer_count
  `));
  expect(Number(after.ticket_count)).toBe(Number(before.ticket_count));
  expect(Number(after.active_timer_count)).toBe(Number(before.active_timer_count));
});

test('admin can create a ticket, upload, preview, download, and delete attachments', async ({ page }) => {
  const attachmentPath = path.join(os.tmpdir(), 'foxdesk-e2e-attachment.txt');
  fs.writeFileSync(attachmentPath, 'hello from foxdesk e2e\n');
  const imagePath = path.join(os.tmpdir(), 'foxdesk-e2e-preview.png');
  fs.writeFileSync(imagePath, Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
    'base64'
  ));

  await login(page);
  await page.goto('/index.php?page=new-ticket');
  await expect(page.locator('body')).toContainText('New ticket');

  await page.locator('input[name="title"]').fill('E2E ticket with attachment');
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Created by Playwright E2E.</p>';
  });
  await page.locator('#file-input').setInputFiles([attachmentPath, imagePath]);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);
  const ticketId = Number(new URL(page.url()).searchParams.get('id'));

  await expect(page.locator('body')).toContainText('E2E ticket with attachment');
  await expect(page.locator('body')).toContainText('Attachments');
  await expect(page.locator('body')).toContainText('foxdesk-e2e-attachment.txt');
  await expect(page.locator('body')).toContainText('foxdesk-e2e-preview.png');

  const attachmentHref = await page.locator('a[href*="attachment.php"]', { hasText: 'foxdesk-e2e-attachment.txt' }).first().getAttribute('href');
  expect(attachmentHref).toBeTruthy();
  const attachmentResponse = await page.request.get(attachmentHref);
  expect(attachmentResponse.ok()).toBeTruthy();
  expect(await attachmentResponse.text()).toContain('hello from foxdesk e2e');

  await page.getByRole('link', { name: 'foxdesk-e2e-preview.png' }).first().click();
  await expect(page.locator('#image-lightbox')).toBeVisible();
  await expect(page.locator('#lightbox-name')).toContainText('foxdesk-e2e-preview.png');
  await page.keyboard.press('Escape');
  await expect(page.locator('#image-lightbox')).not.toBeVisible();

  const sideAttachments = page.locator('details.ticket-side-section').filter({
    has: page.locator('summary', { hasText: 'Attachments' })
  }).first();
  await sideAttachments.evaluate(details => { details.open = true; });
  const deleteButton = sideAttachments.locator('.ticket-attachment-item', { hasText: 'foxdesk-e2e-attachment.txt' }).getByRole('button', { name: 'Delete attachment' });
  page.once('dialog', dialog => dialog.accept());
  await deleteButton.click();
  await expect(page.locator('body')).toContainText('Attachment deleted.');
  await expect(sideAttachments.locator('.ticket-attachment-item', { hasText: 'foxdesk-e2e-attachment.txt' })).toHaveCount(0);

  const remaining = rowObject(dbQuery(`
    SELECT COUNT(*) AS attachment_count
    FROM attachments
    WHERE ticket_id = ${ticketId} AND original_name = 'foxdesk-e2e-attachment.txt'
  `));
  expect(Number(remaining.attachment_count)).toBe(0);
});

test('complete action stops an active ticket timer', async ({ page }) => {
  const stamp = Date.now();
  const title = `E2E complete stops timer ${stamp}`;

  await login(page);
  await page.goto('/index.php?page=new-ticket');
  await page.locator('input[name="title"]').fill(title);
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Timer completion regression.</p>';
  });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);

  const ticketId = Number(new URL(page.url()).searchParams.get('id'));
  expect(ticketId).toBeGreaterThan(0);

  const startButton = page.locator('#toolbar-timer-btn');
  await expect(startButton).toBeVisible();
  await startButton.click();
  await expect(startButton).toHaveClass(/td-tool-btn--active-timer/);
  await expect(page.locator('form.ticket-primary-action-form button[name="change_status"]')).toContainText('Complete & stop timer');

  await page.locator('form.ticket-primary-action-form button[name="change_status"]').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('body')).toContainText('Ticket completed and timer stopped.');

  const timerState = rowObject(dbQuery(`
    SELECT
      SUM(CASE WHEN ended_at IS NULL THEN 1 ELSE 0 END) AS active_count,
      SUM(CASE WHEN ended_at IS NOT NULL THEN 1 ELSE 0 END) AS stopped_count
    FROM ticket_time_entries
    WHERE ticket_id = ${ticketId}
  `));
  expect(Number(timerState.active_count || 0)).toBe(0);
  expect(Number(timerState.stopped_count || 0)).toBeGreaterThan(0);
});

test('admin can add normalized tags and filter tickets by tag', async ({ page }) => {
  const stamp = Date.now();
  const title = `E2E tagged ticket ${stamp}`;

  await login(page);
  await page.goto('/index.php?page=new-ticket');
  await page.locator('input[name="title"]').fill(title);
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Tag workflow regression.</p>';
  });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);

  const ticketId = Number(new URL(page.url()).searchParams.get('id'));
  expect(ticketId).toBeGreaterThan(0);

  const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  expect(csrf).toBeTruthy();

  const updateResponse = await page.request.post('/index.php?page=api&action=update-tags', {
    headers: { 'X-CSRF-Token': csrf },
    form: {
      ticket_id: String(ticketId),
      tags: '#Urgent, urgent; customer  success'
    }
  });
  expect(updateResponse.ok()).toBeTruthy();
  const updateJson = await updateResponse.json();
  expect(updateJson.success).toBe(true);
  expect(updateJson.tags).toEqual(['Urgent', 'customer success']);

  const stored = rowObject(dbQuery(`
    SELECT tags
    FROM tickets
    WHERE id = ${ticketId}
    LIMIT 1
  `));
  expect(stored.tags).toBe('Urgent, customer success');

  const suggestionsResponse = await page.request.get('/index.php?page=api&action=get-tags');
  expect(suggestionsResponse.ok()).toBeTruthy();
  const suggestionsJson = await suggestionsResponse.json();
  expect(suggestionsJson.success).toBe(true);
  expect(suggestionsJson.tags.map(tag => tag.name)).toEqual(expect.arrayContaining(['Urgent', 'customer success']));

  await page.goto('/index.php?page=tickets&tags=Urgent');
  await expect(page.locator('body')).toContainText(title);
  await expect(page.locator('body')).toContainText('Urgent');

  await page.goto('/index.php?page=tickets&tags=not-present');
  await expect(page.locator('body')).not.toContainText(title);
});

test('ticket inline status menu stays anchored to the clicked row', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await login(page);
  await page.goto('/index.php?page=new-ticket');
  await page.locator('input[name="title"]').fill(`Popup anchor ${Date.now()}`);
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Status popup geometry test.</p>';
  });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);
  await page.goto('/index.php?page=tickets');

  const trigger = page.locator('.tl-edit-trigger[data-field="status"]').first();
  await expect(trigger).toBeVisible();
  await trigger.scrollIntoViewIfNeeded();
  await trigger.click();

  const ticketId = await trigger.getAttribute('data-ticket');
  const dropdown = page.locator(`[data-dropdown="status-${ticketId}"]`);
  await expect(dropdown).toBeVisible();

  const geometry = await page.evaluate(({ ticketId }) => {
    const triggerNode = document.querySelector(`.tl-edit-trigger[data-field="status"][data-ticket="${ticketId}"]`);
    const dropdownNode = document.querySelector(`[data-dropdown="status-${ticketId}"]`);
    if (!triggerNode || !dropdownNode) return null;
    const triggerRect = triggerNode.getBoundingClientRect();
    const dropdownRect = dropdownNode.getBoundingClientRect();
    return {
      trigger: { left: triggerRect.left, top: triggerRect.top, bottom: triggerRect.bottom },
      dropdown: { left: dropdownRect.left, top: dropdownRect.top, bottom: dropdownRect.bottom, right: dropdownRect.right },
      viewport: { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight },
      position: getComputedStyle(dropdownNode).position
    };
  }, { ticketId });

  expect(geometry).not.toBeNull();
  expect(geometry.position).toBe('fixed');
  expect(Math.abs(geometry.dropdown.left - geometry.trigger.left)).toBeLessThanOrEqual(8);
  const opensBelow = Math.abs(geometry.dropdown.top - (geometry.trigger.bottom + 4)) <= 8;
  const opensAbove = Math.abs(geometry.dropdown.bottom - (geometry.trigger.top - 4)) <= 8;
  expect(opensBelow || opensAbove).toBe(true);
  expect(geometry.dropdown.left).toBeGreaterThanOrEqual(0);
  expect(geometry.dropdown.right).toBeLessThanOrEqual(geometry.viewport.width);
  expect(geometry.dropdown.top).toBeGreaterThanOrEqual(0);
  expect(geometry.dropdown.bottom).toBeLessThanOrEqual(geometry.viewport.height);
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

test('admin can manage profile preferences, request password setup link, and start 2FA setup', async ({ page }) => {
  const stamp = Date.now();
  const firstName = `Profile${stamp}`;
  const lastName = 'E2E';

  await login(page);
  await page.goto('/index.php?page=profile');
  await expect(page.locator('body')).toContainText('My profile');
  await expect(page.locator('body')).toContainText('Personal information');
  await expect(page.locator('body')).toContainText('Change password');
  await expect(page.locator('#two-factor-section')).toContainText('Two-factor authentication');

  await page.locator('input[name="first_name"]').fill(firstName);
  await page.locator('input[name="last_name"]').fill(lastName);
  await page.locator('select[name="language"]').selectOption('en');
  await page.locator('button[name="update_profile"]').click();
  await expect(page.locator('body')).toContainText('Profile updated.');

  let profileRow = rowObject(dbQuery(`
    SELECT first_name, last_name, language
    FROM users
    WHERE email = 'admin@example.test'
    LIMIT 1;
  `));
  expect(profileRow.first_name).toBe(firstName);
  expect(profileRow.last_name).toBe(lastName);
  expect(profileRow.language).toBe('en');

  await page.locator('#profile_in_app_notifications_enabled').setChecked(true);
  await page.locator('#profile_in_app_sound_enabled').setChecked(true);
  await page.locator('button[name="update_notifications"]').click();
  await expect(page.locator('body')).toContainText('Notification settings saved.');

  profileRow = rowObject(dbQuery(`
    SELECT in_app_notifications_enabled, in_app_sound_enabled
    FROM users
    WHERE email = 'admin@example.test'
    LIMIT 1;
  `));
  expect(profileRow.in_app_notifications_enabled).toBe('1');
  expect(profileRow.in_app_sound_enabled).toBe('1');

  await page.locator('button[name="send_password_setup_link"]').click();
  await expect(page.locator('body')).toContainText('Password setup link sent. Check your email.');

  const resetRow = rowObject(dbQuery(`
    SELECT reset_token REGEXP '^[a-f0-9]{64}$' AS has_hash,
      reset_token_expires > NOW() AS future_expiry
    FROM users
    WHERE email = 'admin@example.test'
    LIMIT 1;
  `));
  expect(resetRow.has_hash).toBe('1');
  expect(resetRow.future_expiry).toBe('1');

  await page.locator('button[name="start_2fa_setup"]').click();
  await expect(page).toHaveURL(/setup2fa=1/);
  await expect(page.locator('#two-factor-section')).toContainText('Verification code');
  await expect(page.locator('#setup-code')).toBeVisible();
  await expect(page.locator('#totp-qr-code')).toBeVisible();
});

test('admin can subscribe to tenant-scoped browser push notifications', async ({ page }) => {
  const stamp = Date.now();
  const endpoint = `https://push.example.test/subscription/${stamp}`;
  const subject = `Push E2E notification ${stamp}`;

  await login(page);
  await page.goto('/index.php?page=dashboard');
  const csrf = await getCsrf(page);
  expect(csrf).toBeTruthy();

  const vapidResponse = await page.request.get('/index.php?page=api&action=push-vapid-key');
  expect(vapidResponse.ok()).toBeTruthy();
  const vapidJson = await vapidResponse.json();
  expect(vapidJson.publicKey).toEqual(expect.any(String));
  expect(vapidJson.publicKey.length).toBeGreaterThan(20);

  const subscribeResponse = await page.request.post('/index.php?page=api&action=push-subscribe', {
    headers: { 'X-CSRF-Token': csrf },
    data: {
      endpoint,
      p256dh: `p256dh-${stamp}`,
      auth: `auth-${stamp}`
    }
  });
  expect(subscribeResponse.ok()).toBeTruthy();
  const subscribeJson = await subscribeResponse.json();
  expect(subscribeJson.success).toBe(true);

  const subscriptionRow = rowObject(dbQuery(`
    SELECT ps.user_id, ps.tenant_id, ps.p256dh, ps.auth_key, u.tenant_id AS user_tenant_id
    FROM push_subscriptions ps
    JOIN users u ON u.id = ps.user_id
    WHERE ps.endpoint = ${sqlString(endpoint)}
    LIMIT 1;
  `));
  expect(Number(subscriptionRow.user_id)).toBeGreaterThan(0);
  expect(subscriptionRow.tenant_id).toBe(subscriptionRow.user_tenant_id);
  expect(subscriptionRow.p256dh).toBe(`p256dh-${stamp}`);
  expect(subscriptionRow.auth_key).toBe(`auth-${stamp}`);

  const adminRow = rowObject(dbQuery(`
    SELECT id, tenant_id
    FROM users
    WHERE email = 'admin@example.test'
    LIMIT 1;
  `));
  const notificationData = JSON.stringify({
    ticket_subject: subject,
    actor_name: 'FoxDesk E2E'
  });
  dbQuery(`
    INSERT INTO notifications (tenant_id, user_id, ticket_id, type, actor_id, data, is_read, created_at)
    VALUES (${Number(adminRow.tenant_id)}, ${Number(adminRow.id)}, NULL, 'assigned_to_you', NULL, ${sqlString(notificationData)}, 0, NOW());
  `);

  const notificationsResponse = await page.request.get('/index.php?page=api&action=push-notifications');
  expect(notificationsResponse.ok()).toBeTruthy();
  const notificationsJson = await notificationsResponse.json();
  expect(notificationsJson.notifications.length).toBeGreaterThan(0);
  const notification = notificationsJson.notifications[0];
  expect(notification.title).toBe('Ticket Assigned');
  expect(notification.body).toContain(subject);
  expect(notification.url).toContain('/index.php?page=notifications');
  expect(notification.tag).toMatch(/^foxdesk-\d+$/);

  const unsubscribeResponse = await page.request.post('/index.php?page=api&action=push-unsubscribe', {
    headers: { 'X-CSRF-Token': csrf },
    data: { endpoint }
  });
  expect(unsubscribeResponse.ok()).toBeTruthy();
  const unsubscribeText = await unsubscribeResponse.text();
  expect(unsubscribeText).not.toContain('<br');
  const unsubscribeJson = JSON.parse(unsubscribeText);
  expect(unsubscribeJson.success).toBe(true);

  const remainingRow = rowObject(dbQuery(`
    SELECT COUNT(*) AS subscription_count
    FROM push_subscriptions
    WHERE endpoint = ${sqlString(endpoint)};
  `));
  expect(Number(remainingRow.subscription_count)).toBe(0);
});

test('page load triggers throttled pseudo-cron email fallback', async ({ page }) => {
  dbQuery(`
    INSERT INTO settings (setting_key, setting_value) VALUES
      ('pseudo_cron_enabled', '1'),
      ('pseudo_cron_last_email', '0'),
      ('pseudo_cron_last_email_attempt', '0'),
      ('pseudo_cron_email_inline_lock', '0'),
      ('imap_enabled', '0')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
  `);

  await page.goto('/index.php?page=login');

  const output = dbQuery("SELECT setting_value FROM settings WHERE setting_key = 'pseudo_cron_last_email' LIMIT 1;");
  const lastRun = Number(output.trim().split('\n').pop());
  expect(lastRun).toBeGreaterThan(0);
});

test('recurring task runner creates due tickets without duplicate runs', async () => {
  const stamp = Date.now();
  const title = `E2E recurring runtime ${stamp}`;
  const taskRow = seedDueRecurringTask(title);

  const runRecurring = () => JSON.parse(dockerExec(webContainer, ['php', 'bin/process-recurring-tasks.php', '--json']));

  const firstRun = runRecurring();
  expect(firstRun.ok).toBe(true);
  expect(Number(firstRun.processed)).toBeGreaterThanOrEqual(1);

  let state = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE title = ${sqlString(title)} AND source = 'recurring') AS ticket_count,
      (SELECT COUNT(*) FROM recurring_task_runs WHERE recurring_task_id = ${Number(taskRow.id)} AND status = 'success') AS run_count,
      (SELECT next_run_date > NOW() FROM recurring_tasks WHERE id = ${Number(taskRow.id)}) AS next_in_future,
      (SELECT last_run_date IS NOT NULL FROM recurring_tasks WHERE id = ${Number(taskRow.id)}) AS has_last_run
  `));
  expect(Number(state.ticket_count)).toBe(1);
  expect(Number(state.run_count)).toBe(1);
  expect(state.next_in_future).toBe('1');
  expect(state.has_last_run).toBe('1');

  const secondRun = runRecurring();
  expect(secondRun.ok).toBe(true);

  state = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE title = ${sqlString(title)} AND source = 'recurring') AS ticket_count,
      (SELECT COUNT(*) FROM recurring_task_runs WHERE recurring_task_id = ${Number(taskRow.id)} AND status = 'success') AS run_count
  `));
  expect(Number(state.ticket_count)).toBe(1);
  expect(Number(state.run_count)).toBe(1);

  dbQuery(`
    UPDATE recurring_tasks
    SET next_run_date = DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    WHERE id = ${Number(taskRow.id)};
  `);

  const thirdRun = runRecurring();
  expect(thirdRun.ok).toBe(true);

  state = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE title = ${sqlString(title)} AND source = 'recurring') AS ticket_count,
      (SELECT COUNT(*) FROM recurring_task_runs WHERE recurring_task_id = ${Number(taskRow.id)} AND status = 'success') AS run_count
  `));
  expect(Number(state.ticket_count)).toBe(2);
  expect(Number(state.run_count)).toBe(2);
});

test('background jobs preserve tenant isolation across workspaces', async () => {
  const stamp = Date.now();
  const secondTenantSlug = `jobs-${stamp}`;
  const secondAdminEmail = `jobs.admin.${stamp}@example.test`;
  const defaultTaskTitle = `E2E default tenant recurring ${stamp}`;
  const secondTaskTitle = `E2E second tenant recurring ${stamp}`;
  const defaultReportTitle = `E2E default tenant report ${stamp}`;
  const secondReportTitle = `E2E second tenant report ${stamp}`;

  php(`
    define('BASE_PATH', '/var/www/html');
    require_once BASE_PATH . '/config.php';
    require_once BASE_PATH . '/includes/database.php';
    require_once BASE_PATH . '/includes/tenant-functions.php';
    ensure_tenant_baseline();
    require_once BASE_PATH . '/includes/functions.php';
    ensure_recurring_task_columns();
    ensure_report_schedule_columns();
  `);

  const defaultAdmin = rowObject(dbQuery(`
    SELECT id, tenant_id FROM users WHERE email = 'admin@example.test' LIMIT 1;
  `));
  const defaultStatus = rowObject(dbQuery(`
    SELECT id FROM statuses WHERE is_default = 1 ORDER BY sort_order ASC, id ASC LIMIT 1;
  `));

  dbQuery(`
    INSERT INTO tenants (uuid, name, slug, status, subscription_status, created_at)
    VALUES (${sqlString(crypto.randomUUID())}, 'Jobs tenant', ${sqlString(secondTenantSlug)}, 'active', 'active', NOW());
  `);
  const secondTenant = rowObject(dbQuery(`
    SELECT id FROM tenants WHERE slug = ${sqlString(secondTenantSlug)} LIMIT 1;
  `));
  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, is_active, language, created_at)
    VALUES (
      ${Number(secondTenant.id)},
      ${sqlString(secondAdminEmail)},
      '$2y$10$abcdefghijklmnopqrstuuF0I9oWV6x3p4GmD0Yj6Hf8wd2Kx0D5u',
      'Jobs', 'Admin', 'admin', 1, 'en', NOW()
    );
    INSERT INTO organizations (tenant_id, name, is_active, created_at)
    VALUES
      (${Number(defaultAdmin.tenant_id)}, ${sqlString(`Default jobs org ${stamp}`)}, 1, NOW()),
      (${Number(secondTenant.id)}, ${sqlString(`Second jobs org ${stamp}`)}, 1, NOW());
  `);

  const organizations = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM organizations WHERE tenant_id = ${Number(defaultAdmin.tenant_id)} AND name = ${sqlString(`Default jobs org ${stamp}`)} LIMIT 1) AS default_org_id,
      (SELECT id FROM organizations WHERE tenant_id = ${Number(secondTenant.id)} AND name = ${sqlString(`Second jobs org ${stamp}`)} LIMIT 1) AS second_org_id,
      (SELECT id FROM users WHERE email = ${sqlString(secondAdminEmail)} LIMIT 1) AS second_admin_id;
  `));

  dbQuery(`
    INSERT INTO recurring_tasks (
      tenant_id, title, description, status_id, recurrence_type, recurrence_interval,
      start_date, next_run_date, is_active, send_email_notification, created_by_user_id, created_at
    ) VALUES
      (${Number(defaultAdmin.tenant_id)}, ${sqlString(defaultTaskTitle)}, 'Default tenant job', ${Number(defaultStatus.id)}, 'daily', 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, 0, ${Number(defaultAdmin.id)}, NOW()),
      (${Number(secondTenant.id)}, ${sqlString(secondTaskTitle)}, 'Second tenant job', ${Number(defaultStatus.id)}, 'daily', 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, 0, ${Number(organizations.second_admin_id)}, NOW());

    INSERT INTO report_templates (
      tenant_id, uuid, organization_id, created_by_user_id, title, date_from, date_to,
      is_draft, schedule_enabled, schedule_interval, schedule_day, schedule_next_due, created_at
    ) VALUES
      (${Number(defaultAdmin.tenant_id)}, ${sqlString(crypto.randomUUID())}, ${Number(organizations.default_org_id)}, ${Number(defaultAdmin.id)}, ${sqlString(defaultReportTitle)}, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), CURDATE(), 0, 1, 'monthly', 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), NOW()),
      (${Number(secondTenant.id)}, ${sqlString(crypto.randomUUID())}, ${Number(organizations.second_org_id)}, ${Number(organizations.second_admin_id)}, ${sqlString(secondReportTitle)}, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), CURDATE(), 0, 1, 'monthly', 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), NOW());
  `);

  const recurringResult = JSON.parse(dockerExec(webContainer, ['php', 'bin/process-recurring-tasks.php', '--json']));
  expect(recurringResult.ok).toBe(true);
  expect(Number(recurringResult.processed)).toBeGreaterThanOrEqual(2);

  expect(php(`
    define('BASE_PATH', '/var/www/html');
    require_once BASE_PATH . '/config.php';
    require_once BASE_PATH . '/includes/database.php';
    require_once BASE_PATH . '/includes/tenant-functions.php';
    ensure_tenant_baseline();
    require_once BASE_PATH . '/includes/functions.php';
    process_scheduled_reports();
    echo 'scheduled-ok';
  `)).toContain('scheduled-ok');

  const ticketState = rowObject(dbQuery(`
    SELECT
      (SELECT tenant_id FROM tickets WHERE title = ${sqlString(defaultTaskTitle)} AND source = 'recurring' LIMIT 1) AS default_ticket_tenant,
      (SELECT tenant_id FROM tickets WHERE title = ${sqlString(secondTaskTitle)} AND source = 'recurring' LIMIT 1) AS second_ticket_tenant;
  `));
  expect(Number(ticketState.default_ticket_tenant)).toBe(Number(defaultAdmin.tenant_id));
  expect(Number(ticketState.second_ticket_tenant)).toBe(Number(secondTenant.id));

  const reportState = rowObject(dbQuery(`
    SELECT
      (SELECT rs.tenant_id FROM report_snapshots rs JOIN report_templates rt ON rt.id = rs.report_template_id WHERE rt.title = ${sqlString(defaultReportTitle)} ORDER BY rs.id DESC LIMIT 1) AS default_snapshot_tenant,
      (SELECT rs.tenant_id FROM report_snapshots rs JOIN report_templates rt ON rt.id = rs.report_template_id WHERE rt.title = ${sqlString(secondReportTitle)} ORDER BY rs.id DESC LIMIT 1) AS second_snapshot_tenant;
  `));
  expect(Number(reportState.default_snapshot_tenant)).toBe(Number(defaultAdmin.tenant_id));
  expect(Number(reportState.second_snapshot_tenant)).toBe(Number(secondTenant.id));
});

test('maintenance and pseudo-cron runners process due work once per interval', async ({ page }) => {
  const stamp = Date.now();
  const maintenanceTitle = `E2E maintenance recurring ${stamp}`;
  const endpointTitle = `E2E cron endpoint recurring ${stamp}`;
  const maintenanceTask = seedDueRecurringTask(maintenanceTitle, 'Created by maintenance E2E.');
  const secret = `cron-secret-${stamp}`;
  const now = Math.floor(Date.now() / 1000);

  dbQuery(`
    INSERT INTO settings (setting_key, setting_value) VALUES
      ('update_check_enabled', '0'),
      ('pseudo_cron_secret', ${sqlString(secret)}),
      ('pseudo_cron_lock', '0'),
      ('pseudo_cron_last_email', '0'),
      ('pseudo_cron_last_email_attempt', '0'),
      ('pseudo_cron_last_recurring', '0'),
      ('pseudo_cron_last_due_check', ${sqlString(String(now))}),
      ('pseudo_cron_last_reports', ${sqlString(String(now))}),
      ('pseudo_cron_last_trial_expire', ${sqlString(String(now))}),
      ('pseudo_cron_last_billing_usage', ${sqlString(String(now))}),
      ('pseudo_cron_last_maintenance', ${sqlString(String(now))}),
      ('imap_enabled', '0')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
  `);

  const maintenance = JSON.parse(dockerExec(webContainer, ['php', 'bin/run-maintenance.php', '--json']));
  expect(maintenance.ok).toBe(true);
  expect(Number(maintenance.recurring_processed)).toBeGreaterThanOrEqual(1);
  expect(maintenance.email_ingest.status).toBe('disabled');
  expect(maintenance.update_check.status).toBe('disabled');
  expect(maintenance.billing_usage.status).toBe('disabled');

  let state = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE title = ${sqlString(maintenanceTitle)} AND source = 'recurring') AS ticket_count,
      (SELECT COUNT(*) FROM recurring_task_runs WHERE recurring_task_id = ${Number(maintenanceTask.id)} AND status = 'success') AS run_count,
      (SELECT setting_value REGEXP '^[0-9]+$' FROM settings WHERE setting_key = 'pseudo_cron_last_email') AS email_marked
  `));
  expect(Number(state.ticket_count)).toBe(1);
  expect(Number(state.run_count)).toBe(1);
  expect(state.email_marked).toBe('1');

  const secondMaintenance = JSON.parse(dockerExec(webContainer, ['php', 'bin/run-maintenance.php', '--json']));
  expect(secondMaintenance.ok).toBe(true);
  state = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE title = ${sqlString(maintenanceTitle)} AND source = 'recurring') AS ticket_count,
      (SELECT COUNT(*) FROM recurring_task_runs WHERE recurring_task_id = ${Number(maintenanceTask.id)} AND status = 'success') AS run_count
  `));
  expect(Number(state.ticket_count)).toBe(1);
  expect(Number(state.run_count)).toBe(1);

  const endpointTask = seedDueRecurringTask(endpointTitle, 'Created by cron endpoint E2E.');

  dbQuery(`
    UPDATE recurring_tasks
    SET next_run_date = DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    WHERE id = ${Number(endpointTask.id)};
    UPDATE settings SET setting_value = '0' WHERE setting_key IN ('pseudo_cron_last_recurring', 'pseudo_cron_last_email', 'pseudo_cron_lock');
  `);

  const forbidden = await page.request.get(`/index.php?page=cron&token=${encodeURIComponent('bad-' + secret)}`);
  expect(forbidden.status()).toBe(403);
  expect(await forbidden.text()).toContain('Forbidden');

  const endpointRun = await page.request.get(`/index.php?page=cron&token=${encodeURIComponent(secret)}`);
  expect(endpointRun.status()).toBe(200);
  expect(await endpointRun.text()).toContain('OK');

  state = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE title = ${sqlString(endpointTitle)} AND source = 'recurring') AS ticket_count,
      (SELECT COUNT(*) FROM recurring_task_runs WHERE recurring_task_id = ${Number(endpointTask.id)} AND status = 'success') AS run_count,
      (SELECT setting_value FROM settings WHERE setting_key = 'pseudo_cron_lock') AS lock_value,
      (SELECT CAST(setting_value AS UNSIGNED) > 0 FROM settings WHERE setting_key = 'pseudo_cron_last_recurring') AS recurring_marked
  `));
  expect(Number(state.ticket_count)).toBe(1);
  expect(Number(state.run_count)).toBe(1);
  expect(state.lock_value).toBe('0');
  expect(state.recurring_marked).toBe('1');

  const endpointSecondRun = await page.request.get(`/index.php?page=cron&token=${encodeURIComponent(secret)}`);
  expect(endpointSecondRun.status()).toBe(200);

  state = rowObject(dbQuery(`
    SELECT
      (SELECT COUNT(*) FROM tickets WHERE title = ${sqlString(endpointTitle)} AND source = 'recurring') AS ticket_count,
      (SELECT COUNT(*) FROM recurring_task_runs WHERE recurring_task_id = ${Number(endpointTask.id)} AND status = 'success') AS run_count
  `));
  expect(Number(state.ticket_count)).toBe(1);
  expect(Number(state.run_count)).toBe(1);
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
  const agentClientRateForm = page.locator('form').filter({
    has: page.locator('button[name="save_agent_client_rate"]')
  });
  await agentClientRateForm.locator('select[name="organization_id"]').selectOption(String(ids.org_id));
  await agentClientRateForm.locator('select[name="user_id"]').selectOption(String(ids.agent_id));
  await agentClientRateForm.locator('input[name="billable_rate"]').fill('750');
  await agentClientRateForm.locator('button[name="save_agent_client_rate"]').click();
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
