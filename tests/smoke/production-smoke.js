const assert = require('assert');

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

(async () => {
  const health = await assertOk(`${baseUrl}/index.php?page=health`, '"status":"ok"');
  assert(health.includes('"db":true'), 'Health endpoint did not confirm database connectivity');

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
