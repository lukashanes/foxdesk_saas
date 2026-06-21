const { test, expect } = require('@playwright/test');
const { publicBaseURL } = require('./env');

test('public Cloud page keeps marketing copy, pricing, and previews separated', async ({ page }) => {
  await page.goto(`${publicBaseURL}/index.php?page=cloud`);

  await expect(page.locator('h1')).toHaveText('Helpdesk & time tracking');
  await expect(page.locator('body')).toContainText('Track support tickets and billable hours');
  await expect(page.locator('#pricing')).toContainText('One plan. No per-seat math.');
  await expect(page.locator('#pricing')).toContainText('Launch price until May 31, 2026.');
  await expect(page.locator('#pricing')).toContainText('One workspace, one invoice, unlimited seats.');
  await expect(page.locator('#pricing')).toContainText('EUR 9.90');
  await expect(page.locator('#pricing')).toContainText('Unlimited users, agents, clients, organizations, and tickets');
  await expect(page.locator('#pricing')).not.toContainText('Stripe checkout ready');
  await expect(page.locator('#pricing img')).toHaveCount(0);
  await expect(page.locator('#preview')).toContainText('The daily workspace.');
  await expect(page.locator('#preview figure')).toHaveCount(2);
  await expect(page.locator('#preview img')).not.toHaveCount(0);

  const overflowX = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflowX).toBe(false);
});

test('public Cloud page does not overflow on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${publicBaseURL}/index.php?page=cloud`);

  await expect(page.locator('h1')).toHaveText('Helpdesk & time tracking');
  await expect(page.getByRole('link', { name: 'Try FoxDesk' }).first()).toBeVisible();

  const overflowX = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflowX).toBe(false);
});
