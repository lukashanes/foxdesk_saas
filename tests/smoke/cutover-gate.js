const { chromium } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const mode = process.env.FOXDESK_CUTOVER_MODE || 'local';
const isLocalMode = mode === 'local';
const isProductionMode = mode === 'production';
const baseURL = process.env.FOXDESK_CUTOVER_BASE_URL ||
  process.env.FOXDESK_LOCAL_URL ||
  (isProductionMode ? 'https://app.foxdesk.net' : 'http://127.0.0.1:8090');
const email = process.env.FOXDESK_CUTOVER_ADMIN_EMAIL ||
  process.env.FOXDESK_LOCAL_ADMIN_EMAIL ||
  (isLocalMode ? 'admin@example.test' : '');
const password = process.env.FOXDESK_CUTOVER_ADMIN_PASSWORD ||
  process.env.FOXDESK_LOCAL_ADMIN_PASSWORD ||
  (isLocalMode ? 'AdminPass123!' : '');
const allowMutation = isLocalMode || process.env.FOXDESK_CUTOVER_ALLOW_MUTATION === '1';
const prepareFixtures = isLocalMode && process.env.FOXDESK_CUTOVER_PREPARE_FIXTURES !== '0';
const requireSearchCounts = prepareFixtures || process.env.FOXDESK_CUTOVER_REQUIRE_SEARCH_COUNTS === '1';
const searchQuery = process.env.FOXDESK_CUTOVER_SEARCH_QUERY ||
  (prepareFixtures ? 'Cutover searchable' : '');
const reportTimeRange = process.env.FOXDESK_CUTOVER_REPORT_TIME_RANGE || 'last_month';
const screenshotRoot = process.env.FOXDESK_CUTOVER_SCREENSHOT_DIR ||
  path.join(os.tmpdir(), `foxdesk-cutover-gate-${new Date().toISOString().replace(/[:.]/g, '-')}`);
const startedAt = new Date();

const viewports = [
  { name: 'desktop', width: 1440, height: 900, isMobile: false },
  { name: 'mobile', width: 390, height: 844, isMobile: true },
];

const screenshots = [];
const checks = [];
const gateChecklist = [
  ['Work queue desktop/mobile', ['desktop work', 'mobile work']],
  ['Inbox triage desktop/mobile', ['desktop inbox', 'mobile inbox']],
  ['Ticket list desktop/mobile', ['desktop tickets', 'mobile tickets']],
  ['Ticket detail desktop/mobile', ['desktop ticket detail', 'mobile ticket detail']],
  ['New ticket and attachment flow', ['new ticket create/upload/download']],
  ['Global search API', ['global search']],
  ['Reports detail rows and totals', ['reports detail rows and totals']],
  ['Admin/settings page desktop/mobile', ['desktop settings', 'mobile settings']],
];

function record(name) {
  checks.push(name);
}

function hasCheck(needle) {
  return checks.some(check => {
    if (/\bskipped\b/i.test(check)) {
      return false;
    }
    return check === needle || check.startsWith(`${needle} `);
  });
}

function checklistEvidence() {
  return gateChecklist.map(([label, required]) => ({
    label,
    passed: required.every(hasCheck),
    required,
  }));
}

function buildResult(status, error = null) {
  const endedAt = new Date();
  const canLiftHold = status === 'passed' && isProductionMode && allowMutation;
  return {
    status,
    mode,
    baseURL,
    adminEmail: email,
    allowMutation,
    prepareFixtures,
    requireSearchCounts,
    searchQuery,
    reportTimeRange,
    startedAt: startedAt.toISOString(),
    endedAt: endedAt.toISOString(),
    durationMs: endedAt.getTime() - startedAt.getTime(),
    canLiftHold,
    holdDecision: canLiftHold
      ? 'eligible_for_manual_cutover_review'
      : 'hold_remains_active',
    checks,
    checklist: checklistEvidence(),
    screenshots,
    evidenceDir: screenshotRoot,
    error: error ? {
      message: error.message,
      stack: error.stack,
    } : null,
  };
}

