const { test, expect, request } = require('@playwright/test');
const { baseURL } = require('./env');
const { dbQuery, php, getCsrf, login } = require('./helpers');

function ensureClientUser() {
  const hash = php("echo password_hash('ClientPass123!', PASSWORD_DEFAULT);").trim();
  const tenantId = Number(dbQuery("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1").trim().split('\n').pop());
  dbQuery(`
    INSERT INTO users (tenant_id, email, password, first_name, last_name, role, is_active, created_at)
    VALUES (${tenantId}, 'client.impersonation@example.test', '${hash.replaceAll("'", "''")}', 'Client', 'User', 'user', 1, NOW())
    ON DUPLICATE KEY UPDATE
      tenant_id = VALUES(tenant_id),
      password = VALUES(password),
      first_name = VALUES(first_name),
      last_name = VALUES(last_name),
      deleted_at = NULL,
      is_active = 1,
      role = 'user'
  `);
  const output = dbQuery("SELECT id FROM users WHERE email = 'client.impersonation@example.test' LIMIT 1");
  return Number(output.trim().split('\n').pop());
}

test('admin system page renders the simplified layout', async ({ page }) => {
  await login(page);
  await page.goto('/index.php?page=admin&section=settings&tab=system');
  await expect(page.locator('.admin-system')).toBeVisible();
  await expect(page.locator('.admin-page-nav')).toHaveCount(0);
  await expect(page.locator('.admin-tabs')).toHaveCount(0);
  await expect(page.locator('.settings-section-nav')).toBeVisible();
  await expect(page.locator('.settings-section-nav a.is-active, .settings-section-nav button.is-active')).toContainText('System');
  await expect(page.locator('body')).toContainText('Operations overview');
  await expect(page.locator('body')).toContainText('Backups');
  await expect(page.locator('body')).not.toContainText('System information');
});

test('customer admin navigation stays compact and stable across sections', async ({ page }) => {
  await login(page);
  await page.goto('/index.php?page=admin&section=users');

  await expect(page.locator('.admin-page-nav')).toHaveCount(0);
  await expect(page.locator('body')).toContainText('Users');
  await expect(page.locator('#main-content').getByRole('link', { name: 'Settings' })).toBeVisible();
  await expect(page.locator('body')).toContainText('Add user');

  await page.goto('/index.php?page=admin&section=settings');
  await expect(page.locator('.admin-page-nav')).toHaveCount(0);
  await expect(page.locator('.settings-management-grid')).toHaveCount(0);
  await expect(page.locator('.settings-section-nav')).toHaveCount(1);
  await expect(page.locator('.settings-section-nav')).toContainText('Workspace');
  await expect(page.locator('.settings-section-nav')).toContainText('Team & access');
  await expect(page.locator('.settings-section-nav')).toContainText('API & agents');
  await expect(page.locator('.settings-section-nav')).toContainText('Clients');
  await expect(page.locator('.settings-section-nav')).toContainText('Reports & rates');

  await page.goto('/index.php?page=admin&section=settings&tab=api');
  await expect(page.locator('[data-settings-api-access]')).toBeVisible();
  await expect(page.locator('[data-api-token-create-form]')).toBeVisible();
  await expect(page.locator('[data-api-tester]')).toBeVisible();
});

test('GET impersonation does not switch user, POST impersonation with CSRF does', async ({ page }) => {
  const clientId = ensureClientUser();
  await login(page);

  await page.goto(`/index.php?page=impersonate&user_id=${clientId}`);
  await expect(page.locator('body')).not.toContainText('Viewing as Client User');

  await page.goto('/index.php?page=dashboard');
  const csrf = await getCsrf(page);
  const response = await page.request.post('/index.php?page=impersonate', {
    form: {
      csrf_token: csrf,
      user_id: String(clientId)
    },
    maxRedirects: 0
  });
  expect([302, 303]).toContain(response.status());

  await page.goto('/index.php?page=dashboard');
  await expect(page.locator('body')).toContainText('Viewing as Client User');

  const stopCsrf = await getCsrf(page);
  await page.request.post('/index.php?page=impersonate', {
    form: {
      csrf_token: stopCsrf,
      stop: '1'
    },
    maxRedirects: 0
  });
  await page.goto('/index.php?page=dashboard');
  await expect(page.locator('body')).not.toContainText('Viewing as Client User');
});

test('update dismiss API requires auth and CSRF', async ({ page }) => {
  const anonymous = await request.newContext({ baseURL });
  const anonymousResponse = await anonymous.post('/index.php?page=api&action=dismiss-update-notice', {
    data: { version: 'e2e' }
  });
  expect([401, 403]).toContain(anonymousResponse.status());
  await anonymous.dispose();

  const missingCsrf = await page.request.post('/index.php?page=api&action=dismiss-update-notice', {
    data: { version: 'e2e' }
  });
  expect([400, 401, 403, 419]).toContain(missingCsrf.status());

  await login(page);
  await page.goto('/index.php?page=dashboard');
  const csrf = await getCsrf(page);
  const ok = await page.request.post('/index.php?page=api&action=dismiss-update-notice', {
    headers: {
      'X-CSRF-TOKEN': csrf,
      'Content-Type': 'application/json'
    },
    data: { version: 'e2e' }
  });
  expect(ok.status()).toBe(200);
  await expect(ok).toBeOK();
  expect(await ok.json()).toMatchObject({ success: true, dismissed: true });
});

