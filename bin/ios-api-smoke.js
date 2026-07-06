#!/usr/bin/env node
/* eslint-disable no-console */

const fs = require('node:fs');
const path = require('node:path');

const DEFAULT_BASE_URL = 'https://app.foxdesk.net/api/mobile/v1';
const ROOT_DIR = path.resolve(__dirname, '..');
const EVIDENCE_DIR = path.join(ROOT_DIR, 'tmp', 'ios-api-smoke');

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
  baseURL: normalizeBaseURL(process.env.FOXDESK_IOS_SMOKE_BASE_URL || process.env.FOXDESK_IOS_BASE_URL),
  email: (process.env.FOXDESK_IOS_SMOKE_EMAIL || '').trim(),
  password: process.env.FOXDESK_IOS_SMOKE_PASSWORD || '',
  twoFactorCode: (process.env.FOXDESK_IOS_SMOKE_2FA_CODE || '').trim(),
  query: (process.env.FOXDESK_IOS_SMOKE_SEARCH || 'test').trim(),
  writeEnabled: process.env.FOXDESK_IOS_SMOKE_WRITE === '1',
  clientId: process.env.FOXDESK_IOS_SMOKE_CLIENT_ID || '',
  assigneeId: process.env.FOXDESK_IOS_SMOKE_ASSIGNEE_ID || '',
  priorityId: process.env.FOXDESK_IOS_SMOKE_PRIORITY_ID || '',
  statusId: process.env.FOXDESK_IOS_SMOKE_STATUS_ID || '',
};

const result = {
  ok: false,
  mode: config.email && config.password ? (config.writeEnabled ? 'live-write' : 'live-read-only') : 'preflight',
  generated_at: null,
  base_url: config.baseURL,
  steps: [],
  missing: [],
};

function record(name, ok, details = {}) {
  result.steps.push({ name, ok, ...details });
}

