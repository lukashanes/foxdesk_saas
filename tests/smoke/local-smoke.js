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
    const main = document.querySelector('#main-content');
    const content = document.querySelector('.app-content');
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };
    const styleTagCount = document.querySelectorAll('style').length;
    const contentStyle = content ? getComputedStyle(content) : null;
    return {
      url: window.location.href,
      viewportWidth: window.innerWidth,
      mainRect: main ? rect(main) : null,
      mainDisplay: main ? getComputedStyle(main).display : null,
      mainBackground: main ? getComputedStyle(main).backgroundColor : null,
      contentRect: content ? rect(content) : null,
      contentPaddingLeft: contentStyle ? parseFloat(contentStyle.paddingLeft) : null,
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
  if (!layout.mainRect || layout.mainDisplay !== 'flex' || layout.mainRect.width < 320) {
    throw new Error(`App frame is unstyled on ${pagePath}: ${JSON.stringify(layout)}`);
  }
  if (!layout.contentRect || layout.contentPaddingLeft < 16) {
    throw new Error(`App content padding is missing on ${pagePath}: ${JSON.stringify(layout)}`);
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
  if (layout.viewportWidth >= 1024 && layout.panelRect.width < 420) {
    throw new Error(`Queue panel is too narrow on ${pagePath}: ${JSON.stringify(layout)}`);
  }
}

async function expectDashboardSurface(page) {
  await page.goto('/index.php?page=dashboard');
  const layout = await page.evaluate(() => {
    const grid = document.querySelector('.db-grid');
    const kpi = document.querySelector('.db-kpi-grid');
    const widget = document.querySelector('.db-widget');
    const configButton = document.querySelector('#dashboard-config-btn');
    const configPanel = document.querySelector('#dashboard-config-panel');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height };
    };

    return {
      url: window.location.href,
      gridRect: grid ? rect(grid) : null,
      gridDisplay: grid ? getComputedStyle(grid).display : null,
      gridColumns: grid ? getComputedStyle(grid).gridTemplateColumns : null,
      kpiRect: kpi ? rect(kpi) : null,
      kpiDisplay: kpi ? getComputedStyle(kpi).display : null,
      widgetRect: widget ? rect(widget) : null,
      widgetDisplay: widget ? getComputedStyle(widget).display : null,
      configButtonRect: configButton ? rect(configButton) : null,
      configPanelHidden: configPanel ? configPanel.classList.contains('hidden') : null,
      bodyOpacity: getComputedStyle(document.body).opacity,
      themeLinks,
      inlineDashboardStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.db-grid') ||
        style.textContent.includes('.db-onboarding') ||
        style.textContent.includes('.db-agent-activity')
      )
    };
  });

  if (!layout.gridRect || layout.gridDisplay !== 'grid') {
    throw new Error(`Dashboard grid is missing or unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.kpiRect || layout.kpiDisplay !== 'flex') {
    throw new Error(`Dashboard KPI strip is missing or unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.widgetRect || layout.widgetDisplay === 'block') {
    throw new Error(`Dashboard widget shell is unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.configButtonRect || layout.configPanelHidden !== true) {
    throw new Error(`Dashboard customize controls are not ready: ${JSON.stringify(layout)}`);
  }
  if (layout.bodyOpacity !== '1') {
    throw new Error(`Dashboard body is not fully visible: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Dashboard does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineDashboardStyles) {
    throw new Error(`Dashboard still contains page-level dashboard layout styles: ${JSON.stringify(layout)}`);
  }

  await page.locator('#dashboard-config-btn').click();
  const opened = await page.evaluate(() => {
    const panel = document.querySelector('#dashboard-config-panel');
    return {
      hidden: panel ? panel.classList.contains('hidden') : null,
      display: panel ? getComputedStyle(panel).display : null,
      visibleItems: panel ? [...panel.querySelectorAll('[data-config-section]')].filter(item => {
        const rect = item.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
      }).length : 0
    };
  });
  if (opened.hidden !== false || opened.display === 'none' || opened.visibleItems < 1) {
    throw new Error(`Dashboard customize panel did not open: ${JSON.stringify(opened)}`);
  }
  await page.keyboard.press('Escape');
}

async function expectPublicRefactorSurfaces(page) {
  await page.goto('/index.php?page=signup');
  const signup = await page.evaluate(() => {
    const shell = document.querySelector('.signup-shell');
    const card = document.querySelector('.signup-card');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    return {
      bodyClass: document.body.className,
      shellDisplay: shell ? getComputedStyle(shell).display : null,
      cardWidth: card ? card.getBoundingClientRect().width : 0,
      themeLinks,
      inlineSignupStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.signup-shell') ||
        style.textContent.includes('.signup-input')
      )
    };
  });
  if (!signup.bodyClass.includes('signup-page') || signup.shellDisplay !== 'grid' || signup.cardWidth < 300) {
    throw new Error(`Signup page layout is broken: ${JSON.stringify(signup)}`);
  }
  if (!signup.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Signup page does not use a versioned theme.css link: ${JSON.stringify(signup)}`);
  }
  if (signup.inlineSignupStyles) {
    throw new Error(`Signup page still contains local signup styles: ${JSON.stringify(signup)}`);
  }

  await page.goto('/index.php?page=legal&type=terms');
  const legal = await page.evaluate(() => {
    const shell = document.querySelector('.legal-shell');
    const card = document.querySelector('.legal-card');
    const nav = document.querySelector('.legal-nav');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    return {
      bodyClass: document.body.className,
      shellWidth: shell ? shell.getBoundingClientRect().width : 0,
      cardBorderStyle: card ? getComputedStyle(card).borderTopStyle : null,
      navDisplay: nav ? getComputedStyle(nav).display : null,
      themeLinks,
      inlineLegalStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.legal-shell') ||
        style.textContent.includes('.legal-card')
      )
    };
  });
  if (!legal.bodyClass.includes('legal-page') || legal.shellWidth < 320 || legal.cardBorderStyle === 'none') {
    throw new Error(`Legal page layout is broken: ${JSON.stringify(legal)}`);
  }
  if (legal.navDisplay !== 'flex') {
    throw new Error(`Legal navigation is unstyled: ${JSON.stringify(legal)}`);
  }
  if (!legal.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Legal page does not use a versioned theme.css link: ${JSON.stringify(legal)}`);
  }
  if (legal.inlineLegalStyles) {
    throw new Error(`Legal page still contains local legal styles: ${JSON.stringify(legal)}`);
  }
}

