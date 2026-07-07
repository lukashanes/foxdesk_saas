#!/usr/bin/env node
/* eslint-disable no-console */

const fs = require('node:fs');
const path = require('node:path');

const DEFAULT_BASE_URL = 'https://app.foxdesk.net/api/mobile/v1';
const ROOT_DIR = path.resolve(__dirname, '..');
const EVIDENCE_DIR = path.join(ROOT_DIR, 'tmp', 'ios-demo-account-check');

const args = new Set(process.argv.slice(2));
const jsonMode = args.has('--json');
const requireCredentials = args.has('--require-credentials');

function normalizeBaseURL(value) {
  const raw = (value || DEFAULT_BASE_URL).trim().replace(/\/+$/, '');
  if (!raw) return DEFAULT_BASE_URL;

  if (raw.endsWith('/api/mobile/v1')) {
    return raw;
  }
  if (raw.endsWith('/index.php')) {
    return raw.slice(0, -'/index.php'.length).replace(/\/+$/, '') + '/api/mobile/v1';
  }
  return raw + '/api/mobile/v1';
}

const config = {
  baseURL: normalizeBaseURL(
    process.env.FOXDESK_IOS_DEMO_BASE_URL
    || process.env.FOXDESK_IOS_SMOKE_BASE_URL
    || process.env.FOXDESK_IOS_BASE_URL
  ),
  email: (process.env.FOXDESK_IOS_DEMO_EMAIL || process.env.FOXDESK_IOS_SMOKE_EMAIL || '').trim(),
  password: process.env.FOXDESK_IOS_DEMO_PASSWORD || process.env.FOXDESK_IOS_SMOKE_PASSWORD || '',
  twoFactorCode: (process.env.FOXDESK_IOS_DEMO_2FA_CODE || process.env.FOXDESK_IOS_SMOKE_2FA_CODE || '').trim(),
  writeEnabled: process.env.FOXDESK_IOS_DEMO_WRITE === '1',
};

const result = {
  ok: false,
  mode: config.email && config.password ? 'live-demo-account' : 'preflight',
  generated_at: null,
  base_url: config.baseURL,
  steps: [],
  missing: [],
};

function record(name, ok, details = {}) {
  result.steps.push({ name, ok, ...details });
}

function outputAndExit(code) {
  result.ok = result.steps.every((step) => step.ok);
  writeEvidence();
  if (jsonMode) {
    console.log(JSON.stringify(result, null, 2));
  } else {
    console.log(`[ios:demo:check] ${result.ok ? 'OK' : 'Not complete'} (${result.mode})`);
    console.log(`[ios:demo:check] Base URL: ${result.base_url}`);
    for (const step of result.steps) {
      const status = step.ok ? 'OK' : 'FAIL';
      const suffix = step.message ? ` - ${step.message}` : '';
      console.log(`[ios:demo:check] ${status} ${step.name}${suffix}`);
    }
    if (result.missing.length > 0) {
      console.log(`[ios:demo:check] Missing env: ${result.missing.join(', ')}`);
      console.log('[ios:demo:check] Set FOXDESK_IOS_DEMO_EMAIL and FOXDESK_IOS_DEMO_PASSWORD to verify the App Review account.');
      console.log('[ios:demo:check] If the account uses 2FA, also set FOXDESK_IOS_DEMO_2FA_CODE.');
    }
  }
  process.exit(code);
}

function writeEvidence() {
  result.generated_at = new Date().toISOString();
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
  const json = JSON.stringify(result, null, 2) + '\n';
  fs.writeFileSync(path.join(EVIDENCE_DIR, 'latest.json'), json);
  fs.writeFileSync(path.join(EVIDENCE_DIR, `latest-${result.mode}.json`), json);
}

function dataOf(payload) {
  return payload?.data && typeof payload.data === 'object' ? payload.data : payload;
}

function asPositiveInt(value) {
  const number = Number.parseInt(`${value}`, 10);
  return Number.isFinite(number) && number > 0 ? number : null;
}

