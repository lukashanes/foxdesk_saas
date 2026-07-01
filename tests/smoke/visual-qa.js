const { chromium } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const baseURL = (process.env.FOXDESK_VISUAL_BASE_URL || process.env.FOXDESK_LOCAL_URL || 'http://127.0.0.1:8090').replace(/\/$/, '');
const publicURL = (process.env.FOXDESK_VISUAL_PUBLIC_URL || process.env.FOXDESK_PUBLIC_URL || baseURL).replace(/\/$/, '');
function defaultPlatformURL() {
  try {
    const parsed = new URL(baseURL);
    if (['127.0.0.1', 'localhost'].includes(parsed.hostname)) {
      return `${parsed.protocol}//platform.localhost${parsed.port ? `:${parsed.port}` : ''}`;
    }
  } catch {
    // Fall through to baseURL.
  }
  return baseURL;
}
const platformURL = (process.env.FOXDESK_VISUAL_PLATFORM_URL || process.env.E2E_PLATFORM_BASE_URL || defaultPlatformURL()).replace(/\/$/, '');
const email = process.env.FOXDESK_VISUAL_ADMIN_EMAIL || process.env.FOXDESK_LOCAL_ADMIN_EMAIL || 'admin@example.test';
const password = process.env.FOXDESK_VISUAL_ADMIN_PASSWORD || process.env.FOXDESK_LOCAL_ADMIN_PASSWORD || 'AdminPass123!';
const outputDir = process.env.FOXDESK_VISUAL_OUTPUT_DIR || fs.mkdtempSync(path.join(os.tmpdir(), 'foxdesk-visual-qa-'));

const viewports = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 },
];

const publicScreens = [
  { name: 'public-cloud', url: `${publicURL}/index.php?page=cloud`, expect: 'FoxDesk' },
  { name: 'login', url: `${baseURL}/index.php?page=login`, expect: 'Sign in' },
];

const appScreens = [
  { name: 'work', path: '/index.php?page=work', expect: 'Dashboard' },
  { name: 'inbox', path: '/index.php?page=inbox', expect: 'Dashboard' },
  { name: 'tickets', path: '/index.php?page=tickets', expect: 'Tickets' },
  { name: 'admin-settings', path: '/index.php?page=admin&section=settings&tab=email', expect: 'Settings' },
  { name: 'billing', path: '/index.php?page=billing', expect: 'Billing' },
  { name: 'reports', path: '/index.php?page=admin&section=reports', expect: 'Reports' },
];

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

async function assertPageReady(page, label, expectedText = '') {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(150);
  const state = await page.evaluate((expectedText) => {
    const bodyText = document.body ? document.body.textContent || '' : '';
    const width = window.innerWidth;
    const height = window.innerHeight;
    const html = document.documentElement;
    const main = document.querySelector('#main-content, main, .fd-main, .op-main, body');
    const mainRect = main ? main.getBoundingClientRect() : null;
    return {
      url: window.location.href,
      width,
      height,
      bodyTextLength: bodyText.trim().length,
      hasExpectedText: expectedText === '' || bodyText.includes(expectedText),
      horizontalOverflow: html ? html.scrollWidth - width : 0,
      mainRect: mainRect ? {
        width: mainRect.width,
        height: mainRect.height,
      } : null,
    };
  }, expectedText);

  if (state.bodyTextLength < 20) {
    throw new Error(`${label} rendered almost no text: ${JSON.stringify(state)}`);
  }
  if (!state.hasExpectedText) {
    throw new Error(`${label} missing expected text "${expectedText}": ${JSON.stringify(state)}`);
  }
  if (state.horizontalOverflow > 4) {
    throw new Error(`${label} has horizontal overflow: ${JSON.stringify(state)}`);
  }
  if (!state.mainRect || state.mainRect.width < Math.min(300, state.width - 20) || state.mainRect.height < 120) {
    throw new Error(`${label} main surface looks collapsed: ${JSON.stringify(state)}`);
  }
}

async function capture(page, viewportName, name, expectedText = '') {
  await assertPageReady(page, `${viewportName}-${name}`, expectedText);
  if (name === 'admin-settings') {
    const navState = await page.evaluate(() => ({
      adminPageNav: document.querySelectorAll('.admin-page-nav').length,
      settingsTabs: document.querySelectorAll('.admin-tabs').length,
      settingsSectionNav: document.querySelectorAll('.settings-section-nav').length,
      settingsSectionCards: document.querySelectorAll('.settings-section-card').length,
      searchInput: Boolean(document.querySelector('#header-search')),
      searchIcon: Boolean(document.querySelector('.header-search-icon')),
    }));
    if (navState.adminPageNav !== 0 || navState.settingsTabs !== 0 || navState.settingsSectionNav !== 1 || navState.settingsSectionCards < 5) {
      throw new Error(`${viewportName}-${name} has duplicate admin navigation: ${JSON.stringify(navState)}`);
    }
    if (!navState.searchInput || !navState.searchIcon) {
      throw new Error(`${viewportName}-${name} missing stable header search controls: ${JSON.stringify(navState)}`);
    }
  }
  if (name === 'reports') {
    const reportsNavState = await page.evaluate(() => ({
      adminPageNav: document.querySelectorAll('.admin-page-nav').length,
      adminTabs: document.querySelectorAll('.admin-tabs').length,
      unifiedWorkspace: document.querySelectorAll('[data-report-unified-workspace]').length,
      reportModeSwitch: document.querySelectorAll('.report-mode-switch').length,
      reportingFlowCards: document.querySelectorAll('.reporting-flow-card').length,
    }));
    if (
      reportsNavState.adminPageNav !== 0 ||
      reportsNavState.adminTabs !== 0 ||
      reportsNavState.unifiedWorkspace !== 1 ||
      reportsNavState.reportModeSwitch !== 0 ||
      reportsNavState.reportingFlowCards !== 1
    ) {
      throw new Error(`${viewportName}-${name} has incorrect report navigation: ${JSON.stringify(reportsNavState)}`);
    }
  }
  const file = path.join(outputDir, `${viewportName}-${name}.png`);
  await page.screenshot({ path: file, fullPage: false });
  return file;
}

