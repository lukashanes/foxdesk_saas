const crypto = require('crypto');
const { test, expect, request } = require('@playwright/test');
const { baseURL } = require('./env');
const { dbQuery, php, getCsrf, login } = require('./helpers');

function sqlString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

async function loginAs(page, email, password) {
  await page.goto('/index.php?page=login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=work|page=dashboard|dashboard/);
}

function rowObject(output) {
  const lines = output.trim().split('\n');
  const headers = lines[0].split('\t');
  const values = lines[1].split('\t');
  return Object.fromEntries(headers.map((header, index) => [header, values[index]]));
}

function seedPermissionFixture() {
  const password = 'AgentScope123!';
  const hash = php(`echo password_hash(${JSON.stringify(password)}, PASSWORD_DEFAULT);`).trim();

  dbQuery("DELETE FROM api_tokens WHERE token_prefix = 'e2e-agent'");
  dbQuery("DELETE FROM tickets WHERE hash IN ('e2ealpha00000001', 'e2ebeta00000001')");
  dbQuery("DELETE FROM users WHERE email IN ('agent.scope@example.test', 'beta.client@example.test')");
  dbQuery("DELETE FROM organizations WHERE name IN ('E2E Alpha Scope', 'E2E Beta Scope')");
  const tenant = rowObject(dbQuery("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1"));
  const tenantId = Number(tenant.id);

  dbQuery(`
    INSERT INTO organizations (tenant_id, name, is_active, created_at)
    VALUES (${tenantId}, 'E2E Alpha Scope', 1, NOW())
  `);
  dbQuery(`
    INSERT INTO organizations (tenant_id, name, is_active, created_at)
    VALUES (${tenantId}, 'E2E Beta Scope', 1, NOW())
  `);

  const ids = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM organizations WHERE name = 'E2E Alpha Scope' LIMIT 1) AS alpha_org_id,
      (SELECT id FROM organizations WHERE name = 'E2E Beta Scope' LIMIT 1) AS beta_org_id,
      (SELECT id FROM statuses ORDER BY sort_order ASC, id ASC LIMIT 1) AS status_id,
      (SELECT id FROM priorities ORDER BY is_default DESC, sort_order ASC, id ASC LIMIT 1) AS priority_id
  `));

  const alphaOrgId = Number(ids.alpha_org_id);
  const betaOrgId = Number(ids.beta_org_id);
  const statusId = Number(ids.status_id);
  const priorityId = Number(ids.priority_id);
  const permissions = {
    ticket_scope: 'organization',
    organization_ids: [alphaOrgId],
    can_view_time: true,
    can_view_timeline: true,
    can_archive: false,
    can_import_md: false,
    can_view_edit_history: false
  };

  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, permissions, organization_id, is_active, created_at)
    VALUES (
      ${tenantId},
      'agent.scope@example.test',
      ${sqlString(hash)},
      'Agent',
      'Scope',
      'agent',
      ${sqlString(JSON.stringify(permissions))},
      ${alphaOrgId},
      1,
      NOW()
    )
    ON DUPLICATE KEY UPDATE
      password = VALUES(password),
      role = 'agent',
      permissions = VALUES(permissions),
      organization_id = VALUES(organization_id),
      is_active = 1,
      deleted_at = NULL
  `);

  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, permissions, organization_id, is_active, created_at)
    VALUES (
      ${tenantId},
      'beta.client@example.test',
      ${sqlString(hash)},
      'Beta',
      'Client',
      'user',
      ${sqlString(JSON.stringify({ ticket_scope: 'organization', organization_ids: [betaOrgId] }))},
      ${betaOrgId},
      1,
      NOW()
    )
    ON DUPLICATE KEY UPDATE
      password = VALUES(password),
      role = 'user',
      permissions = VALUES(permissions),
      organization_id = VALUES(organization_id),
      is_active = 1,
      deleted_at = NULL
  `);

  const userIds = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM users WHERE email = 'agent.scope@example.test' LIMIT 1) AS agent_id,
      (SELECT id FROM users WHERE email = 'beta.client@example.test' LIMIT 1) AS beta_user_id
  `));

  const agentId = Number(userIds.agent_id);
  const betaUserId = Number(userIds.beta_user_id);

  dbQuery(`
    INSERT INTO tickets (tenant_id, hash, title, description, type, priority_id, user_id, organization_id, status_id, tags, is_archived, created_at)
    VALUES
      (${tenantId}, 'e2ealpha00000001', 'Alpha Scope Visible Ticket', 'Only alpha scoped agents can see this', 'general', ${priorityId}, ${agentId}, ${alphaOrgId}, ${statusId}, 'alpha-visible', 0, NOW()),
      (${tenantId}, 'e2ebeta00000001', 'Beta Scope Hidden Ticket', 'This must not leak to alpha scoped agents', 'general', ${priorityId}, ${betaUserId}, ${betaOrgId}, ${statusId}, 'beta-hidden', 0, NOW())
    ON DUPLICATE KEY UPDATE
      title = VALUES(title),
      description = VALUES(description),
      user_id = VALUES(user_id),
      organization_id = VALUES(organization_id),
      status_id = VALUES(status_id),
      tags = VALUES(tags),
      is_archived = 0
  `);

  const betaTicket = rowObject(dbQuery("SELECT id FROM tickets WHERE hash = 'e2ebeta00000001' LIMIT 1"));
  const token = 'e2e-agent-scope-token';
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  dbQuery(`
    INSERT INTO api_tokens (tenant_id, user_id, name, token_hash, token_prefix, is_active, created_at)
    VALUES (${tenantId}, ${agentId}, 'E2E scope token', '${tokenHash}', 'e2e-agent', 1, NOW())
    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), is_active = 1
  `);

  return {
    password,
    agentEmail: 'agent.scope@example.test',
    alphaOrgId,
    betaOrgId,
    betaUserId,
    betaTicketCode: `TK-${String(Number(betaTicket.id) + 10000).padStart(5, '0')}`,
    token
  };
}

