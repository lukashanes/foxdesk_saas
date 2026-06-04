const assert = require('assert');
const { chromium } = require('@playwright/test');

const baseUrl = (process.env.PROD_BASE_URL || 'https://app.foxdesk.net').replace(/\/$/, '');
const publicUrl = (process.env.PROD_PUBLIC_URL || 'https://foxdesk.net').replace(/\/$/, '');

async function fetchText(url) {
  const response = await fetch(url, {
    redirect: 'follow',
    headers: {
      'User-Agent': 'FoxDesk production smoke test'
    }
  });
  const text = await response.text();
  return { response, text };
}

async function assertOk(url, expectedText) {
  const { response, text } = await fetchText(url);
  assert(response.ok, `${url} returned HTTP ${response.status}`);
  if (expectedText) {
    assert(text.includes(expectedText), `${url} did not include ${expectedText}`);
  }
  return text;
}

async function assertLoginLayout() {
  const browser = await chromium.launch();
  const page = await browser.newPage({
    viewport: { width: 1280, height: 720 }
  });

  try {
    await page.goto(`${baseUrl}/index.php?page=login`, { waitUntil: 'networkidle' });
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

    assert.strictEqual(layout.bodyDisplay, 'flex', `Login body layout is broken: ${JSON.stringify(layout)}`);
    assert.strictEqual(layout.leftDisplay, 'flex', `Desktop login brand panel is hidden or unstyled: ${JSON.stringify(layout)}`);
    assert.strictEqual(layout.rightDisplay, 'flex', `Login form panel is not centered: ${JSON.stringify(layout)}`);
    assert(layout.formRect && layout.formRect.x > layout.viewportWidth * 0.55, `Login form is not in the right panel: ${JSON.stringify(layout)}`);
    assert(layout.emailRect && layout.emailRect.width >= 300 && layout.emailRect.height >= 40, `Login email field is not usable: ${JSON.stringify(layout)}`);
  } finally {
    await browser.close();
  }
}

(async () => {
  const health = await assertOk(`${baseUrl}/index.php?page=health`, '"status":"ok"');
  assert(health.includes('"db":true'), 'Health endpoint did not confirm database connectivity');

  await assertLoginLayout();
  await assertOk(`${baseUrl}/index.php?page=signup`, 'Create workspace');
  await assertOk(`${publicUrl}/index.php?page=cloud`, 'FoxDesk Cloud');
  await assertOk(`${publicUrl}/index.php?page=legal&type=privacy`, 'Privacy Policy');
  await assertOk(`${publicUrl}/index.php?page=legal&type=terms`, 'Terms of Service');
  await assertOk(`${publicUrl}/index.php?page=legal&type=refunds`, 'Refund and Cancellation Policy');
  await assertOk(`${publicUrl}/index.php?page=legal&type=security`, 'Security');

  console.log(`Production smoke OK: ${baseUrl} / ${publicUrl}`);
})().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
