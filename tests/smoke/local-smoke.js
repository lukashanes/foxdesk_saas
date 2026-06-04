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

  await browser.close();
  console.log('Local smoke passed.');
})().catch(error => {
  console.error(error);
  process.exit(1);
});
