const { chromium } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const baseURL = process.env.FOXDESK_LOCAL_URL || 'http://127.0.0.1:8090';
const email = process.env.FOXDESK_LOCAL_ADMIN_EMAIL || 'admin@example.test';
const password = process.env.FOXDESK_LOCAL_ADMIN_PASSWORD || 'AdminPass123!';

async function expectText(page, text) {
  const body = await page.locator('body').textContent();
  if (!body.includes(text)) {
    throw new Error(`Expected page to contain "${text}" at ${page.url()}`);
  }
}

async function expectLoginLayout(page) {
  const layout = await page.evaluate(() => {
    const body = document.body;
    const left = document.querySelector('.split-left');
    const right = document.querySelector('.split-right');
    const form = document.querySelector('.login-form-wrap');
    const email = document.querySelector('input[name="email"]');
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      viewportWidth: window.innerWidth,
      bodyDisplay: getComputedStyle(body).display,
      leftDisplay: left ? getComputedStyle(left).display : null,
      rightDisplay: right ? getComputedStyle(right).display : null,
      formRect: form ? rect(form) : null,
      emailRect: email ? rect(email) : null
    };
  });

  if (layout.bodyDisplay !== 'flex') {
    throw new Error(`Login body layout is broken: expected flex, got ${layout.bodyDisplay}`);
  }
  if (layout.viewportWidth >= 1024 && layout.leftDisplay !== 'flex') {
    throw new Error(`Desktop login brand panel is hidden or unstyled: ${layout.leftDisplay}`);
  }
  if (layout.rightDisplay !== 'flex') {
    throw new Error(`Login form panel is not centered with flex: ${layout.rightDisplay}`);
  }
  if (!layout.formRect || layout.formRect.x < layout.viewportWidth * 0.55) {
    throw new Error(`Login form is not in the right panel: ${JSON.stringify(layout.formRect)}`);
  }
  if (!layout.emailRect || layout.emailRect.width < 300 || layout.emailRect.height < 40) {
    throw new Error(`Login email field is not usable: ${JSON.stringify(layout.emailRect)}`);
  }
}

async function expectQueueSurface(page, pagePath, shellSelector, panelSelector) {
  await page.goto(pagePath);
  const layout = await page.evaluate(({ shellSelector, panelSelector }) => {
    const shell = document.querySelector(shellSelector);
    const panel = document.querySelector(panelSelector);
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };
    const styleTagCount = document.querySelectorAll('style').length;
    return {
      url: window.location.href,
      viewportWidth: window.innerWidth,
      shellRect: shell ? rect(shell) : null,
      panelRect: panel ? rect(panel) : null,
      shellDisplay: shell ? getComputedStyle(shell).display : null,
      shellGridTemplateColumns: shell ? getComputedStyle(shell).gridTemplateColumns : null,
      panelBorderStyle: panel ? getComputedStyle(panel).borderTopStyle : null,
      panelBorderWidth: panel ? getComputedStyle(panel).borderTopWidth : null,
      styleTagCount,
      inlineQueueStyles: [...document.querySelectorAll('style')]
        .some(style => style.textContent.includes(shellSelector.replace('.', ''))),
      bodyText: document.body.textContent.slice(0, 300)
    };
  }, { shellSelector, panelSelector });

  if (!layout.shellRect || !layout.panelRect) {
    throw new Error(`Missing queue surface on ${pagePath}: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineQueueStyles) {
    throw new Error(`Queue page still contains inline queue layout styles on ${pagePath}`);
  }
  if (layout.shellDisplay !== 'grid') {
    throw new Error(`Queue shell is not a grid on ${pagePath}: ${JSON.stringify(layout)}`);
  }
  if (layout.viewportWidth >= 1024 && !layout.shellGridTemplateColumns.includes('px')) {
    throw new Error(`Queue shell columns are not styled on ${pagePath}: ${JSON.stringify(layout)}`);
  }
  if (layout.panelBorderStyle === 'none' || layout.panelBorderWidth === '0px') {
    throw new Error(`Queue panel is unstyled on ${pagePath}: ${JSON.stringify(layout)}`);
  }
}

async function expectTicketsSurface(page, pagePath) {
  await page.goto(pagePath);
  const layout = await page.evaluate(() => {
    const tabs = document.querySelector('.ticket-view-tabs');
    const card = document.querySelector('.card');
    const table = document.querySelector('.tickets-table');
    const board = document.querySelector('.kanban-board');
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      url: window.location.href,
      viewportWidth: window.innerWidth,
      tabsRect: tabs ? rect(tabs) : null,
      cardRect: card ? rect(card) : null,
      tableRect: table ? rect(table) : null,
      boardRect: board ? rect(board) : null,
      tabsDisplay: tabs ? getComputedStyle(tabs).display : null,
      cardBorderStyle: card ? getComputedStyle(card).borderTopStyle : null,
      cardBorderWidth: card ? getComputedStyle(card).borderTopWidth : null,
      tableLayout: table ? getComputedStyle(table).tableLayout : null,
      tableDisplay: table ? getComputedStyle(table).display : null,
      boardDisplay: board ? getComputedStyle(board).display : null,
      inlineTicketStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.ticket-view-tabs') ||
        style.textContent.includes('.kanban-board') ||
        style.textContent.includes('.tl-dropdown')
      ),
      bodyText: document.body.textContent.slice(0, 300)
    };
  });

  if (!layout.tabsRect || layout.tabsDisplay !== 'flex') {
    throw new Error(`Ticket view tabs are unstyled on ${pagePath}: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineTicketStyles) {
    throw new Error(`Tickets page still contains inline ticket layout styles on ${pagePath}`);
  }
  if (pagePath.includes('view=board')) {
    if (!layout.boardRect || layout.boardDisplay !== 'flex') {
      throw new Error(`Tickets board is unstyled on ${pagePath}: ${JSON.stringify(layout)}`);
    }
  } else {
    if (!layout.cardRect || layout.cardBorderStyle === 'none' || layout.cardBorderWidth === '0px') {
      throw new Error(`Tickets card is unstyled on ${pagePath}: ${JSON.stringify(layout)}`);
    }
    if (layout.viewportWidth >= 1024 && (!layout.tableRect || layout.tableDisplay !== 'table')) {
      throw new Error(`Tickets table is not usable on ${pagePath}: ${JSON.stringify(layout)}`);
    }
  }
}