async function gotoFirstAvailable(page, urls) {
  let lastError = null;
  for (const url of urls) {
    try {
      await page.goto(url);
      return url;
    } catch (error) {
      lastError = error;
      await page.evaluate(() => window.stop()).catch(() => {});
      await page.goto('about:blank').catch(() => {});
    }
  }
  throw lastError;
}

async function capturePlatform(page, viewportName) {
  await tryLoginAt(page, platformURL);
  await gotoFirstAvailable(page, [
    `${platformURL}/index.php?page=platform`,
    `${baseURL}/index.php?page=platform`,
    `${baseURL}/index.php?page=login`,
  ]);
  return capture(page, viewportName, 'platform');
}

async function login(page) {
  await loginAt(page, baseURL);
}

async function loginAt(page, host) {
  await page.goto(`${host}/index.php?page=login`);
  const emailInput = page.locator('input[name="email"]');
  if (await emailInput.count()) {
    await emailInput.fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
  }
}

async function tryLoginAt(page, host) {
  try {
    await loginAt(page, host);
    return true;
  } catch {
    await page.evaluate(() => window.stop()).catch(() => {});
    await page.goto('about:blank').catch(() => {});
    return false;
  }
}

async function firstClientPath(page) {
  await page.goto(`${baseURL}/index.php?page=admin&section=organizations`);
  const href = await page.evaluate(() => {
    const link = [...document.querySelectorAll('a[href*="page=client&id="]')].find((item) => {
      const rect = item.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    });
    return link ? link.getAttribute('href') : '';
  });
  return href ? (href.startsWith('http') ? href : `${baseURL}/${href.replace(/^\/+/, '')}`) : '';
}

async function ticketDetailPath(page) {
  await page.goto(`${baseURL}/index.php?page=tickets`);
  let href = await page.evaluate(() => {
    const link = [...document.querySelectorAll('a[href*="page=ticket&id="]')].find((item) => {
      const rect = item.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    });
    return link ? link.getAttribute('href') : '';
  });
  if (href) {
    return href.startsWith('http') ? href : `${baseURL}/${href.replace(/^\/+/, '')}`;
  }

  await page.goto(`${baseURL}/index.php?page=new-ticket`);
  await page.locator('input[name="title"]').fill('Visual QA ticket');
  await page.locator('#description-input').evaluate((input) => {
    input.value = '<p>Created by the visual QA smoke.</p>';
  });
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/page=ticket&id=\d+/);
  return page.url();
}

(async () => {
  ensureDir(outputDir);
  const browser = await chromium.launch();
  const screenshots = [];

  try {
    for (const viewport of viewports) {
      const page = await browser.newPage({ viewport: { width: viewport.width, height: viewport.height } });

      for (const screen of publicScreens) {
        await page.goto(screen.url);
        screenshots.push(await capture(page, viewport.name, screen.name, screen.expect));
      }

      await login(page);

      for (const screen of appScreens) {
        await page.goto(`${baseURL}${screen.path}`);
        screenshots.push(await capture(page, viewport.name, screen.name, screen.expect));
      }

      screenshots.push(await capturePlatform(page, viewport.name));

      const client = await firstClientPath(page);
      if (client) {
        await page.goto(client);
        screenshots.push(await capture(page, viewport.name, 'client', 'Client'));
      }

      const ticket = await ticketDetailPath(page);
      await page.goto(ticket);
      screenshots.push(await capture(page, viewport.name, 'ticket-detail', 'Activity'));

      await page.close();
    }
  } finally {
    await browser.close();
  }

  const result = {
    status: 'passed',
    baseURL,
    publicURL,
    outputDir,
    screenshots,
  };
  fs.writeFileSync(path.join(outputDir, 'visual-qa-result.json'), JSON.stringify(result, null, 2));
  fs.writeFileSync(
    path.join(outputDir, 'visual-qa-report.md'),
    ['# FoxDesk Visual QA', '', `Base URL: ${baseURL}`, `Public URL: ${publicURL}`, '', ...screenshots.map((file) => `- ${file}`), ''].join('\n')
  );
  console.log(`Visual QA passed. Screenshots: ${outputDir}`);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
