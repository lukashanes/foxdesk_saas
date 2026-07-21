const crypto = require('crypto');
const { test, expect, request } = require('@playwright/test');
const { baseURL, admin } = require('./env');
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

function seedPausedWorkspaceFixture() {
  const email = 'paused.workspace.admin@example.test';
  const password = 'PausedWorkspace123!';
  const tenantSlug = 'paused-workspace-e2e';
  const organizationName = 'Paused Workspace E2E';
  const passwordHash = php(`echo password_hash(${JSON.stringify(password)}, PASSWORD_DEFAULT);`).trim();

  dbQuery(`DELETE FROM users WHERE email = ${sqlString(email)}`);
  dbQuery(`DELETE FROM organizations WHERE name = ${sqlString(organizationName)}`);
  dbQuery(`DELETE FROM tenants WHERE slug = ${sqlString(tenantSlug)}`);
  dbQuery(`
    INSERT INTO tenants (
      uuid, name, slug, status, subscription_status, billing_email,
      suspended_at, blocked_at, created_at
    ) VALUES (
      '22222222-3333-4444-8555-666666666666',
      'Paused Workspace E2E',
      ${sqlString(tenantSlug)},
      'blocked',
      'blocked',
      ${sqlString(email)},
      NOW(),
      NOW(),
      NOW()
    )
  `);
  const tenant = rowObject(dbQuery(`SELECT id FROM tenants WHERE slug = ${sqlString(tenantSlug)} LIMIT 1`));
  const tenantId = Number(tenant.id);

  dbQuery(`
    INSERT INTO organizations (tenant_id, name, is_active, created_at)
    VALUES (${tenantId}, ${sqlString(organizationName)}, 1, NOW())
  `);
  const organization = rowObject(dbQuery(`SELECT id FROM organizations WHERE tenant_id = ${tenantId} LIMIT 1`));

  dbQuery(`
    INSERT INTO users (
      tenant_id, email, password, first_name, last_name, role,
      is_active, organization_id, created_at
    ) VALUES (
      ${tenantId},
      ${sqlString(email)},
      ${sqlString(passwordHash)},
      'Paused',
      'Admin',
      'admin',
      1,
      ${Number(organization.id)},
      NOW()
    )
  `);

  return { email, password, tenantId, organizationName, tenantSlug };
}