test('concurrent idempotent API requests create one ticket', async () => {
  const fixture = seedPermissionFixture();
  const title = `Concurrent idempotency ${Date.now()}`;
  const idempotencyKey = `e2e-idempotency-${crypto.randomUUID()}`;
  const api = await request.newContext({
    baseURL,
    extraHTTPHeaders: {
      Authorization: `Bearer ${fixture.token}`,
      'Content-Type': 'application/json'
    }
  });

  try {
    const options = {
      headers: { 'Idempotency-Key': idempotencyKey },
      data: {
        title,
        description: 'Concurrent retry safety check',
        organization_id: fixture.alphaOrgId,
        skip_notification: true
      }
    };
    const [first, second] = await Promise.all([
      api.post('/index.php?page=api&action=app-create-ticket', options),
      api.post('/index.php?page=api&action=app-create-ticket', options)
    ]);
    const responses = [first, second];
    const statuses = responses.map(response => response.status());

    expect(statuses.some(status => status === 200)).toBe(true);
    expect(statuses.every(status => status === 200 || status === 409)).toBe(true);

    const successfulBodies = [];
    for (const response of responses) {
      if (response.status() === 200) {
        successfulBodies.push(await response.json());
      }
    }
    expect(successfulBodies.every(body => body.success === true)).toBe(true);
    expect(new Set(successfulBodies.map(body => body.ticket_id)).size).toBe(1);

    const count = rowObject(dbQuery(`
      SELECT COUNT(*) AS ticket_count
      FROM tickets
      WHERE title = ${sqlString(title)}
    `));
    expect(Number(count.ticket_count)).toBe(1);
  } finally {
    await api.dispose();
    dbQuery(`DELETE FROM tickets WHERE title = ${sqlString(title)}`);
  }
});