function renderMarkdown(result) {
  const lines = [
    '# FoxDesk Cutover Gate Report',
    '',
    `Status: ${result.status}`,
    `Mode: ${result.mode}`,
    `Target: ${result.baseURL}`,
    `Started: ${result.startedAt}`,
    `Ended: ${result.endedAt}`,
    `Duration: ${Math.round(result.durationMs / 1000)}s`,
    `Mutation enabled: ${result.allowMutation ? 'yes' : 'no'}`,
    `Fixture preparation: ${result.prepareFixtures ? 'yes' : 'no'}`,
    `Search query: ${result.searchQuery}`,
    `Hold decision: ${result.holdDecision}`,
    '',
    '## Verdict',
    '',
  ];

  if (result.canLiftHold) {
    lines.push('This run passed in production mode with mutation enabled. The cutover hold is eligible for manual review after the screenshots and imported workspace data are checked.');
  } else if (result.status === 'passed') {
    lines.push('This run passed, but it does not lift the cutover hold. A hold-lifting run must pass in production mode with mutation enabled.');
  } else {
    lines.push('This run failed. Keep the cutover hold active and fix the reported issue before another cutover attempt.');
  }

  lines.push('', '## Checklist', '');
  for (const item of result.checklist) {
    lines.push(`- ${item.passed ? '[x]' : '[ ]'} ${item.label}`);
  }

  lines.push('', '## Checks', '');
  for (const check of result.checks) {
    lines.push(`- ${check}`);
  }

  if (result.screenshots.length > 0) {
    lines.push('', '## Screenshots', '');
    for (const file of result.screenshots) {
      const name = path.basename(file);
      lines.push(`![${name}](${name})`);
    }
  }

  if (result.error) {
    lines.push('', '## Error', '', '```text', result.error.stack || result.error.message, '```');
  }

  return `${lines.join('\n')}\n`;
}

function writeEvidence(result) {
  fs.mkdirSync(screenshotRoot, { recursive: true });
  const resultPath = path.join(screenshotRoot, 'result.json');
  const reportPath = path.join(screenshotRoot, 'report.md');
  fs.writeFileSync(resultPath, `${JSON.stringify(result, null, 2)}\n`);
  fs.writeFileSync(reportPath, renderMarkdown(result));
  return { resultPath, reportPath };
}

function validateConfig() {
  if (!['local', 'production'].includes(mode)) {
    throw new Error(`FOXDESK_CUTOVER_MODE must be "local" or "production"; got "${mode}"`);
  }
  if (!email || !password) {
    throw new Error('Set FOXDESK_CUTOVER_ADMIN_EMAIL and FOXDESK_CUTOVER_ADMIN_PASSWORD for this cutover gate run.');
  }
  if (!searchQuery) {
    throw new Error('Set FOXDESK_CUTOVER_SEARCH_QUERY to a term that should be searchable in the target workspace.');
  }
}

