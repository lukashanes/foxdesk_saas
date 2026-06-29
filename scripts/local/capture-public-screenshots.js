const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

const baseURL = process.env.FOXDESK_LOCAL_URL || 'http://127.0.0.1:8090';
const email = process.env.FOXDESK_LOCAL_ADMIN_EMAIL || 'admin@example.test';
const password = process.env.FOXDESK_LOCAL_ADMIN_PASSWORD || 'AdminPass123!';
const root = path.resolve(__dirname, '../..');
const tmpDir = process.env.FOXDESK_SCREENSHOT_TMP || '/tmp/foxdesk-screenshots-refresh-20260629';
const publicDir = path.join(root, 'assets/public');
const viewport = { width: 1200, height: 675 };

const shots = [
  {
    key: 'dashboard',
    route: '/index.php?page=work',
    outputs: {
      light: 'dashboard-light.webp',
      dark: 'dashboard-dark.webp'
    }
  },
  {
    key: 'ticket-detail',
    route: '/index.php?page=ticket&id=1',
    outputs: {
      light: 'ticket-detail-light.webp',
      dark: 'ticket-detail-dark.webp'
    }
  },
  {
    key: 'time-report',
    route: '/index.php?page=admin&section=reports&tab=time&period=this_month',
    outputs: {
      light: 'time-report-light.webp',
      dark: 'time-report-dark.webp'
    }
  }
];

function findBinary(name) {
  const candidates = [
    name,
    `/opt/homebrew/bin/${name}`,
    `/usr/local/bin/${name}`,
    `/usr/bin/${name}`
  ];
  for (const candidate of candidates) {
    try {
      execFileSync(candidate, ['-version'], { stdio: 'ignore' });
      return candidate;
    } catch (_) {}
  }
  return name;
}

function convertToWebp(source, target) {
  const cwebp = findBinary('cwebp');
  execFileSync(cwebp, ['-quiet', '-q', '86', source, '-o', target], { stdio: 'inherit' });
}

function convertPreviewJpeg(source, target) {
  try {
    execFileSync('/usr/bin/sips', ['-s', 'format', 'jpeg', source, '--out', target], { stdio: 'ignore' });
  } catch (_) {
    fs.copyFileSync(source, target);
  }
}

async function login(page) {
  await page.goto(`${baseURL}/index.php?page=logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.goto(`${baseURL}/index.php?page=login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await Promise.all([
    page.waitForURL(/page=(work|platform|dashboard)/, { timeout: 15000 }),
    page.getByRole('button', { name: 'Sign in' }).click()
  ]);
}

async function captureTheme(browser, theme) {
  const context = await browser.newContext({
    viewport,
    deviceScaleFactor: 1,
    colorScheme: theme === 'dark' ? 'dark' : 'light'
  });
  await context.addInitScript((themeName) => {
    try {
      window.localStorage.setItem('theme', themeName);
      window.localStorage.setItem('foxdesk-cloud-theme', themeName);
    } catch (_) {}
    document.documentElement.setAttribute('data-theme', themeName);
  }, theme);

  const page = await context.newPage();
  await login(page);

  const results = [];
  for (const shot of shots) {
    await page.goto(`${baseURL}${shot.route}`, { waitUntil: 'domcontentloaded' });
    await page.evaluate((themeName) => {
      document.documentElement.setAttribute('data-theme', themeName);
      window.scrollTo(0, 0);
    }, theme);
    await page.waitForTimeout(500);

    const pngPath = path.join(tmpDir, `${shot.key}-${theme}.png`);
    const webpPath = path.join(publicDir, shot.outputs[theme]);
    await page.screenshot({ path: pngPath, fullPage: false });
    convertToWebp(pngPath, webpPath);

    const state = await page.evaluate(() => ({
      url: location.href,
      title: document.title,
      h1: document.querySelector('h1')?.textContent?.trim() || '',
      avatarImages: document.querySelectorAll('.user-avatar__image[src]').length,
      brokenImages: Array.from(document.images)
        .filter((image) => {
          const src = image.getAttribute('src');
          return !!src && image.complete && image.naturalWidth === 0;
        })
        .map((image) => image.getAttribute('src'))
        .slice(0, 8),
      overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth
    }));
    results.push({ key: shot.key, theme, pngPath, webpPath, state });
  }

  await context.close();
  return results;
}

(async () => {
  fs.mkdirSync(tmpDir, { recursive: true });
  fs.mkdirSync(publicDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const results = [
    ...(await captureTheme(browser, 'light')),
    ...(await captureTheme(browser, 'dark'))
  ];
  await browser.close();

  convertPreviewJpeg(path.join(tmpDir, 'dashboard-light.png'), path.join(publicDir, 'FoxDesk_preview.jpg'));

  const failed = results.filter((result) => result.state.brokenImages.length > 0 || result.state.overflowX);
  console.log(JSON.stringify({ results, failed }, null, 2));
  if (failed.length > 0) {
    process.exit(1);
  }
})();