async function expectPlatformSurface(page) {
  const response = await page.request.get('/index.php?page=platform', {
    maxRedirects: 0,
    timeout: 10000
  });
  if (response.status() >= 300 && response.status() < 400) {
    return;
  }

  await page.goto('/index.php?page=platform', { waitUntil: 'domcontentloaded', timeout: 10000 });
  const layout = await page.evaluate(() => {
    const shell = document.querySelector('.op-shell');
    const sidebar = document.querySelector('.op-sidebar');
    const catalog = document.querySelector('#workspaces');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    return {
      url: window.location.href,
      bodyClass: document.body.className,
      shellDisplay: shell ? getComputedStyle(shell).display : null,
      sidebarDisplay: sidebar ? getComputedStyle(sidebar).display : null,
      catalogExists: !!catalog,
      themeLinks,
      inlinePlatformStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.op-shell') ||
        style.textContent.includes('--op-bg')
      ),
      bodyText: document.body.textContent.slice(0, 240)
    };
  });
  if (!layout.url.includes('page=platform')) {
    return;
  }
  if (!layout.bodyClass.includes('op-page') && layout.url.includes('page=login') && layout.bodyText.includes('FoxDesk Platform')) {
    return;
  }
  if (!layout.bodyClass.includes('op-page') || layout.shellDisplay !== 'block' || layout.sidebarDisplay !== 'flex') {
    throw new Error(`Platform console layout is broken: ${JSON.stringify(layout)}`);
  }
  if (!layout.catalogExists || !layout.bodyText.includes('Workspace catalog')) {
    throw new Error(`Platform console content is missing: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Platform console does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }
  if (layout.inlinePlatformStyles) {
    throw new Error(`Platform console still contains local operator styles: ${JSON.stringify(layout)}`);
  }
}

async function expectSettingsSurface(page) {
  await page.goto('/index.php?page=admin&section=settings');
  const layout = await page.evaluate(() => {
    const shell = document.querySelector('.admin-legacy-page, .admin-shell, .card');
    const sectionNav = document.querySelector('.settings-section-nav');
    const sectionCards = document.querySelectorAll('.settings-section-card');
    const adminTabs = document.querySelector('.admin-tabs');
    const adminPageNav = document.querySelector('.admin-page-nav');
    const themeLinks = [...document.querySelectorAll('link[href*="theme.css"]')].map(link => link.getAttribute('href'));
    return {
      url: window.location.href,
      shellWidth: shell ? shell.getBoundingClientRect().width : 0,
      sectionNavDisplay: sectionNav ? getComputedStyle(sectionNav).display : null,
      sectionCardCount: sectionCards.length,
      adminTabsCount: adminTabs ? 1 : 0,
      adminPageNavCount: adminPageNav ? 1 : 0,
      themeLinks,
      inlineSettingsStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('Operations overview') ||
        style.textContent.includes('system-notice')
      )
    };
  });
  if (layout.shellWidth < 320 || !['grid'].includes(layout.sectionNavDisplay) || layout.sectionCardCount < 5) {
    throw new Error(`Settings surface is missing or unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.adminTabsCount || layout.adminPageNavCount) {
    throw new Error(`Settings page must not render duplicate horizontal admin menus: ${JSON.stringify(layout)}`);
  }
  if (!layout.themeLinks.some(href => href && href.includes('theme.css?v='))) {
    throw new Error(`Settings page does not use a versioned theme.css link: ${JSON.stringify(layout)}`);
  }
  if (layout.inlineSettingsStyles) {
    throw new Error(`Settings page still contains moved notice/system styles: ${JSON.stringify(layout)}`);
  }
}

