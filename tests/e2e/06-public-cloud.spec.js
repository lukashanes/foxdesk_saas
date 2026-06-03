const { test, expect } = require('@playwright/test');

test('public Cloud page keeps marketing copy, pricing, and previews separated', async ({ page }) => {
  await page.goto('/index.php?page=cloud');

  await expect(page.locator('h1')).toHaveText('Helpdesk & time tracking');
  await expect(page.locator('body')).toContainText('Track support tickets and billable hours');
  await expect(page.locator('#pricing')).toContainText('One plan for your support team.');
  await expect(page.locator('#pricing')).toContainText('50% launch price until May 31, 2026.');
  await expect(page.locator('#pricing')).toContainText('EUR 9.90');
  await expect(page.locator('#pricing')).toContainText('Unlimited users, agents, clients, organizations, and tickets');
  await expect(page.locator('#pricing')).not.toContainText('Stripe checkout ready');
  await expect(page.locator('#pricing img')).toHaveCount(0);
  await expect(page.locator('#preview img')).not.toHaveCount(0);

  const overflowX = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflowX).toBe(false);
});

test('public Cloud page does not overflow on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/index.php?page=cloud');

  await expect(page.locator('h1')).toHaveText('Helpdesk & time tracking');
  await expect(page.getByRole('link', { name: 'Try FoxDesk' }).first()).toBeVisible();

  const overflowX = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflowX).toBe(false);
});
