const { execFileSync } = require('child_process');

const baseURL = process.env.FOXDESK_LOCAL_URL || 'http://127.0.0.1:8090';
const adminEmail = process.env.FOXDESK_LOCAL_ADMIN_EMAIL || 'admin@example.test';
const adminPassword = process.env.FOXDESK_LOCAL_ADMIN_PASSWORD || 'AdminPass123!';

function run(command, args, options = {}) {
  return execFileSync(command, args, {
    encoding: 'utf8',
    stdio: options.stdio || ['ignore', 'pipe', 'pipe']
  });
}

function dockerCompose(args, options = {}) {
  return run('docker', ['compose', '-f', 'docker-compose.local.yml', ...args], options);
}

function updateCookieJar(cookieJar, response) {
  const setCookie = response.headers.getSetCookie ? response.headers.getSetCookie() : [];
  for (const cookie of setCookie) {
    const pair = cookie.split(';')[0];
    const eq = pair.indexOf('=');
    if (eq > 0) cookieJar[pair.slice(0, eq)] = pair.slice(eq + 1);
  }
}

function cookieHeader(cookieJar) {
  return Object.entries(cookieJar).map(([key, value]) => `${key}=${value}`).join('; ');
}

function extractCsrf(html) {
  const match = html.match(/name="csrf_token" value="([^"]+)"/);
  if (!match) throw new Error('Installer CSRF token not found.');
  return match[1];
}

async function waitForWeb() {
  const started = Date.now();
  while (Date.now() - started < 90_000) {
    try {
      const response = await fetch(`${baseURL}/install.php`, { redirect: 'manual' });
      if ([200, 302].includes(response.status)) return;
    } catch (_) {}
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  throw new Error(`FoxDesk local app did not become ready at ${baseURL}.`);
}

async function request(cookieJar, urlPath, options = {}) {
  const response = await fetch(`${baseURL}${urlPath}`, {
    redirect: 'manual',
    ...options,
    headers: {
      ...(options.headers || {}),
      Cookie: cookieHeader(cookieJar)
    }
  });
  updateCookieJar(cookieJar, response);
  return response;
}

async function install() {
  dockerCompose(['up', '-d', '--build'], { stdio: 'inherit' });
  await waitForWeb();

  const cookies = {};
  let response = await request(cookies, '/install.php');
  if (response.status === 302) {
    console.log(`FoxDesk already installed at ${baseURL}`);
    return;
  }

  let html = await response.text();
  let csrf = extractCsrf(html);

  response = await request(cookies, '/install.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      csrf_token: csrf,
      db_host: 'db',
      db_port: '3306',
      db_name: 'foxdesk_saas',
      db_user: 'foxdesk',
      db_pass: 'foxpass'
    })
  });
  if (response.status !== 302) {
    throw new Error(`Installer database step failed with HTTP ${response.status}: ${await response.text()}`);
  }

  response = await request(cookies, '/install.php?step=2');
  html = await response.text();
  csrf = extractCsrf(html);

  response = await request(cookies, '/install.php?step=2', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      csrf_token: csrf,
      app_name: 'FoxDesk SaaS Local',
      admin_email: adminEmail,
      admin_name: 'Admin',
      admin_surname: 'Local',
      admin_pass: adminPassword,
      admin_pass2: adminPassword
    })
  });
  if (response.status !== 302) {
    throw new Error(`Installer admin step failed with HTTP ${response.status}: ${await response.text()}`);
  }

  console.log(`FoxDesk installed at ${baseURL}`);
  console.log(`Admin: ${adminEmail} / ${adminPassword}`);
}

install().catch(error => {
  console.error(error);
  process.exit(1);
});