function runDockerPhp(code) {
  return execFileSync('docker', ['exec', 'foxdesk-saas-local-app', 'php', '-r', code], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

async function waitForHealth() {
  const started = Date.now();
  while (Date.now() - started < 60_000) {
    try {
      const response = await fetch(`${baseURL}/index.php?page=health`);
      if (response.ok) {
        const json = await response.json();
        if (json.status === 'ok' && json.db === true) {
          record('health');
          return;
        }
      }
    } catch (_) {}
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  throw new Error(`FoxDesk health check did not pass at ${baseURL}`);
}

function ensureSearchFixtures() {
  const code = `
define('BASE_PATH', '/var/www/html');
require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/tenant-functions.php';
ensure_tenant_baseline();

function fx_one($sql, $params = []) {
    $row = db_fetch_one($sql, $params);
    return $row ?: [];
}

function fx_status_id($slug, $closed) {
    $row = fx_one('SELECT id FROM statuses WHERE slug = ? LIMIT 1', [$slug]);
    if (!$row) {
        $row = fx_one('SELECT id FROM statuses WHERE is_closed = ? ORDER BY sort_order, id LIMIT 1', [$closed ? 1 : 0]);
    }
    if (!$row) {
        $row = fx_one('SELECT id FROM statuses ORDER BY sort_order, id LIMIT 1');
    }
    return (int) ($row['id'] ?? 1);
}

function fx_priority_id() {
    $row = fx_one('SELECT id FROM priorities WHERE is_default = 1 ORDER BY sort_order, id LIMIT 1');
    if (!$row) {
        $row = fx_one('SELECT id FROM priorities ORDER BY sort_order, id LIMIT 1');
    }
    return (int) ($row['id'] ?? 1);
}

function fx_upsert_ticket($tenant_id, $title, $status_id, $archived, $user_id, $org_id, $priority_id) {
    $existing = fx_one('SELECT id FROM tickets WHERE tenant_id = ? AND title = ? LIMIT 1', [$tenant_id, $title]);
    $payload = [
        'tenant_id' => $tenant_id,
        'hash' => substr(sha1($tenant_id . ':' . $title), 0, 16),
        'title' => $title,
        'description' => '<p>Cutover gate fixture for global search and cloud QA.</p>',
        'type' => 'general',
        'priority_id' => $priority_id,
        'user_id' => $user_id,
        'organization_id' => $org_id,
        'status_id' => $status_id,
        'source' => 'web',
        'is_archived' => $archived ? 1 : 0,
        'assignee_id' => $user_id,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($existing) {
        db_update('tickets', $payload, 'id = ?', [(int) $existing['id']]);
        return (int) $existing['id'];
    }
    $payload['created_at'] = date('Y-m-d H:i:s');
    return (int) db_insert('tickets', $payload);
}

$tenant = fx_one("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1");
if (!$tenant) {
    $tenant = fx_one('SELECT id FROM tenants ORDER BY id LIMIT 1');
}
$tenant_id = (int) ($tenant['id'] ?? 1);
$user = fx_one("SELECT id FROM users WHERE tenant_id = ? AND role IN ('admin', 'agent') ORDER BY role = 'admin' DESC, id LIMIT 1", [$tenant_id]);
$org = fx_one('SELECT id FROM organizations WHERE tenant_id = ? ORDER BY id LIMIT 1', [$tenant_id]);
$user_id = (int) ($user['id'] ?? 1);
$org_id = (int) ($org['id'] ?? 0);
$priority_id = fx_priority_id();
$open_status = fx_status_id('open', false);
$closed_status = fx_status_id('closed', true);

fx_upsert_ticket($tenant_id, 'Cutover searchable open ticket', $open_status, false, $user_id, $org_id, $priority_id);
fx_upsert_ticket($tenant_id, 'Cutover searchable done ticket', $closed_status, false, $user_id, $org_id, $priority_id);
fx_upsert_ticket($tenant_id, 'Cutover searchable archived ticket', $open_status, true, $user_id, $org_id, $priority_id);
echo json_encode(['tenant_id' => $tenant_id, 'user_id' => $user_id, 'organization_id' => $org_id]);
`;

  const output = runDockerPhp(code);
  record(`fixtures ${output.trim()}`);
}

async function login(page) {
  await page.goto('/index.php?page=login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await Promise.all([
    page.waitForURL(/page=platform|page=work|page=dashboard|dashboard/),
    page.locator('button[type="submit"]').click(),
  ]);
  record('login');
}

async function screenshot(page, name) {
  fs.mkdirSync(screenshotRoot, { recursive: true });
  const file = path.join(screenshotRoot, `${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  screenshots.push(file);
}

async function expectNoAppConsoleErrors(errors, label) {
  const relevant = errors.filter(message => !/favicon|net::ERR_ABORTED/i.test(message));
  if (relevant.length > 0) {
    throw new Error(`${label} console errors: ${relevant.join('\n')}`);
  }
}

async function assertStyledShell(page, label, selectors, options = {}) {
  const result = await page.evaluate(({ selectors, allowStyleTags }) => {
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return {
        x: Math.round(bounds.x),
        y: Math.round(bounds.y),
        width: Math.round(bounds.width),
        height: Math.round(bounds.height),
      };
    };
    const matches = {};
    for (const [key, selector] of Object.entries(selectors)) {
      const element = document.querySelector(selector);
      matches[key] = element ? {
        selector,
        rect: rect(element),
        display: getComputedStyle(element).display,
        borderTopStyle: getComputedStyle(element).borderTopStyle,
        borderTopWidth: getComputedStyle(element).borderTopWidth,
      } : null;
    }
    const main = document.querySelector('#main-content');
    const content = document.querySelector('.app-content');
    const contentStyle = content ? getComputedStyle(content) : null;
    return {
      url: window.location.href,
      title: document.title,
      bodyText: document.body.textContent.trim().slice(0, 240),
      bodyOpacity: getComputedStyle(document.body).opacity,
      main: main ? {
        rect: rect(main),
        display: getComputedStyle(main).display,
      } : null,
      content: content ? {
        rect: rect(content),
        paddingLeft: parseFloat(contentStyle.paddingLeft),
      } : null,
      styleTagCount: document.querySelectorAll('style').length,
      themeLinks: [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href')),
      matches,
      allowStyleTags,
    };
  }, { selectors, allowStyleTags: !!options.allowStyleTags });

  if (!result.bodyText || result.bodyText.length < 20) {
    throw new Error(`${label} rendered as a blank or nearly blank page: ${JSON.stringify(result)}`);
  }
  if (result.bodyOpacity !== '1') {
    throw new Error(`${label} body is not fully visible: ${JSON.stringify(result)}`);
  }
  if (!result.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`${label} does not use a versioned theme.css link: ${JSON.stringify(result)}`);
  }
  if (!result.main || result.main.display !== 'flex' || result.main.rect.width < 320) {
    throw new Error(`${label} app frame is missing or unstyled: ${JSON.stringify(result)}`);
  }
  if (!result.content || result.content.paddingLeft < 16 || result.content.rect.width < 300) {
    throw new Error(`${label} app content spacing is missing: ${JSON.stringify(result)}`);
  }
  if (!result.allowStyleTags && result.styleTagCount > 0) {
    throw new Error(`${label} still emits inline <style> tags: ${JSON.stringify(result)}`);
  }
  for (const [key, match] of Object.entries(result.matches)) {
    if (!match || match.rect.width < 20 || match.rect.height < 8 || match.display === 'none') {
      throw new Error(`${label} missing or collapsed selector "${key}": ${JSON.stringify(result)}`);
    }
  }
  record(label);
}

async function expectWork(page, viewportName) {
  await page.goto('/index.php?page=work');
  await assertStyledShell(page, `${viewportName} work`, {
    shell: '.workspace-queue-shell',
    panel: '.workspace-queue-panel',
    queueList: '.work-queue-list',
  });
  await screenshot(page, `${viewportName}-work`);
}

async function expectInbox(page, viewportName) {
  await page.goto('/index.php?page=inbox');
  await assertStyledShell(page, `${viewportName} inbox`, {
    shell: '.workspace-queue-shell',
    panel: '.workspace-queue-panel',
    list: '.inbox-list',
  });
  await screenshot(page, `${viewportName}-inbox`);
}

async function expectTickets(page, viewportName, isMobile) {
  await page.goto('/index.php?page=tickets&work_view=all');
  await assertStyledShell(page, `${viewportName} tickets`, {
    tabs: '.ticket-view-tabs',
    card: '.card',
    rows: isMobile ? '.ticket-list-item' : '.tickets-table tbody tr.ticket-row',
  }, { allowStyleTags: false });
  await screenshot(page, `${viewportName}-tickets`);
}

async function expectTicketDetail(page, viewportName) {
  await page.goto('/index.php?page=tickets&work_view=all');
  const href = await page.evaluate(() => {
    const links = [...document.querySelectorAll('a[href*="page=ticket&id="]')];
    const visible = links.find(link => {
      const rect = link.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    });
    if (visible || links[0]) {
      return (visible || links[0]).getAttribute('href') || '';
    }
    const rows = [...document.querySelectorAll('.ticket-row[data-href]')];
    const visibleRow = rows.find(row => {
      const rect = row.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    });
    return (visibleRow || rows[0])?.getAttribute('data-href') || '';
  });
  if (!href) {
    throw new Error('No ticket detail link found from tickets list.');
  }
  await page.goto(href.startsWith('http') || href.startsWith('/') ? href : `/${href}`);
  await assertStyledShell(page, `${viewportName} ticket detail`, {
    workPanel: '.ticket-work-panel',
    sidebar: '.ticket-sidebar, #ticket-side-panel',
    editor: '.editor-wrapper',
    commentForm: '#comment-form',
  });

  const timelineButton = page.locator('button[onclick^="openTicketTimeline"]');
  if (await timelineButton.count()) {
    await timelineButton.first().click();
    await page.waitForFunction(() => {
      const overlay = document.querySelector('#timeline-overlay');
      return overlay && overlay.classList.contains('is-open') && overlay.getAttribute('aria-hidden') === 'false';
    });
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => {
      const overlay = document.querySelector('#timeline-overlay');
      return overlay && !overlay.classList.contains('is-open') && overlay.getAttribute('aria-hidden') === 'true';
    });
    record(`${viewportName} ticket timeline interaction`);
  }
  await screenshot(page, `${viewportName}-ticket-detail`);
}

async function expectNewTicketFlow(page) {
  const attachmentPath = path.join(os.tmpdir(), 'foxdesk-cutover-gate-attachment.txt');
  fs.writeFileSync(attachmentPath, 'cutover gate attachment\n');

  await page.goto('/index.php?page=new-ticket');
  await assertStyledShell(page, 'new ticket', {
    form: '#new-ticket-form',
    editor: '.editor-wrapper',
    upload: '#upload-zone',
  });

  const freshDefaults = await page.evaluate(() => {
    const org = document.querySelector('select[name="organization_id"][data-reset-on-fresh-ticket="1"]');
    const assignee = document.querySelector('select[name="assignee_id"][data-reset-on-fresh-ticket="1"]');
    return {
      organizationValue: org ? org.value : null,
      assigneeValue: assignee ? assignee.value : null,
    };
  });
  if (freshDefaults.organizationValue !== '') {
    throw new Error(`Fresh new-ticket client select must start empty: ${JSON.stringify(freshDefaults)}`);
  }
  if (freshDefaults.assigneeValue !== null && freshDefaults.assigneeValue !== '') {
    throw new Error(`Fresh new-ticket assignee select must start empty: ${JSON.stringify(freshDefaults)}`);
  }
  record('new ticket fresh defaults');

  const orgOption = await page.locator('select[name="organization_id"] option:not([value=""])').first().getAttribute('value').catch(() => null);
  if (orgOption) {
    await page.locator('select[name="organization_id"]').selectOption(orgOption);
  }
  const assigneeOption = await page.locator('select[name="assignee_id"] option:not([value=""])').first().getAttribute('value').catch(() => null);
  if (assigneeOption) {
    await page.locator('select[name="assignee_id"]').evaluate((select, value) => {
      select.value = value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }, assigneeOption);
  }

  const title = `Cutover gate ticket ${Date.now()}`;
  await page.locator('input[name="title"]').fill(title);
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Created by the cutover gate to verify create, upload, detail, and download.</p>';
  });
  await page.locator('#file-input').setInputFiles(attachmentPath);
  await page.locator('#new-ticket-form button[type="submit"]').first().click();
  await page.waitForURL(/page=ticket&id=\d+/);
  await page.locator('body').waitFor({ state: 'visible' });
  const body = await page.locator('body').textContent();
  if (!body.includes(title) || !body.includes('foxdesk-cutover-gate-attachment.txt')) {
    throw new Error(`Created ticket did not show expected title/attachment. Body: ${body.slice(0, 500)}`);
  }
  await assertStyledShell(page, 'created ticket detail', {
    workPanel: '.ticket-work-panel',
    editor: '.editor-wrapper',
    commentForm: '#comment-form',
  });
  const attachmentHref = await page.locator('a[href*="attachment.php"]', { hasText: 'foxdesk-cutover-gate-attachment.txt' }).first().getAttribute('href');
  if (!attachmentHref) {
    throw new Error('Created ticket attachment link was not rendered.');
  }
  const response = await page.request.get(attachmentHref);
  if (!response.ok()) {
    throw new Error(`Created ticket attachment download failed: ${response.status()}`);
  }
  const attachmentText = await response.text();
  if (!attachmentText.includes('cutover gate attachment')) {
    throw new Error('Created ticket attachment content did not match.');
  }
  record('new ticket create/upload/download');
  await screenshot(page, 'desktop-new-ticket-created');
}

async function expectGlobalSearch(page) {
  const response = await page.request.get(`/index.php?page=api&action=global-search&q=${encodeURIComponent(searchQuery)}&limit=8`);
  if (!response.ok()) {
    throw new Error(`Global search API failed: HTTP ${response.status()}`);
  }
  const json = await response.json();
  const sections = json.sections || {};
  const expectedSections = ['open_tickets', 'done_tickets', 'archived_tickets', 'clients', 'contacts', 'reports'];
  const missingSections = expectedSections.filter(section => !Object.prototype.hasOwnProperty.call(sections, section));
  const counts = {
    open: sections.open_tickets?.items?.length || 0,
    done: sections.done_tickets?.items?.length || 0,
    archived: sections.archived_tickets?.items?.length || 0,
    clients: sections.clients?.items?.length || 0,
    contacts: sections.contacts?.items?.length || 0,
    reports: sections.reports?.items?.length || 0,
  };
  if (json.success !== true || missingSections.length > 0) {
    throw new Error(`Global search response is incomplete: ${JSON.stringify({ success: json.success, counts, missingSections, keys: Object.keys(sections) })}`);
  }
  if (requireSearchCounts && (counts.open < 1 || counts.done < 1 || counts.archived < 1)) {
    throw new Error(`Global search did not return open/done/archived matches for "${searchQuery}": ${JSON.stringify({ counts, keys: Object.keys(sections) })}`);
  }
  record(`global search "${searchQuery}" ${JSON.stringify(counts)}`);
}

async function expectReports(page) {
  await page.goto('/index.php?page=admin&section=reports');
  await assertStyledShell(page, 'reports entry', {
    shell: '.admin-legacy-page',
    flow: '.reporting-flow-card',
    filters: '#report-filters',
  }, { allowStyleTags: true });

  const orgOption = await page.locator('.reporting-flow-card select[name="organizations[]"] option:not([value=""])').first().getAttribute('value').catch(() => null);
  if (!orgOption) {
    throw new Error('Reports billing review has no client option.');
  }
  await page.locator('.reporting-flow-card select[name="organizations[]"]').selectOption(orgOption);
  const rangeSelect = page.locator('.reporting-flow-card select[name="time_range"]');
  const hasRequestedRange = await rangeSelect.locator(`option[value="${reportTimeRange}"]`).count();
  if (hasRequestedRange) {
    await rangeSelect.selectOption(reportTimeRange);
  }
  await Promise.all([
    page.waitForURL(/tab=detailed/),
    page.locator('.reporting-flow-card button[type="submit"]').click(),
  ]);
  await assertStyledShell(page, 'reports detailed', {
    shell: '.admin-legacy-page',
    row: '.report-detail-row',
    amount: '[data-entry-amount]',
    form: '.entry-billing-form',
  }, { allowStyleTags: true });
  const totals = await page.evaluate(() => ({
    rows: document.querySelectorAll('.report-detail-row').length,
    amountTexts: [...document.querySelectorAll('[data-entry-amount]')].slice(0, 3).map(node => node.textContent.trim()),
    detailTotal: document.querySelector('#detail-billable-amount')?.textContent.trim() || '',
  }));
  if (totals.rows < 1 || !totals.amountTexts.some(Boolean) || !totals.detailTotal) {
    throw new Error(`Reports detailed billing rows/totals are incomplete: ${JSON.stringify(totals)}`);
  }
  const beforeTotal = await page.locator('#detail-billable-amount').textContent();
  const firstEntryForm = page.locator('.entry-billing-form').first();
  await firstEntryForm.locator('select[name="entry_adjust_action"]').selectOption('discount_percent');
  await firstEntryForm.locator('input[name="entry_adjust_value"]').fill('10');
  await page.waitForFunction((before) => {
    const current = document.querySelector('#detail-billable-amount')?.textContent.trim() || '';
    return current !== String(before || '').trim();
  }, beforeTotal);
  record('reports detail rows and totals');
  await screenshot(page, 'desktop-reports-detailed');
}

async function expectSettings(page, viewportName) {
  await page.goto('/index.php?page=admin&section=settings&tab=system');
  await assertStyledShell(page, `${viewportName} settings`, {
    shell: '.admin-legacy-page, .admin-shell, .card',
    tabs: '.admin-tabs',
    activeTab: '.admin-tab.is-active',
  }, { allowStyleTags: true });
  await screenshot(page, `${viewportName}-settings`);
}

async function runViewport(browser, viewport) {
  const context = await browser.newContext({
    baseURL,
    acceptDownloads: true,
    viewport: { width: viewport.width, height: viewport.height },
    isMobile: viewport.isMobile,
  });
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('console', message => {
    if (message.type() === 'error') {
      consoleErrors.push(`[${page.url()}] ${message.text()}`);
    }
  });
  page.on('pageerror', error => {
    consoleErrors.push(`[${page.url()}] ${error.message}`);
  });

  await login(page);
  await expectWork(page, viewport.name);
  await expectInbox(page, viewport.name);
  await expectTickets(page, viewport.name, viewport.isMobile);
  await expectTicketDetail(page, viewport.name);
  await expectSettings(page, viewport.name);
  if (!viewport.isMobile) {
    if (allowMutation) {
      await expectNewTicketFlow(page);
    } else {
      record('new ticket create/upload/download skipped in read-only mode');
    }
    await expectGlobalSearch(page);
    await expectReports(page);
  }
  await expectNoAppConsoleErrors(consoleErrors, viewport.name);
  await context.close();
}

async function main() {
  validateConfig();
  await waitForHealth();
  if (prepareFixtures) {
    ensureSearchFixtures();
  } else {
    record('fixture preparation skipped');
  }

  const browser = await chromium.launch();
  try {
    for (const viewport of viewports) {
      await runViewport(browser, viewport);
    }
  } finally {
    await browser.close();
  }

  const result = buildResult('passed');
  const evidence = writeEvidence(result);
  console.log(JSON.stringify({ ...result, evidence }, null, 2));
}

main().catch(error => {
  const result = buildResult('failed', error);
  const evidence = writeEvidence(result);
  console.error(error);
  console.error(`Cutover evidence written to ${evidence.reportPath}`);
  process.exit(1);
});