function firstId(rows) {
  if (!Array.isArray(rows)) return null;
  for (const row of rows) {
    const id = asPositiveInt(row?.id || row?.organization_id || row?.user_id || row?.value);
    if (id) return id;
  }
  return null;
}

function compactObject(value) {
  return Object.fromEntries(
    Object.entries(value).filter(([, entry]) => entry !== undefined && entry !== null && entry !== '')
  );
}

async function request(path, { method = 'GET', token = '', body = undefined, query = undefined } = {}) {
  const url = new URL(`${config.baseURL}/${path.replace(/^\/+/, '')}`);
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== null && `${value}` !== '') {
        url.searchParams.set(key, String(value));
      }
    }
  }

  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  const response = await fetch(url, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await response.text();
  let payload = null;
  try {
    payload = text ? JSON.parse(text) : null;
  } catch {
    payload = { raw: text.slice(0, 500) };
  }

  if (!response.ok || payload?.success === false) {
    const message = payload?.error || payload?.message || `HTTP ${response.status}`;
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

function requireEnv() {
  if (!config.email) result.missing.push('FOXDESK_IOS_DEMO_EMAIL');
  if (!config.password) result.missing.push('FOXDESK_IOS_DEMO_PASSWORD');

  if (result.missing.length > 0) {
    record('credentials', !requireCredentials, {
      message: requireCredentials ? 'Demo reviewer credentials are required.' : 'Skipped live demo account verification until credentials are provided.',
    });
    outputAndExit(requireCredentials ? 2 : 0);
  }
}

async function login() {
  const device = {
    device_id: 'codex-ios-demo-account-check',
    device_name: 'Codex iOS Demo Account Check',
    app_version: '0.1.0-demo-check',
  };

  const loginPayload = await request('login', {
    method: 'POST',
    body: {
      email: config.email,
      password: config.password,
      ...device,
    },
  });
  const loginData = dataOf(loginPayload);
  record('login', true, { requires_2fa: !!loginData?.requires_2fa });

  if (!loginData?.requires_2fa) {
    return loginData;
  }

  if (!config.twoFactorCode) {
    result.missing.push('FOXDESK_IOS_DEMO_2FA_CODE');
    record('verify-2fa', false, { message: 'Demo account requires 2FA.' });
    outputAndExit(2);
  }

  const verifyPayload = await request('verify-2fa', {
    method: 'POST',
    body: {
      challenge_token: loginData.challenge_token,
      code: config.twoFactorCode,
      ...device,
    },
  });
  record('verify-2fa', true);
  return dataOf(verifyPayload);
}

async function listTickets(accessToken, view) {
  const payload = dataOf(await request('tickets', {
    token: accessToken,
    query: { view, limit: 10, offset: 0 },
  }));
  const tickets = Array.isArray(payload?.tickets) ? payload.tickets : [];
  record(`tickets:${view}`, true, { count: tickets.length });
  return tickets;
}

async function verifyDemoWrite(accessToken, createOptions) {
  if (!config.writeEnabled) {
    record('demo-write-create-ticket', true, {
      message: 'Skipped. Set FOXDESK_IOS_DEMO_WRITE=1 to prove the reviewer account can create a ticket.',
    });
    record('demo-write-comment-with-time', true, {
      message: 'Skipped. Set FOXDESK_IOS_DEMO_WRITE=1 to prove the reviewer account can add a linked internal timed comment.',
    });
    return;
  }

  const clients = Array.isArray(createOptions?.clients) ? createOptions.clients : [];
  const statuses = Array.isArray(createOptions?.statuses) ? createOptions.statuses : [];
  const priorities = Array.isArray(createOptions?.priorities) ? createOptions.priorities : [];
  const now = new Date();
  const createBody = compactObject({
    title: `[App Review write check] ${now.toISOString()}`,
    description: '<p>Internal App Review write check created by the FoxDesk iOS demo account verifier.</p>',
    organization_id: asPositiveInt(createOptions?.defaults?.organization_id) || firstId(clients),
    status_id: asPositiveInt(createOptions?.defaults?.status_id) || firstId(statuses),
    priority_id: asPositiveInt(createOptions?.defaults?.priority_id) || firstId(priorities),
    tags: 'ios-demo-review',
    skip_notification: true,
  });

  const created = dataOf(await request('tickets', {
    method: 'POST',
    token: accessToken,
    body: createBody,
  }));
  const ticketId = asPositiveInt(created?.ticket_id || created?.ticket?.id);
  record('demo-write-create-ticket', !!ticketId, {
    ticket_id: ticketId || null,
    ticket_code: created?.ticket_code || created?.ticket?.ticket_code || null,
  });
  if (!ticketId) {
    throw new Error('Demo reviewer write check did not create a ticket.');
  }

  const payload = dataOf(await request(`tickets/${ticketId}/comment-with-time`, {
    method: 'POST',
    token: accessToken,
    body: {
      ticket_id: ticketId,
      content: '<p><strong>App Review write check</strong></p><p>Internal timed comment created by the FoxDesk iOS demo account verifier.</p>',
      is_internal: true,
      skip_notification: true,
      duration_minutes: 3,
      is_billable: false,
      time_summary: 'App Review write check',
    },
  }));

  const commentId = asPositiveInt(payload?.comment_id);
  const timeEntryId = asPositiveInt(payload?.time_entry_id);
  record('demo-write-comment-with-time', !!commentId && !!timeEntryId, {
    ticket_id: ticketId,
    comment_id: commentId || null,
    time_entry_id: timeEntryId || null,
  });
  if (!commentId || !timeEntryId) {
    throw new Error('Demo reviewer write check did not return linked comment_id and time_entry_id.');
  }

  const detail = dataOf(await request(`tickets/${ticketId}`, { token: accessToken }));
  const comments = Array.isArray(detail?.comments) ? detail.comments : [];
  const timeEntries = Array.isArray(detail?.time_entries) ? detail.time_entries : [];
  const commentRow = comments.find((row) => asPositiveInt(row?.id || row?.comment_id) === commentId);
  const linkedTimeRow = timeEntries.find((row) => (
    asPositiveInt(row?.id || row?.time_entry_id) === timeEntryId
    && asPositiveInt(row?.comment_id) === commentId
  ));
  record('demo-write-detail-reload', !!detail?.ticket && !!commentRow && !!linkedTimeRow, {
    ticket_id: ticketId,
    comment_visible: !!commentRow,
    linked_time_visible: !!linkedTimeRow,
  });
  if (!detail?.ticket || !commentRow || !linkedTimeRow) {
    throw new Error('Demo reviewer write check did not reload the created ticket with its linked timed comment.');
  }
}

async function main() {
  requireEnv();

  const auth = await login();
  const accessToken = auth?.session?.access_token;
  const refreshToken = auth?.session?.refresh_token;
  if (!accessToken) {
    throw new Error('Login response did not include a mobile access token.');
  }

  try {
    const me = dataOf(await request('me', { token: accessToken }));
    record('me', !!me?.user?.email, { user: me?.user?.email ? 'present' : 'unknown' });

    const shell = dataOf(await request('shell', { token: accessToken }));
    record('shell', !!(shell?.app_shell || shell?.navigation || shell?.capabilities), {
      message: 'Workspace shell is readable.',
    });

    const work = dataOf(await request('work', { token: accessToken, query: { limit: 5 } }));
    record('work', !!(work?.home || work?.work || work?.time), {
      has_time: !!(work?.home?.time || work?.time),
    });

    const createOptions = dataOf(await request('tickets/create-options', { token: accessToken }));
    const clients = Array.isArray(createOptions?.clients) ? createOptions.clients : [];
    const statuses = Array.isArray(createOptions?.statuses) ? createOptions.statuses : [];
    const priorities = Array.isArray(createOptions?.priorities) ? createOptions.priorities : [];
    record('create-options', clients.length > 0 && statuses.length > 0 && priorities.length > 0, {
      clients: clients.length,
      statuses: statuses.length,
      priorities: priorities.length,
    });

    const openTickets = await listTickets(accessToken, 'open');
    const waitingTickets = await listTickets(accessToken, 'waiting');
    const doneTickets = await listTickets(accessToken, 'done');
    const allTickets = await listTickets(accessToken, 'all');

    record('demo-open-ticket', openTickets.length > 0, {
      message: openTickets.length > 0 ? 'At least one open ticket exists.' : 'Create at least one open ticket for App Review.',
    });
    record('demo-waiting-ticket', waitingTickets.length > 0, {
      message: waitingTickets.length > 0 ? 'At least one waiting ticket exists.' : 'Create at least one waiting ticket for App Review.',
    });
    record('demo-done-ticket', doneTickets.length > 0, {
      message: doneTickets.length > 0 ? 'At least one done ticket exists.' : 'Create at least one done ticket for App Review.',
    });

    const candidateMap = new Map();
    for (const row of [...openTickets, ...waitingTickets, ...doneTickets, ...allTickets]) {
      const id = asPositiveInt(row?.id || row?.ticket_id);
      if (id && !candidateMap.has(id)) candidateMap.set(id, row);
    }

    let richTicket = null;
    let clientContext = null;
    for (const id of candidateMap.keys()) {
      const detail = dataOf(await request(`tickets/${id}`, { token: accessToken }));
      const ticket = detail?.ticket || {};
      const comments = Array.isArray(detail?.comments) ? detail.comments : [];
      const attachments = Array.isArray(detail?.attachments) ? detail.attachments : [];
      if (comments.length > 0 && attachments.length > 0) {
        richTicket = { id, comments: comments.length, attachments: attachments.length };
      }

      if (!clientContext) {
        const clientId = asPositiveInt(ticket?.client?.id || ticket?.organization_id || ticket?.organizationId);
        if (clientId) {
          try {
            const overview = dataOf(await request(`clients/${clientId}`, {
              token: accessToken,
              query: { view: 'open' },
            }));
            const relatedTickets = Array.isArray(overview?.tickets) ? overview.tickets : [];
            const contacts = Array.isArray(overview?.contacts) ? overview.contacts : [];
            if (overview?.client?.id && (relatedTickets.length > 0 || contacts.length > 0)) {
              clientContext = {
                id: overview.client.id,
                name: overview.client.name || ticket?.client?.name || 'client',
                tickets: relatedTickets.length,
                contacts: contacts.length,
                source_ticket_id: id,
              };
            }
          } catch (error) {
            // Keep checking other tickets. A single stale organization id should not
            // hide a usable demo workspace elsewhere in the account.
          }
        }
      }

      if (richTicket && clientContext) {
        break;
      }
    }

    record('demo-rich-ticket', !!richTicket, {
      ticket_id: richTicket?.id || null,
      comments: richTicket?.comments || 0,
      attachments: richTicket?.attachments || 0,
      message: richTicket ? 'At least one ticket has comments and an attachment.' : 'Add one demo ticket with comments and an attachment.',
    });
    record('demo-client-context', !!clientContext, {
      client_id: clientContext?.id || null,
      client: clientContext?.name || null,
      related_tickets: clientContext?.tickets || 0,
      contacts: clientContext?.contacts || 0,
      source_ticket_id: clientContext?.source_ticket_id || null,
      message: clientContext ? 'At least one ticket opens a readable client context.' : 'Add one ticket linked to a client with related tickets or contacts visible.',
    });

    await verifyDemoWrite(accessToken, createOptions);
  } finally {
    if (refreshToken) {
      try {
        await request('logout', {
          method: 'POST',
          token: accessToken,
          body: { refresh_token: refreshToken },
        });
        record('logout', true);
      } catch (error) {
        record('logout', false, { message: error.message });
      }
    }
  }

  outputAndExit(result.steps.every((step) => step.ok) ? 0 : 1);
}

main().catch((error) => {
  record('unexpected-error', false, {
    message: error.message,
    status: error.status || null,
  });
  outputAndExit(1);
});