test('mobile refresh tokens are single-use under concurrent retries', async () => {
  const fixture = seedPermissionFixture();
  const api = await request.newContext({ baseURL });

  try {
    const loginResponse = await api.post('/index.php?page=api&action=mobile-login', {
      data: {
        email: fixture.agentEmail,
        password: fixture.password,
        device_id: `e2e-refresh-${crypto.randomUUID()}`,
        device_name: 'Playwright iPhone',
        app_version: '0.1.0-e2e'
      }
    });
    expect(loginResponse.status()).toBe(200);
    const login = await loginResponse.json();
    const refreshToken = login?.session?.refresh_token;
    expect(typeof refreshToken).toBe('string');
    expect(refreshToken.length).toBeGreaterThan(20);

    const refreshRequest = {
      data: {
        refresh_token: refreshToken,
        device_name: 'Playwright iPhone',
        app_version: '0.1.0-e2e'
      }
    };
    const responses = await Promise.all([
      api.post('/index.php?page=api&action=mobile-refresh', refreshRequest),
      api.post('/index.php?page=api&action=mobile-refresh', refreshRequest)
    ]);

    expect(responses.map((response) => response.status()).sort()).toEqual([200, 401]);
    const success = responses.find((response) => response.status() === 200);
    const rejected = responses.find((response) => response.status() === 401);
    expect((await success.json())?.session?.refresh_token).not.toBe(refreshToken);
    expect(await rejected.text()).toMatch(/already used|expired|invalid|unauthorized/i);
  } finally {
    await api.dispose();
  }
});

function seedTenantIsolationFixture() {
  dbQuery("DELETE FROM tickets WHERE hash = 'e2etenantb0001'");
  dbQuery("DELETE FROM users WHERE email = 'tenant-b-admin@example.test'");
  dbQuery("DELETE FROM organizations WHERE name = 'E2E Tenant B Org'");
  dbQuery("DELETE FROM tenants WHERE slug = 'tenant-b-e2e'");

  dbQuery(`
    INSERT INTO tenants (uuid, name, slug, status, created_at)
    VALUES ('11111111-2222-4333-8444-555555555555', 'Tenant B E2E', 'tenant-b-e2e', 'active', NOW())
  `);
  const tenant = rowObject(dbQuery("SELECT id FROM tenants WHERE slug = 'tenant-b-e2e' LIMIT 1"));
  const tenantId = Number(tenant.id);
  const passwordHash = php("echo password_hash('TenantBPass123!', PASSWORD_DEFAULT);").trim();
  const ids = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM statuses ORDER BY sort_order ASC, id ASC LIMIT 1) AS status_id,
      (SELECT id FROM priorities ORDER BY is_default DESC, sort_order ASC, id ASC LIMIT 1) AS priority_id
  `));

  dbQuery(`
    INSERT INTO organizations (tenant_id, name, is_active, created_at)
    VALUES (${tenantId}, 'E2E Tenant B Org', 1, NOW())
  `);
  const org = rowObject(dbQuery("SELECT id FROM organizations WHERE name = 'E2E Tenant B Org' LIMIT 1"));

  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, is_active, organization_id, created_at)
    VALUES (${tenantId}, 'tenant-b-admin@example.test', ${sqlString(passwordHash)}, 'TenantB', 'Admin', 'admin', 1, ${Number(org.id)}, NOW())
  `);
  const user = rowObject(dbQuery("SELECT id FROM users WHERE email = 'tenant-b-admin@example.test' LIMIT 1"));

  dbQuery(`
    INSERT INTO tickets (tenant_id, hash, title, description, type, priority_id, user_id, organization_id, status_id, tags, is_archived, created_at)
    VALUES (${tenantId}, 'e2etenantb0001', 'Tenant B Confidential Ticket', 'Must not be visible to default tenant admin', 'general', ${Number(ids.priority_id)}, ${Number(user.id)}, ${Number(org.id)}, ${Number(ids.status_id)}, 'tenant-b-hidden', 0, NOW())
  `);
  dbQuery(`
    INSERT INTO page_views (tenant_id, user_id, page, section, created_at)
    VALUES (${tenantId}, ${Number(user.id)}, 'admin', 'tenant-b-secret-activity', NOW())
  `);

  const ticket = rowObject(dbQuery("SELECT id FROM tickets WHERE hash = 'e2etenantb0001' LIMIT 1"));
  return {
    ticketCode: `TK-${String(Number(ticket.id) + 10000).padStart(5, '0')}`,
    tenantId,
    orgId: Number(org.id),
    userId: Number(user.id)
  };
}

