const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const { baseURL } = require('./env');

test('public signup creates an isolated FoxDesk workspace and platform admin can manage it', async ({ browser }) => {
  const stamp = Date.now();
  const workspaceName = `E2E SaaS Workspace ${stamp}`;
  const ownerEmail = `owner.${stamp}@example.test`;
  const ownerPassword = 'OwnerPass123!';

  const signupContext = await browser.newContext({ baseURL });
  const signupPage = await signupContext.newPage();
  await signupPage.goto('/index.php?page=signup');
  await expect(signupPage.locator('body')).toContainText('Create workspace');
  await signupPage.locator('input[name="workspace_name"]').fill(workspaceName);
  await signupPage.locator('input[name="admin_first_name"]').fill('Owner');
  await signupPage.locator('input[name="admin_last_name"]').fill('SaaS');
  await signupPage.locator('input[name="admin_email"]').fill(ownerEmail);
  await signupPage.locator('input[name="password"]').fill(ownerPassword);
  await signupPage.locator('input[name="password_confirm"]').fill(ownerPassword);
  await signupPage.locator('button[type="submit"]').click();
  await signupPage.waitForURL(/page=dashboard|dashboard/);
  await expect(signupPage.locator('body')).toContainText('Dashboard');

  await signupPage.goto('/index.php?page=platform');
  await expect(signupPage).toHaveURL(/page=dashboard|dashboard/);
  await signupContext.close();

  const platformContext = await browser.newContext({ baseURL });
  const platformPage = await platformContext.newPage();
  await login(platformPage);
  await platformPage.goto('/index.php?page=platform');
  await expect(platformPage.locator('body')).toContainText('Platform');
  await expect(platformPage.locator('body')).toContainText(workspaceName);
  await expect(platformPage.locator('body')).toContainText(ownerEmail);
  await platformContext.close();
});
