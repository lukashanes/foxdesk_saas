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

async function expectNewTicketSurface(page) {
  const layout = await page.evaluate(() => {
    const form = document.querySelector('#new-ticket-form');
    const editor = document.querySelector('.editor-wrapper');
    const uploadZone = document.querySelector('#upload-zone');
    const optionPill = document.querySelector('.option-pill');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      url: window.location.href,
      formRect: form ? rect(form) : null,
      formBorderStyle: form ? getComputedStyle(form).borderTopStyle : null,
      formBorderWidth: form ? getComputedStyle(form).borderTopWidth : null,
      editorRect: editor ? rect(editor) : null,
      editorBorderStyle: editor ? getComputedStyle(editor).borderTopStyle : null,
      editorBorderWidth: editor ? getComputedStyle(editor).borderTopWidth : null,
      uploadZoneRect: uploadZone ? rect(uploadZone) : null,
      uploadZoneBorderStyle: uploadZone ? getComputedStyle(uploadZone).borderTopStyle : null,
      uploadZoneBorderWidth: uploadZone ? getComputedStyle(uploadZone).borderTopWidth : null,
      optionPillDisplay: optionPill ? getComputedStyle(optionPill).display : null,
      optionPillHeight: optionPill ? getComputedStyle(optionPill).height : null,
      themeLinks,
      inlineNewTicketStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('#description-editor') ||
        style.textContent.includes('.option-pill') ||
        style.textContent.includes('.editor-wrapper')
      )
    };
  });

  if (!layout.formRect || layout.formBorderStyle === 'none' || layout.formBorderWidth === '0px') {
    throw new Error(`New ticket form card is unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.editorRect || layout.editorBorderStyle === 'none' || layout.editorBorderWidth === '0px') {
    throw new Error(`New ticket editor is unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.uploadZoneRect || layout.uploadZoneBorderStyle === 'none' || layout.uploadZoneBorderWidth === '0px') {
    throw new Error(`New ticket upload zone is unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.optionPillDisplay !== null && !['flex', 'inline-flex'].includes(layout.optionPillDisplay)) {
    throw new Error(`New ticket option pills are unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`New ticket does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineNewTicketStyles) {
    throw new Error(`New ticket still contains page-level layout styles: ${JSON.stringify(layout)}`);
  }
}

async function expectReportsSurface(page) {
  await page.goto('/index.php?page=admin&section=reports');
  const layout = await page.evaluate(() => {
    const shell = document.querySelector('.admin-legacy-page');
    const tabs = document.querySelector('.admin-tabs');
    const flowCard = document.querySelector('.reporting-flow-card');
    const filterCard = document.querySelector('#report-filters');
    const card = document.querySelector('.card');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      url: window.location.href,
      heading: document.querySelector('h1') ? document.querySelector('h1').textContent.trim() : '',
      shellRect: shell ? rect(shell) : null,
      tabsRect: tabs ? rect(tabs) : null,
      tabsDisplay: tabs ? getComputedStyle(tabs).display : null,
      flowCardRect: flowCard ? rect(flowCard) : null,
      filterCardRect: filterCard ? rect(filterCard) : null,
      cardBorderStyle: card ? getComputedStyle(card).borderTopStyle : null,
      cardBorderWidth: card ? getComputedStyle(card).borderTopWidth : null,
      bodyOpacity: getComputedStyle(document.body).opacity,
      themeLinks,
      inlineReportStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('@media print') ||
        style.textContent.includes('Time Report') ||
        style.textContent.includes('#report-apply-btn')
      )
    };
  });

  if (!layout.shellRect || layout.shellRect.width < 320) {
    throw new Error(`Reports shell is missing or collapsed: ${JSON.stringify(layout)}`);
  }
  if (!layout.tabsRect || !['flex', 'inline-flex'].includes(layout.tabsDisplay)) {
    throw new Error(`Reports tabs are unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.flowCardRect && layout.flowCardRect.width < 320) {
    throw new Error(`Reports billing review flow is collapsed: ${JSON.stringify(layout)}`);
  }
  if (!layout.filterCardRect && !layout.cardBorderStyle) {
    throw new Error(`Reports content cards are missing: ${JSON.stringify(layout)}`);
  }
  if (layout.cardBorderStyle === 'none' || layout.cardBorderWidth === '0px') {
    throw new Error(`Reports card styling is missing: ${JSON.stringify(layout)}`);
  }
  if (layout.bodyOpacity !== '1') {
    throw new Error(`Reports page shell is not fully visible: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Reports page does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineReportStyles) {
    throw new Error(`Reports page still contains page-level print styles: ${JSON.stringify(layout)}`);
  }
}

async function expectBillingSurface(page) {
  await page.goto('/index.php?page=billing');
  const layout = await page.evaluate(() => {
    const shell = document.querySelector('.billing-page');
    const card = document.querySelector('.billing-card');
    const plan = document.querySelector('.billing-plan-panel');
    const summary = document.querySelector('.billing-summary-grid');
    const storage = document.querySelector('.billing-storage-progress');
    const actions = document.querySelector('.billing-actions');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      url: window.location.href,
      heading: document.querySelector('h1') ? document.querySelector('h1').textContent.trim() : '',
      shellRect: shell ? rect(shell) : null,
      cardRect: card ? rect(card) : null,
      planRect: plan ? rect(plan) : null,
      summaryDisplay: summary ? getComputedStyle(summary).display : null,
      storageValue: storage ? Number(storage.getAttribute('value')) : null,
      storageMax: storage ? Number(storage.getAttribute('max')) : null,
      actionsDisplay: actions ? getComputedStyle(actions).display : null,
      cardBorderStyle: card ? getComputedStyle(card).borderTopStyle : null,
      cardBorderWidth: card ? getComputedStyle(card).borderTopWidth : null,
      bodyOpacity: getComputedStyle(document.body).opacity,
      scopedInlineStyles: shell ? shell.querySelectorAll('[style]').length : null,
      themeLinks
    };
  });

  if (!layout.shellRect || !layout.cardRect || layout.heading !== 'Billing') {
    throw new Error(`Billing page did not render: ${JSON.stringify(layout)}`);
  }
  if (layout.cardBorderStyle === 'none' || layout.cardBorderWidth === '0px') {
    throw new Error(`Billing card styling is missing: ${JSON.stringify(layout)}`);
  }
  if (!layout.planRect || layout.summaryDisplay !== 'grid') {
    throw new Error(`Billing plan/summary layout is unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.storageMax !== 100 || layout.storageValue === null || layout.storageValue < 0 || layout.storageValue > 100) {
    throw new Error(`Billing storage progress is invalid: ${JSON.stringify(layout)}`);
  }
  if (layout.actionsDisplay !== 'flex') {
    throw new Error(`Billing actions are unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.bodyOpacity !== '1') {
    throw new Error(`Billing page shell is not fully visible: ${JSON.stringify(layout)}`);
  }
  if (layout.scopedInlineStyles !== 0) {
    throw new Error(`Billing page still has scoped inline styles: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Billing page does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }
}

async function expectClientSurface(page) {
  await page.goto('/index.php?page=admin&section=organizations');
  const clientHref = await page.evaluate(() => {
    const links = [...document.querySelectorAll('a[href*="page=client&id="]')];
    const visibleLink = links.find(link => {
      const bounds = link.getBoundingClientRect();
      return bounds.width > 0 && bounds.height > 0;
    });
    return (visibleLink || links[0])?.getAttribute('href') || '';
  });

  if (!clientHref) {
    throw new Error('No client detail link was found on the organization admin page.');
  }

  const clientPath = clientHref.startsWith('http') || clientHref.startsWith('/')
    ? clientHref
    : `/${clientHref}`;

  await page.goto(clientPath);
  const layout = await page.evaluate(() => {
    const shell = document.querySelector('.client-center');
    const hero = document.querySelector('.client-hero');
    const stats = document.querySelector('.client-stats');
    const grid = document.querySelector('.client-grid');
    const tabs = document.querySelector('.client-tabs');
    const profile = document.querySelector('.client-profile-list');
    const ticket = document.querySelector('.client-ticket');
    const status = document.querySelector('.client-ticket-status');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    const scopedInlineStyles = shell ? [...shell.querySelectorAll('[style]')].map(node => ({
      className: node.className,
      style: node.getAttribute('style')
    })) : [];
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      url: window.location.href,
      heading: document.querySelector('h1') ? document.querySelector('h1').textContent.trim() : '',
      shellRect: shell ? rect(shell) : null,
      heroDisplay: hero ? getComputedStyle(hero).display : null,
      statsDisplay: stats ? getComputedStyle(stats).display : null,
      gridDisplay: grid ? getComputedStyle(grid).display : null,
      tabsDisplay: tabs ? getComputedStyle(tabs).display : null,
      profileDisplay: profile ? getComputedStyle(profile).display : null,
      ticketDisplay: ticket ? getComputedStyle(ticket).display : null,
      statusBackground: status ? getComputedStyle(status).backgroundColor : null,
      bodyOpacity: getComputedStyle(document.body).opacity,
      themeLinks,
      scopedInlineStyles,
      inlineClientStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.client-center') ||
        style.textContent.includes('.client-ticket') ||
        style.textContent.includes('.client-profile')
      )
    };
  });

  if (!layout.shellRect || layout.shellRect.width < 320 || !layout.heading) {
    throw new Error(`Client detail did not render: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineClientStyles) {
    throw new Error(`Client detail still contains page-level layout styles: ${JSON.stringify(layout)}`);
  }
  if (layout.heroDisplay !== 'flex' || layout.statsDisplay !== 'grid' || layout.gridDisplay !== 'grid') {
    throw new Error(`Client detail layout is unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.tabsDisplay !== 'flex') {
    throw new Error(`Client tabs are unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.profileDisplay !== 'grid') {
    throw new Error(`Client profile is unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.ticketDisplay !== null && layout.ticketDisplay !== 'grid') {
    throw new Error(`Client ticket rows are unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.scopedInlineStyles.some(item => !String(item.style || '').includes('--client-status-color'))) {
    throw new Error(`Client detail has unexpected scoped inline styles: ${JSON.stringify(layout)}`);
  }
  if (layout.bodyOpacity !== '1') {
    throw new Error(`Client detail shell is not fully visible: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Client detail does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }

  await page.locator('.client-tab[href*="work_view=all"]').click();
  await page.waitForURL(/work_view=all/);
  const activeTab = await page.locator('.client-tab.is-active').textContent();
  if (!activeTab || !activeTab.includes('All')) {
    throw new Error(`Client ticket tab did not switch to All: ${activeTab}`);
  }
}

async function expectTicketDetailSurface(page) {
  const layout = await page.evaluate(() => {
    const editor = document.querySelector('.editor-wrapper');
    const commentForm = document.querySelector('#comment-form');
    const timelineOverlay = document.querySelector('#timeline-overlay');
    const workPanel = document.querySelector('.ticket-work-panel');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      url: window.location.href,
      editorRect: editor ? rect(editor) : null,
      editorBorderStyle: editor ? getComputedStyle(editor).borderTopStyle : null,
      editorBorderWidth: editor ? getComputedStyle(editor).borderTopWidth : null,
      commentFormRect: commentForm ? rect(commentForm) : null,
      workPanelDisplay: workPanel ? getComputedStyle(workPanel).display : null,
      timelineDisplay: timelineOverlay ? getComputedStyle(timelineOverlay).display : null,
      timelineAriaHidden: timelineOverlay ? timelineOverlay.getAttribute('aria-hidden') : null,
      themeLinks,
      inlineDetailStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.editor-wrapper') ||
        style.textContent.includes('.ticket-work-panel') ||
        style.textContent.includes('.ticket-timeline-overlay') ||
        style.textContent.includes('.tl-event')
      )
    };
  });

  if (!layout.editorRect || layout.editorBorderStyle === 'none' || layout.editorBorderWidth === '0px') {
    throw new Error(`Ticket detail editor is unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.commentFormRect) {
    throw new Error(`Ticket detail comment form is missing: ${JSON.stringify(layout)}`);
  }
  if (layout.workPanelDisplay !== 'flex') {
    throw new Error(`Ticket detail work panel is unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.timelineDisplay !== null && layout.timelineDisplay !== 'none') {
    throw new Error(`Ticket timeline overlay should be hidden by default: ${JSON.stringify(layout)}`);
  }
  if (layout.timelineAriaHidden !== null && layout.timelineAriaHidden !== 'true') {
    throw new Error(`Ticket timeline overlay should be aria-hidden by default: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Ticket detail does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineDetailStyles) {
    throw new Error(`Ticket detail still contains page-level layout styles: ${JSON.stringify(layout)}`);
  }

  const timelineButton = page.locator('button[onclick^="openTicketTimeline"]');
  const timelineButtonCount = await timelineButton.count();
  if (timelineButtonCount > 0) {
    if (timelineButtonCount !== 1) {
      throw new Error(`Expected one timeline button, found ${timelineButtonCount}`);
    }
    await timelineButton.click();
    await page.waitForFunction(() => {
      const overlay = document.querySelector('#timeline-overlay');
      const content = document.querySelector('#timeline-content');
      return overlay &&
        overlay.classList.contains('is-open') &&
        overlay.getAttribute('aria-hidden') === 'false' &&
        content &&
        !content.textContent.includes('Loading');
    });
    const openState = await page.evaluate(() => {
      const overlay = document.querySelector('#timeline-overlay');
      return {
        display: overlay ? getComputedStyle(overlay).display : null,
        bodyLocked: document.body.classList.contains('ticket-timeline-open'),
        hasContent: !!document.querySelector('#timeline-content .tl-event, #timeline-content .ticket-timeline-empty')
      };
    });
    if (openState.display !== 'flex' || !openState.bodyLocked || !openState.hasContent) {
      throw new Error(`Ticket timeline did not open correctly: ${JSON.stringify(openState)}`);
    }
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => {
      const overlay = document.querySelector('#timeline-overlay');
      return overlay && !overlay.classList.contains('is-open') && overlay.getAttribute('aria-hidden') === 'true';
    });
    const closeState = await page.evaluate(() => ({
      bodyLocked: document.body.classList.contains('ticket-timeline-open'),
      display: getComputedStyle(document.querySelector('#timeline-overlay')).display
    }));
    if (closeState.bodyLocked || closeState.display !== 'none') {
      throw new Error(`Ticket timeline did not close correctly: ${JSON.stringify(closeState)}`);
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
  await expectReportsSurface(page);
  await expectBillingSurface(page);
  await expectClientSurface(page);

  const attachmentPath = path.join(os.tmpdir(), 'foxdesk-local-smoke.txt');
  fs.writeFileSync(attachmentPath, 'hello from local smoke\n');

  await page.goto('/index.php?page=new-ticket');
  await expectNewTicketSurface(page);
  await page.locator('input[name="title"]').fill('Local smoke ticket');
  await page.locator('#description-input').evaluate(input => {
    input.value = '<p>Created by local smoke test.</p>';
  });
  await page.locator('#file-input').setInputFiles(attachmentPath);
  const previewState = await page.evaluate(() => {
    const preview = document.querySelector('#file-preview');
    return {
      hidden: preview ? preview.classList.contains('hidden') : true,
      text: preview ? preview.textContent : ''
    };
  });
  if (previewState.hidden || !previewState.text.includes('foxdesk-local-smoke.txt')) {
    throw new Error(`New ticket attachment preview did not render: ${JSON.stringify(previewState)}`);
  }
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);
  await expectText(page, 'Local smoke ticket');
  await expectText(page, 'foxdesk-local-smoke.txt');
  await expectTicketDetailSurface(page);

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