test('organization-scoped agent search and tags do not leak other organizations', async ({ page }) => {
  const fixture = seedPermissionFixture();
  await loginAs(page, fixture.agentEmail, fixture.password);

  const search = await page.request.get('/index.php?page=api&action=search-tickets&q=Beta%20Scope');
  expect(search.status()).toBe(200);
  expect(await search.json()).toMatchObject({ success: true, tickets: [] });

  const exactCodeSearch = await page.request.get(`/index.php?page=api&action=search-tickets&q=${fixture.betaTicketCode}`);
  expect(exactCodeSearch.status()).toBe(200);
  expect(await exactCodeSearch.json()).toMatchObject({ success: true, tickets: [] });

  const tags = await page.request.get('/index.php?page=api&action=get-tags');
  expect(tags.status()).toBe(200);
  const tagNames = (await tags.json()).tags.map(tag => tag.name);
  expect(tagNames).toContain('alpha-visible');
  expect(tagNames).not.toContain('beta-hidden');
});

test('organization-scoped agent cannot create tickets for hidden users or organizations', async ({ page }) => {
  const fixture = seedPermissionFixture();
  await loginAs(page, fixture.agentEmail, fixture.password);

  await page.goto('/index.php?page=dashboard');
  const csrf = await getCsrf(page);
  const quickCreate = await page.request.post('/index.php?page=api&action=quick-create-ticket', {
    form: {
      csrf_token: csrf,
      title: 'Forbidden beta quick ticket',
      organization_id: String(fixture.betaOrgId)
    }
  });
  expect(quickCreate.status()).toBe(403);

  const api = await request.newContext({
    baseURL,
    extraHTTPHeaders: {
      Authorization: `Bearer ${fixture.token}`,
      'Content-Type': 'application/json'
    }
  });
  const hiddenOwner = await api.post('/index.php?page=api&action=agent-create-ticket', {
    data: {
      title: 'Forbidden beta owner',
      user_id: fixture.betaUserId,
      organization_id: fixture.betaOrgId
    }
  });
  expect(hiddenOwner.status()).toBe(403);
  await api.dispose();
});

test('default tenant admin cannot search tickets from another tenant', async ({ page }) => {
  const fixture = seedTenantIsolationFixture();
  await login(page);

  await page.goto('/index.php?page=dashboard');
  await expect(page.locator('body')).not.toContainText('Tenant B Confidential Ticket');

  const titleSearch = await page.request.get('/index.php?page=api&action=search-tickets&q=Tenant%20B%20Confidential');
  expect(titleSearch.status()).toBe(200);
  expect(await titleSearch.json()).toMatchObject({ success: true, tickets: [] });

  const codeSearch = await page.request.get(`/index.php?page=api&action=search-tickets&q=${fixture.ticketCode}`);
  expect(codeSearch.status()).toBe(200);
  expect(await codeSearch.json()).toMatchObject({ success: true, tickets: [] });
});

test('default tenant admin cannot mutate another tenant users or organizations', async ({ page }) => {
  const fixture = seedTenantIsolationFixture();
  await login(page);

  await page.goto('/index.php?page=admin&section=users');
  const csrf = await getCsrf(page);

  await page.request.post('/index.php?page=admin&section=users', {
    form: {
      csrf_token: csrf,
      update_user: '1',
      id: String(fixture.userId),
      email: 'tenant-b-admin@example.test',
      first_name: 'Hacked',
      last_name: 'CrossTenant',
      role: 'user',
      is_active: '1',
      organization_id: ''
    }
  });

  await page.request.post('/index.php?page=admin&section=organizations', {
    form: {
      csrf_token: csrf,
      update: '1',
      id: String(fixture.orgId),
      name: 'Hacked Tenant B Org',
      ico: '',
      address: '',
      contact_email: '',
      contact_phone: '',
      notes: '',
      billable_rate: '0'
    }
  });

  const user = rowObject(dbQuery(`SELECT first_name, last_name, role FROM users WHERE id = ${fixture.userId}`));
  expect(user.first_name).toBe('TenantB');
  expect(user.last_name).toBe('Admin');
  expect(user.role).toBe('admin');

  const org = rowObject(dbQuery(`SELECT name FROM organizations WHERE id = ${fixture.orgId}`));
  expect(org.name).toBe('E2E Tenant B Org');
});

test('default tenant admin activity page does not include another tenant activity', async ({ page }) => {
  seedTenantIsolationFixture();
  await login(page);

  await page.goto('/index.php?page=admin&section=activity&tab=log&range=30');
  await expect(page.locator('body')).not.toContainText('tenant-b-secret-activity');
});