function seedPermanentDeleteFixture() {
  const suffix = Date.now();
  const ticketHash = `e2edel${String(suffix).slice(-8)}`;
  const title = `Permanent delete E2E ${suffix}`;
  const token = `e2e-permanent-delete-${crypto.randomUUID()}`;
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  const ids = rowObject(dbQuery(`
    SELECT
      (SELECT id FROM tenants WHERE slug = 'default' LIMIT 1) AS tenant_id,
      (SELECT id FROM users WHERE email = 'admin@example.test' LIMIT 1) AS admin_id,
      (SELECT id FROM organizations WHERE tenant_id = (SELECT id FROM tenants WHERE slug = 'default' LIMIT 1) ORDER BY id LIMIT 1) AS organization_id,
      (SELECT id FROM statuses ORDER BY is_default DESC, sort_order ASC, id ASC LIMIT 1) AS status_id,
      (SELECT id FROM priorities ORDER BY is_default DESC, sort_order ASC, id ASC LIMIT 1) AS priority_id
  `));
  const tenantId = Number(ids.tenant_id);
  const adminId = Number(ids.admin_id);
  const organizationId = ids.organization_id ? Number(ids.organization_id) : null;
  const organizationSql = Number.isInteger(organizationId) ? organizationId : 'NULL';

  dbQuery(`
    INSERT INTO tickets (
      tenant_id, hash, title, description, type, priority_id, user_id,
      organization_id, status_id, assignee_id, source, is_archived, created_at
    ) VALUES (
      ${tenantId}, ${sqlString(ticketHash)}, ${sqlString(title)},
      'Permanent deletion integration fixture', 'general', ${Number(ids.priority_id)},
      ${adminId}, ${organizationSql}, ${Number(ids.status_id)}, ${adminId}, 'api', 0, NOW()
    )
  `);
  const ticket = rowObject(dbQuery(`SELECT id FROM tickets WHERE hash = ${sqlString(ticketHash)} LIMIT 1`));
  const ticketId = Number(ticket.id);

  dbQuery(`
    INSERT INTO comments (tenant_id, ticket_id, user_id, content, is_internal, time_spent, created_at)
    VALUES (${tenantId}, ${ticketId}, ${adminId}, '<p>E2E tracked work</p>', 0, 35, NOW())
  `);
  const comment = rowObject(dbQuery(`SELECT id FROM comments WHERE ticket_id = ${ticketId} ORDER BY id DESC LIMIT 1`));
  dbQuery(`
    INSERT INTO ticket_time_entries (
      tenant_id, ticket_id, user_id, comment_id, started_at, ended_at,
      duration_minutes, is_billable, is_manual, summary, created_at
    ) VALUES (
      ${tenantId}, ${ticketId}, ${adminId}, ${Number(comment.id)},
      DATE_SUB(NOW(), INTERVAL 35 MINUTE), NOW(), 35, 1, 1, 'E2E tracked work', NOW()
    )
  `);
  dbQuery(`
    INSERT INTO attachments (
      tenant_id, ticket_id, comment_id, filename, original_name, mime_type,
      file_size, storage_driver, uploaded_by, created_at
    ) VALUES (
      ${tenantId}, ${ticketId}, ${Number(comment.id)},
      ${sqlString(`e2e-delete-${suffix}.txt`)}, 'evidence.txt', 'text/plain',
      12, 'local', ${adminId}, NOW()
    )
  `);
  dbQuery(`
    INSERT INTO notifications (tenant_id, user_id, ticket_id, type, actor_id, data, created_at)
    VALUES (${tenantId}, ${adminId}, ${ticketId}, 'info', ${adminId}, '{}', NOW())
  `);
  dbQuery(`
    INSERT INTO activity_log (tenant_id, ticket_id, user_id, action, details, created_at)
    VALUES (${tenantId}, ${ticketId}, ${adminId}, 'commented', '{}', NOW())
  `);
  dbQuery(`
    INSERT INTO api_tokens (
      tenant_id, user_id, name, token_hash, token_prefix, scopes_json,
      is_active, created_at
    ) VALUES (
      ${tenantId}, ${adminId}, 'E2E permanent deletion', '${tokenHash}', 'e2edel',
      '["tickets:read","delete:write"]', 1, NOW()
    )
  `);

  return { ticketId, title, token, tokenHash };
}

function seedPermanentDeleteDeniedToken() {
  seedPermissionFixture();
  const user = rowObject(dbQuery("SELECT id, tenant_id FROM users WHERE email = 'agent.scope@example.test' LIMIT 1"));
  const token = `e2e-permanent-delete-denied-${crypto.randomUUID()}`;
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  dbQuery(`
    INSERT INTO api_tokens (
      tenant_id, user_id, name, token_hash, token_prefix, scopes_json,
      is_active, created_at
    ) VALUES (
      ${Number(user.tenant_id)}, ${Number(user.id)}, 'E2E denied permanent deletion',
      '${tokenHash}', 'e2eddeny', '["tickets:read","delete:write"]', 1, NOW()
    )
  `);
  return { token, tokenHash };
}

function cleanupPausedWorkspaceFixture(fixture) {
  dbQuery(`DELETE FROM users WHERE email = ${sqlString(fixture.email)}`);
  dbQuery(`DELETE FROM organizations WHERE name = ${sqlString(fixture.organizationName)}`);
  dbQuery(`DELETE FROM tenants WHERE slug = ${sqlString(fixture.tenantSlug)}`);
}

test('native iOS login rejects customer accounts', async () => {
  const fixture = seedPermissionFixture();
  const api = await request.newContext({ baseURL });

  try {
    const response = await api.post('/index.php?page=api&action=mobile-login', {
      data: {
        email: 'beta.client@example.test',
        password: fixture.password,
        device_id: 'e2e-customer-device',
        device_name: 'E2E iPhone',
        app_version: 'e2e'
      }
    });

    expect(response.status()).toBe(403);
    const payload = await response.json();
    expect(JSON.stringify(payload)).toContain('workspace agents and admins');
  } finally {
    await api.dispose();
  }
});