async function expectMovedAdminPageStyles(page) {
  await page.goto('/index.php?page=notifications');
  const notifications = await page.evaluate(() => {
    const wrap = document.querySelector('.notif-page-wrap');
    const tabs = document.querySelector('.notif-filter-tabs');
    return {
      wrapWidth: wrap ? wrap.getBoundingClientRect().width : 0,
      tabsDisplay: tabs ? getComputedStyle(tabs).display : null,
      inlineNotificationStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.notif-page-wrap') ||
        style.textContent.includes('.notif-card')
      )
    };
  });
  if (notifications.wrapWidth < 320 || notifications.tabsDisplay !== 'flex') {
    throw new Error(`Notifications page is unstyled: ${JSON.stringify(notifications)}`);
  }
  if (notifications.inlineNotificationStyles) {
    throw new Error(`Notifications page still contains local notification styles: ${JSON.stringify(notifications)}`);
  }

  await page.goto('/index.php?page=admin&section=activity');
  const activity = await page.evaluate(() => {
    const card = document.querySelector('.act-card');
    const tabs = document.querySelector('.act-tabs, .admin-tabs');
    return {
      cardBorderStyle: card ? getComputedStyle(card).borderTopStyle : null,
      tabsDisplay: tabs ? getComputedStyle(tabs).display : null,
      inlineActivityStyles: [...document.querySelectorAll('style')].some(style =>
        style.textContent.includes('.act-card') ||
        style.textContent.includes('.act-table')
      )
    };
  });
  if (activity.cardBorderStyle === 'none' || !['flex', 'inline-flex'].includes(activity.tabsDisplay)) {
    throw new Error(`Activity page is unstyled: ${JSON.stringify(activity)}`);
  }
  if (activity.inlineActivityStyles) {
    throw new Error(`Activity page still contains local activity styles: ${JSON.stringify(activity)}`);
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
    const modeSwitch = document.querySelector('.report-mode-switch');
    const modeLinks = [...document.querySelectorAll('.report-mode-link')].map(link => link.textContent.trim());
    const adminTabs = document.querySelector('.admin-tabs');
    const adminPageNav = document.querySelector('.admin-page-nav');
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
      modeSwitchRect: modeSwitch ? rect(modeSwitch) : null,
      modeSwitchDisplay: modeSwitch ? getComputedStyle(modeSwitch).display : null,
      modeLinks,
      adminTabsCount: adminTabs ? 1 : 0,
      adminPageNavCount: adminPageNav ? 1 : 0,
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
  if (!layout.modeSwitchRect || !['flex', 'inline-flex'].includes(layout.modeSwitchDisplay)) {
    throw new Error(`Reports mode switch is missing or unstyled: ${JSON.stringify(layout)}`);
  }
  if (!layout.modeLinks.includes('Time overview') || !layout.modeLinks.includes('Billing review') || !layout.modeLinks.includes('Published reports')) {
    throw new Error(`Reports modes are incomplete: ${JSON.stringify(layout)}`);
  }
  if (layout.adminTabsCount || layout.adminPageNavCount) {
    throw new Error(`Reports page must not render duplicate horizontal admin menus: ${JSON.stringify(layout)}`);
  }
  if (layout.flowCardRect) {
    throw new Error(`Time overview must not show the billing review flow card: ${JSON.stringify(layout)}`);
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
  if (layout.heroDisplay !== 'grid' || layout.statsDisplay !== 'grid' || layout.gridDisplay !== 'grid') {
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
    const workActions = document.querySelector('.ticket-work-panel__actions');
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
      workActionsBorderStyle: workActions ? getComputedStyle(workActions).borderTopStyle : null,
      workActionsBorderWidth: workActions ? getComputedStyle(workActions).borderTopWidth : null,
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
  if (layout.workPanelDisplay !== 'grid') {
    throw new Error(`Ticket detail work panel is unstyled: ${JSON.stringify(layout)}`);
  }
  if (layout.workActionsBorderStyle === 'none' || layout.workActionsBorderWidth === '0px') {
    throw new Error(`Ticket detail action bar is not separated from the title: ${JSON.stringify(layout)}`);
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

  await expectPublicRefactorSurfaces(page);

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
    await expectText(page, 'Dashboard');
  } else {
    await expectText(page, 'Dashboard');
  }
  await expectQueueSurface(page, '/index.php?page=work', '.workspace-queue-shell', '.workspace-queue-panel');
  await expectQueueSurface(page, '/index.php?page=inbox', '.workspace-queue-shell', '.workspace-queue-panel');
  await expectPlatformSurface(page);
  await expectDashboardSurface(page);
  await expectSettingsSurface(page);
  await expectMovedAdminPageStyles(page);
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