(async () => {
  const health = await fetch(`${baseURL}/index.php?page=health`);
  if (!health.ok) throw new Error(`Health check failed: ${health.status}`);
  const healthJson = await health.json();
  if (healthJson.status !== 'ok' || healthJson.db !== true) {
    throw new Error(`Unexpected health response: ${JSON.stringify(healthJson)}`);
  }

  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL, acceptDownloads: true });

  await page.goto('/index.php?page=login');
  await expectLoginLayout(page);
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=platform|page=work|page=dashboard|dashboard/);
  const signedInUrl = page.url();
  if (signedInUrl.includes('page=platform')) {
    await expectText(page, 'Workspace catalog');
  } else if (signedInUrl.includes('page=work')) {
    await expectText(page, 'Work queues');
  } else {
    await expectText(page, 'Dashboard');
  }
  await expectQueueSurface(page, '/index.php?page=work', '.work-shell', '.work-panel');
  await expectQueueSurface(page, '/index.php?page=inbox', '.inbox-shell', '.inbox-panel');

  const attachmentPath = path.join(os.tmpdir(), 'foxdesk-local-smoke.txt');
  fs.writeFileSync(attachmentPath, 'hello from local smoke\n');

  await page.goto('/index.php?page=new-ticket');
  await page.locator('input[name="title"]').fill('Local smoke ticket');
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Created by local smoke test.</p>';
  });
  await page.locator('#file-input').setInputFiles(attachmentPath);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);
  await expectText(page, 'Local smoke ticket');
  await expectText(page, 'foxdesk-local-smoke.txt');

  const attachmentHref = await page.locator('a[href*="attachment.php"]', { hasText: 'foxdesk-local-smoke.txt' }).first().getAttribute('href');
  if (!attachmentHref) throw new Error('Attachment link was not rendered.');
  const attachmentResponse = await page.request.get(attachmentHref);
  if (!attachmentResponse.ok()) {
    throw new Error(`Attachment download failed: ${attachmentResponse.status()}`);
  }
  const content = await attachmentResponse.text();
  if (!content.includes('hello from local smoke')) {
    throw new Error('Downloaded attachment content did not match.');
  }
  await expectTicketsSurface(page, '/index.php?page=tickets');
  await expectTicketsSurface(page, '/index.php?page=tickets&view=board');

  await browser.close();
  console.log('Local smoke passed.');
})().catch(error => {
  console.error(error);
  process.exit(1);
});