test('paused workspace keeps native account state available but blocks ticket data', async () => {
  const fixture = seedPausedWorkspaceFixture();
  const api = await request.newContext({ baseURL });

  try {
    const loginResponse = await api.post('/index.php?page=api&action=mobile-login', {
      data: {
        email: fixture.email,
        password: fixture.password,
        device_id: `e2e-paused-${crypto.randomUUID()}`,
        device_name: 'Paused E2E iPhone',
        app_version: '0.1.0-e2e'
      }
    });
    expect(loginResponse.status()).toBe(200);
    const login = await loginResponse.json();
    const accessToken = login?.session?.access_token;
    expect(typeof accessToken).toBe('string');

    const authenticated = await request.newContext({
      baseURL,
      extraHTTPHeaders: { Authorization: `Bearer ${accessToken}` }
    });
    try {
      const stateResponse = await authenticated.get('/index.php?page=api&action=app-tenant-state');
      expect(stateResponse.status()).toBe(200);
      const state = await stateResponse.json();
      expect(state?.data?.access?.allowed).toBe(false);
      expect(state?.data?.access?.state).toBe('blocked');

      const ticketsResponse = await authenticated.get('/index.php?page=api&action=app-ticket-list&view=all');
      expect(ticketsResponse.status()).toBe(402);
      expect(ticketsResponse.headers()['x-foxdesk-workspace-access']).toBe('paused');
      expect(await ticketsResponse.text()).toContain('Workspace access is paused');
    } finally {
      await authenticated.dispose();
    }
  } finally {
    await api.dispose();
    cleanupPausedWorkspaceFixture(fixture);
  }
});

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