function outputAndExit(code) {
  writeEvidence();
  if (jsonMode) {
    console.log(JSON.stringify(result, null, 2));
  } else {
    console.log(`[ios:api:smoke] ${result.ok ? 'OK' : 'Not complete'} (${result.mode})`);
    console.log(`[ios:api:smoke] Base URL: ${result.base_url}`);
    for (const step of result.steps) {
      const status = step.ok ? 'OK' : 'FAIL';
      const suffix = step.message ? ` - ${step.message}` : '';
      console.log(`[ios:api:smoke] ${status} ${step.name}${suffix}`);
    }
    if (result.missing.length > 0) {
      console.log(`[ios:api:smoke] Missing env: ${result.missing.join(', ')}`);
      console.log('[ios:api:smoke] Set FOXDESK_IOS_SMOKE_EMAIL and FOXDESK_IOS_SMOKE_PASSWORD to run the live read-only smoke.');
      console.log('[ios:api:smoke] If the account uses 2FA, also set FOXDESK_IOS_SMOKE_2FA_CODE.');
      console.log('[ios:api:smoke] Set FOXDESK_IOS_SMOKE_WRITE=1 to additionally create a smoke ticket and timed internal comment.');
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

async function request(path, { method = 'GET', token = '', body = undefined, query = undefined } = {}) {
  const url = new URL(`${config.baseURL}/${path.replace(/^\/+/, '')}`);
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== null && `${value}` !== '') {
        url.searchParams.set(key, String(value));
      }
    }
  }

  const headers = {
    Accept: 'application/json',
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

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

async function requestMultipart(path, { token = '', fields = {}, files = {} } = {}) {
  const url = new URL(`${config.baseURL}/${path.replace(/^\/+/, '')}`);
  const form = new FormData();
  for (const [key, value] of Object.entries(fields)) {
    if (value !== undefined && value !== null && `${value}` !== '') {
      form.append(key, String(value));
    }
  }
  for (const [key, file] of Object.entries(files)) {
    form.append(key, file.blob, file.filename);
  }

  const headers = {
    Accept: 'application/json',
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(url, {
    method: 'POST',
    headers,
    body: form,
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

function requireEnv() {
  if (!config.email) result.missing.push('FOXDESK_IOS_SMOKE_EMAIL');
  if (!config.password) result.missing.push('FOXDESK_IOS_SMOKE_PASSWORD');

  if (result.missing.length > 0) {
    record('credentials', !requireCredentials, {
      message: requireCredentials ? 'Live smoke credentials are required.' : 'Skipped live smoke until credentials are provided.',
    });
    result.ok = !requireCredentials;
    outputAndExit(requireCredentials ? 2 : 0);
  }
}

async function runWriteSmoke(accessToken) {
  const options = dataOf(await request('tickets/create-options', { token: accessToken }));
  const clients = Array.isArray(options?.clients) ? options.clients : [];
  const statuses = Array.isArray(options?.statuses) ? options.statuses : [];
  const priorities = Array.isArray(options?.priorities) ? options.priorities : [];
  const assignees = Array.isArray(options?.assignees) ? options.assignees : [];
  record('create-options', true, {
    clients: clients.length,
    statuses: statuses.length,
    priorities: priorities.length,
    assignees: assignees.length,
  });

  const now = new Date();
  const title = `[iOS smoke] ${now.toISOString()}`;
  const clientId = asPositiveInt(config.clientId) || asPositiveInt(options?.defaults?.organization_id) || firstId(clients);
  const statusId = asPositiveInt(config.statusId) || asPositiveInt(options?.defaults?.status_id) || firstId(statuses);
  const priorityId = asPositiveInt(config.priorityId) || asPositiveInt(options?.defaults?.priority_id) || firstId(priorities);
  const assigneeId = asPositiveInt(config.assigneeId) || asPositiveInt(options?.defaults?.assignee_id) || null;

  const createBody = compactObject({
    title,
    description: '<p>Created by the FoxDesk iOS mobile API smoke.</p>',
    organization_id: clientId,
    status_id: statusId,
    priority_id: priorityId,
    assignee_id: assigneeId,
    tags: 'ios-smoke',
    skip_notification: true,
  });

  const created = dataOf(await request('tickets', {
    method: 'POST',
    token: accessToken,
    body: createBody,
  }));
  const ticketId = asPositiveInt(created?.ticket_id || created?.ticket?.id);
  record('create-ticket', !!ticketId, {
    ticket_id: ticketId || null,
    ticket_code: created?.ticket_code || created?.ticket?.ticket_code || null,
  });
  if (!ticketId) {
    throw new Error('Create ticket response did not include ticket_id.');
  }

  const comment = dataOf(await request(`tickets/${ticketId}/comment-with-time`, {
    method: 'POST',
    token: accessToken,
    body: {
      ticket_id: ticketId,
      content: '<p><strong>iOS smoke timed reply</strong></p><p>Verified comment-with-time from the native mobile API.</p>',
      is_internal: true,
      skip_notification: true,
      duration_minutes: 5,
      is_billable: false,
      time_summary: 'iOS smoke timed reply',
    },
  }));
  const commentId = asPositiveInt(comment?.comment_id);
  const timeEntryId = asPositiveInt(comment?.time_entry_id);
  record('comment-with-time', !!commentId && !!timeEntryId, {
    ticket_id: ticketId,
    comment_id: commentId || null,
    time_entry_id: timeEntryId || null,
  });
  if (!commentId || !timeEntryId) {
    throw new Error('Comment-with-time response did not include linked comment_id and time_entry_id.');
  }

  const uploadPayload = dataOf(await requestMultipart('attachments', {
    token: accessToken,
    fields: { ticket_id: ticketId },
    files: {
      file: {
        filename: 'ios-smoke-attachment.txt',
        blob: new Blob([
          `FoxDesk iOS smoke attachment\nTicket: ${ticketId}\nCreated: ${now.toISOString()}\n`,
        ], { type: 'text/plain' }),
      },
    },
  }));
  const attachmentId = asPositiveInt(uploadPayload?.file?.attachment_id);
  record('attachment-upload', !!attachmentId, {
    ticket_id: ticketId,
    attachment_id: attachmentId || null,
  });
  if (!attachmentId) {
    throw new Error('Attachment upload response did not include file.attachment_id.');
  }

  const detail = dataOf(await request(`tickets/${ticketId}`, { token: accessToken }));
  const comments = Array.isArray(detail?.comments) ? detail.comments : [];
  const timeEntries = Array.isArray(detail?.time_entries) ? detail.time_entries : [];
  const attachments = Array.isArray(detail?.attachments) ? detail.attachments : [];
  const hasComment = comments.some((row) => asPositiveInt(row?.id || row?.comment_id) === commentId);
  const hasLinkedTime = timeEntries.some((row) => (
    asPositiveInt(row?.id || row?.time_entry_id) === timeEntryId
    && asPositiveInt(row?.comment_id) === commentId
  ));
  const hasAttachment = attachments.some((row) => asPositiveInt(row?.id || row?.attachment_id) === attachmentId);
  record('created-ticket-detail', !!detail?.ticket && hasComment && hasLinkedTime && hasAttachment, {
    ticket_id: ticketId,
    comment_visible: hasComment,
    linked_time_visible: hasLinkedTime,
    attachment_visible: hasAttachment,
  });
}

async function main() {
  requireEnv();

  const device = {
    device_id: 'codex-ios-api-smoke',
    device_name: 'Codex iOS API Smoke',
    app_version: '0.1.0-smoke',
  };

  const loginPayload = await request('login', {
    method: 'POST',
    body: {
      email: config.email,
      password: config.password,
      ...device,
    },
  });
  const login = dataOf(loginPayload);
  record('login', true, { requires_2fa: !!login?.requires_2fa });

  let auth = login;
  if (login?.requires_2fa) {
    if (!config.twoFactorCode) {
      result.missing.push('FOXDESK_IOS_SMOKE_2FA_CODE');
      record('verify-2fa', false, { message: 'Account requires 2FA.' });
      result.ok = false;
      outputAndExit(2);
    }
    const verifyPayload = await request('verify-2fa', {
      method: 'POST',
      body: {
        challenge_token: login.challenge_token,
        code: config.twoFactorCode,
        ...device,
      },
    });
    auth = dataOf(verifyPayload);
    record('verify-2fa', true);
  }

  const accessToken = auth?.session?.access_token;
  const refreshToken = auth?.session?.refresh_token;
  if (!accessToken) {
    throw new Error('Login response did not include a mobile access token.');
  }

  try {
    const me = dataOf(await request('me', { token: accessToken }));
    record('me', !!me?.user?.email, { user: me?.user?.email ? 'present' : 'unknown' });

    const work = dataOf(await request('work', { token: accessToken, query: { limit: 5 } }));
    record('work', !!work?.home || !!work?.work || !!work?.time, {
      has_time: !!(work?.home?.time || work?.time),
    });

    const tickets = dataOf(await request('tickets', {
      token: accessToken,
      query: { view: 'open', assigned_to: 'me', limit: 5, offset: 0 },
    }));
    const rows = Array.isArray(tickets?.tickets) ? tickets.tickets : [];
    record('tickets', true, { count: rows.length });

    if (rows.length > 0) {
      const id = rows[0]?.id || rows[0]?.ticket_id;
      if (id) {
        const detail = dataOf(await request(`tickets/${id}`, { token: accessToken }));
        record('ticket-detail', !!detail?.ticket, { ticket_id: id });
      } else {
        record('ticket-detail', false, { message: 'First ticket row has no id.' });
      }
    } else {
      record('ticket-detail', true, { message: 'Skipped because no assigned open tickets were returned.' });
    }

    const search = dataOf(await request('search', {
      token: accessToken,
      query: { q: config.query, limit: 5 },
    }));
    record('search', !!search, { query: config.query });

    if (config.writeEnabled) {
      await runWriteSmoke(accessToken);
    } else {
      record('write-smoke', true, {
        message: 'Skipped. Set FOXDESK_IOS_SMOKE_WRITE=1 to create a smoke ticket and timed internal comment.',
      });
    }
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

  result.ok = result.steps.every((step) => step.ok);
  outputAndExit(result.ok ? 0 : 1);
}

main().catch((error) => {
  record('unexpected-error', false, {
    message: error.message,
    status: error.status || null,
  });
  result.ok = false;
  outputAndExit(1);
});