test('push subscription API requires CSRF for subscribe and unsubscribe', async ({ page }) => {
  await login(page);
  await page.goto('/index.php?page=dashboard');

  const endpoint = `https://push.example.test/e2e/${Date.now()}`;
  const missingSubscribeCsrf = await page.request.post('/index.php?page=api&action=push-subscribe', {
    data: { endpoint, p256dh: 'p256dh-e2e', auth: 'auth-e2e' }
  });
  expect(missingSubscribeCsrf.status()).toBe(403);

  const csrf = await getCsrf(page);
  const subscribe = await page.request.post('/index.php?page=api&action=push-subscribe', {
    headers: {
      'X-CSRF-TOKEN': csrf,
      'Content-Type': 'application/json'
    },
    data: { endpoint, p256dh: 'p256dh-e2e', auth: 'auth-e2e' }
  });
  expect(subscribe.status()).toBe(200);
  expect(await subscribe.json()).toMatchObject({ success: true });

  const missingUnsubscribeCsrf = await page.request.post('/index.php?page=api&action=push-unsubscribe', {
    data: { endpoint }
  });
  expect(missingUnsubscribeCsrf.status()).toBe(403);
});

function migrationExtractionProbe(entries, symlinkName = null) {
  const payload = Buffer.from(JSON.stringify({ entries, symlinkName })).toString('base64');
  return JSON.parse(php(`
    require '/var/www/html/includes/migration-functions.php';
    $payload = json_decode(base64_decode('${payload}'), true);
    $zip_path = tempnam(sys_get_temp_dir(), 'foxdesk-migration-e2e-') . '.zip';
    $absolute_marker = '/tmp/foxdesk-migration-absolute-e2e.txt';
    $traversal_marker = '/tmp/foxdesk-migration-traversal-e2e.txt';
    @unlink($absolute_marker);
    @unlink($traversal_marker);

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create probe ZIP');
    }
    foreach ($payload['entries'] as $entry) {
        if (!empty($entry['dir'])) {
            $zip->addEmptyDir($entry['name']);
        } else {
            $zip->addFromString($entry['name'], $entry['body']);
        }
    }
    if (!empty($payload['symlinkName'])) {
        $zip->setExternalAttributesName($payload['symlinkName'], ZipArchive::OPSYS_UNIX, (0120777 << 16));
    }
    $zip->close();

    try {
        [$dir, $manifest, $hash] = migration_extract_package($zip_path);
        $result = [
            'ok' => true,
            'manifest_format' => $manifest['format'] ?? null,
            'absolute_marker' => file_exists($absolute_marker),
            'traversal_marker' => file_exists($traversal_marker),
        ];
        migration_remove_dir($dir);
    } catch (Throwable $e) {
        $result = [
            'ok' => false,
            'error' => $e->getMessage(),
            'absolute_marker' => file_exists($absolute_marker),
            'traversal_marker' => file_exists($traversal_marker),
        ];
    } finally {
        @unlink($zip_path);
        @unlink($absolute_marker);
        @unlink($traversal_marker);
    }

    echo json_encode($result);
  `));
}

test('migration ZIP import rejects absolute paths, traversal, and symlinks before extraction', async () => {
  const manifest = JSON.stringify({ format: 'foxdesk-cloud-migration' });

  expect(migrationExtractionProbe([
    { name: 'manifest.json', body: manifest },
    { name: '/tmp/foxdesk-migration-absolute-e2e.txt', body: 'absolute' }
  ])).toMatchObject({
    ok: false,
    absolute_marker: false
  });

  expect(migrationExtractionProbe([
    { name: 'manifest.json', body: manifest },
    { name: '../foxdesk-migration-traversal-e2e.txt', body: 'traversal' }
  ])).toMatchObject({
    ok: false,
    traversal_marker: false
  });

  const symlinkResult = migrationExtractionProbe([
    { name: 'manifest.json', body: manifest },
    { name: 'files/link', body: '/etc/passwd' }
  ], 'files/link');
  expect(symlinkResult.ok).toBe(false);
  expect(symlinkResult.error).toContain('symlink');

  expect(migrationExtractionProbe([
    { name: 'manifest.json', body: manifest },
    { name: 'tables/users.json', body: '[]' }
  ])).toMatchObject({
    ok: true,
    manifest_format: 'foxdesk-cloud-migration',
    absolute_marker: false,
    traversal_marker: false
  });
});

test('force installer cannot be accessed without a recovery token', async ({ page }) => {
  const response = await page.request.get('/install.php?force=1', { maxRedirects: 0 });
  expect([403, 404]).toContain(response.status());
});