test('permanent ticket deletion removes related data and replays idempotently', async () => {
  const fixture = seedPermanentDeleteFixture();
  const deniedToken = seedPermanentDeleteDeniedToken();
  const api = await request.newContext({
    baseURL,
    extraHTTPHeaders: {
      Authorization: `Bearer ${fixture.token}`,
      'Content-Type': 'application/json'
    }
  });
  const deniedApi = await request.newContext({
    baseURL,
    extraHTTPHeaders: {
      Authorization: `Bearer ${deniedToken.token}`,
      'Content-Type': 'application/json'
    }
  });

  try {
    const denied = await deniedApi.get(`/index.php?page=api&action=agent-delete-ticket-preflight&ticket_id=${fixture.ticketId}`);
    expect(denied.status()).toBe(403);

    const preflight = await api.get(`/index.php?page=api&action=agent-delete-ticket-preflight&ticket_id=${fixture.ticketId}`);
    expect(preflight.status()).toBe(200);
    const preflightBody = await preflight.json();
    expect(preflightBody).toMatchObject({
      success: true,
      preflight: {
        ticket_id: fixture.ticketId,
        title: fixture.title,
        comment_count: 1,
        time_entry_count: 1,
        time_minutes: 35,
        attachment_count: 1
      }
    });
    const ticketCode = preflightBody.preflight.ticket_code;

    const invalid = await api.post('/index.php?page=api&action=agent-delete-ticket-permanently', {
      headers: { 'Idempotency-Key': `invalid-${crypto.randomUUID()}` },
      data: { ticket_id: fixture.ticketId, confirmation: 'WRONG-CODE' }
    });
    expect(invalid.status()).toBe(422);
    expect(Number(rowObject(dbQuery(`SELECT COUNT(*) AS cnt FROM tickets WHERE id = ${fixture.ticketId}`)).cnt)).toBe(1);

    const idempotencyKey = `delete-${crypto.randomUUID()}`;
    const payload = {
      ticket_id: fixture.ticketId,
      confirmation: ticketCode,
      delete_comments: true,
      delete_time_entries: true,
      delete_attachments: true
    };
    const removed = await api.post('/index.php?page=api&action=agent-delete-ticket-permanently', {
      headers: { 'Idempotency-Key': idempotencyKey },
      data: payload
    });
    expect(removed.status()).toBe(200);
    const removedBody = await removed.json();
    expect(removedBody).toMatchObject({ success: true, deleted: true, already_deleted: false });

    const replay = await api.post('/index.php?page=api&action=agent-delete-ticket-permanently', {
      headers: { 'Idempotency-Key': idempotencyKey },
      data: payload
    });
    expect(replay.status()).toBe(200);
    expect(await replay.json()).toEqual(removedBody);

    const counts = rowObject(dbQuery(`
      SELECT
        (SELECT COUNT(*) FROM tickets WHERE id = ${fixture.ticketId}) AS tickets,
        (SELECT COUNT(*) FROM comments WHERE ticket_id = ${fixture.ticketId}) AS comments,
        (SELECT COUNT(*) FROM ticket_time_entries WHERE ticket_id = ${fixture.ticketId}) AS time_entries,
        (SELECT COUNT(*) FROM attachments WHERE ticket_id = ${fixture.ticketId}) AS attachments,
        (SELECT COUNT(*) FROM notifications WHERE ticket_id = ${fixture.ticketId}) AS notifications,
        (SELECT COUNT(*) FROM activity_log WHERE ticket_id = ${fixture.ticketId}) AS activity
    `));
    expect(counts).toMatchObject({
      tickets: '0',
      comments: '0',
      time_entries: '0',
      attachments: '0',
      notifications: '0',
      activity: '0'
    });
    const receipt = rowObject(dbQuery(`
      SELECT COUNT(*) AS cnt
      FROM ticket_deletion_receipts
      WHERE ticket_id = ${fixture.ticketId}
    `));
    expect(Number(receipt.cnt)).toBe(1);
  } finally {
    dbQuery(`DELETE FROM api_tokens WHERE token_hash = '${fixture.tokenHash}'`);
    dbQuery(`DELETE FROM api_tokens WHERE token_hash = '${deniedToken.tokenHash}'`);
    await deniedApi.dispose();
    await api.dispose();
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

test('native mobile CRUD keeps ticket, comment, time, attachment, and archive state consistent', async () => {
  const api = await request.newContext({ baseURL });
  let authenticated;
  let ticketId = 0;

  try {
    const loginResponse = await api.post('/index.php?page=api&action=mobile-login', {
      data: {
        email: admin.email,
        password: admin.password,
        device_id: `e2e-capability-${crypto.randomUUID()}`,
        device_name: 'Capability E2E iPhone',
        app_version: '0.1.0-e2e'
      }
    });
    expect(loginResponse.status()).toBe(200);
    const loginPayload = await loginResponse.json();
    const accessToken = loginPayload?.session?.access_token;
    expect(typeof accessToken).toBe('string');

    authenticated = await request.newContext({
      baseURL,
      extraHTTPHeaders: { Authorization: `Bearer ${accessToken}` }
    });

    const title = `Native capability ${Date.now()}`;
    const createResponse = await authenticated.post('/index.php?page=api&action=app-create-ticket', {
      headers: { 'Idempotency-Key': `create-${crypto.randomUUID()}` },
      data: {
        title,
        description: '<p>Created by the native capability E2E.</p>',
        skip_notification: true
      }
    });
    expect(createResponse.status()).toBe(200);
    const created = await createResponse.json();
    ticketId = Number(created.ticket_id);
    expect(ticketId).toBeGreaterThan(0);

    const commentResponse = await authenticated.post('/index.php?page=api&action=app-add-comment-with-time', {
      headers: { 'Idempotency-Key': `comment-${crypto.randomUUID()}` },
      data: {
        ticket_id: ticketId,
        content: '<p>Initial mobile work record.</p>',
        duration_minutes: 18,
        is_billable: true,
        skip_notification: true
      }
    });
    expect(commentResponse.status()).toBe(200);
    const comment = await commentResponse.json();
    const commentId = Number(comment.comment_id);
    const timeEntryId = Number(comment.time_entry_id);
    expect(commentId).toBeGreaterThan(0);
    expect(timeEntryId).toBeGreaterThan(0);

    const updateCommentResponse = await authenticated.post('/index.php?page=api&action=app-update-comment', {
      headers: { 'Idempotency-Key': `update-comment-${crypto.randomUUID()}` },
      data: { comment_id: commentId, content: '<p>Edited mobile work record.</p>' }
    });
    expect(updateCommentResponse.status()).toBe(200);

    const updateTimeResponse = await authenticated.post('/index.php?page=api&action=app-update-time-entry', {
      headers: { 'Idempotency-Key': `update-time-${crypto.randomUUID()}` },
      data: { time_entry_id: timeEntryId, duration_minutes: 27, summary: 'Edited from native E2E', is_billable: false }
    });
    expect(updateTimeResponse.status()).toBe(200);

    const uploadResponse = await authenticated.post('/index.php?page=api&action=upload', {
      headers: { 'Idempotency-Key': `upload-${crypto.randomUUID()}` },
      multipart: {
        ticket_id: String(ticketId),
        file: {
          name: 'native-capability.txt',
          mimeType: 'text/plain',
          buffer: Buffer.from('native capability attachment', 'utf8')
        }
      }
    });
    expect(uploadResponse.status()).toBe(200);
    const attachmentRow = rowObject(dbQuery(`
      SELECT id FROM attachments
      WHERE ticket_id = ${ticketId} AND original_name = 'native-capability.txt'
      ORDER BY id DESC LIMIT 1
    `));
    const attachmentId = Number(attachmentRow.id);
    expect(attachmentId).toBeGreaterThan(0);

    const deleteAttachmentResponse = await authenticated.post('/index.php?page=api&action=app-delete-attachment', {
      headers: { 'Idempotency-Key': `delete-attachment-${crypto.randomUUID()}` },
      data: { attachment_id: attachmentId }
    });
    expect(deleteAttachmentResponse.status()).toBe(200);
    const deletedAttachment = await deleteAttachmentResponse.json();
    const attachmentUndoToken = deletedAttachment.undo_token ?? deletedAttachment.data?.undo_token;
    expect(typeof attachmentUndoToken).toBe('string');

    const restoreAttachmentResponse = await authenticated.post('/index.php?page=api&action=app-restore-attachment', {
      headers: { 'Idempotency-Key': `restore-attachment-${crypto.randomUUID()}` },
      data: { undo_token: attachmentUndoToken }
    });
    expect(restoreAttachmentResponse.status()).toBe(200);

    const archiveResponse = await authenticated.post('/index.php?page=api&action=app-update-ticket', {
      headers: { 'Idempotency-Key': `archive-${crypto.randomUUID()}` },
      data: { ticket_id: ticketId, is_archived: true }
    });
    expect(archiveResponse.status()).toBe(200);

    const restoreResponse = await authenticated.post('/index.php?page=api&action=app-update-ticket', {
      headers: { 'Idempotency-Key': `restore-ticket-${crypto.randomUUID()}` },
      data: { ticket_id: ticketId, is_archived: false }
    });
    expect(restoreResponse.status()).toBe(200);

    const persisted = rowObject(dbQuery(`
      SELECT
        t.is_archived,
        c.content,
        te.duration_minutes,
        te.is_billable,
        te.comment_id,
        (SELECT COUNT(*) FROM attachments a WHERE a.id = ${attachmentId} AND a.ticket_id = t.id) AS attachment_count
      FROM tickets t
      JOIN comments c ON c.id = ${commentId}
      JOIN ticket_time_entries te ON te.id = ${timeEntryId}
      WHERE t.id = ${ticketId}
      LIMIT 1
    `));
    expect(Number(persisted.is_archived)).toBe(0);
    expect(persisted.content).toContain('Edited mobile work record');
    expect(Number(persisted.duration_minutes)).toBe(27);
    expect(Number(persisted.is_billable)).toBe(0);
    expect(Number(persisted.comment_id)).toBe(commentId);
    expect(Number(persisted.attachment_count)).toBe(1);
  } finally {
    if (authenticated) {
      await authenticated.dispose();
    }
    await api.dispose();
    if (ticketId > 0) {
      dbQuery(`DELETE FROM tickets WHERE id = ${ticketId}`);
    }
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
