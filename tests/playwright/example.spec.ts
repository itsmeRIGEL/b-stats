import { test, expect } from '@playwright/test';

test('homepage loads correctly', async ({ page }) => {
  await page.goto('/');
  
  // Check if the page loads without errors
  await expect(page).toHaveTitle(/Pickleball|Laravel/);
  
  // Check for common elements
  const body = page.locator('body');
  await expect(body).toBeVisible();
});

test('navigation works', async ({ page }) => {
  await page.goto('/');
  
  // Look for navigation elements
  const nav = page.locator('nav, .navigation, header');
  if (await nav.count() > 0) {
    await expect(nav.first()).toBeVisible();
  }
});

test('page has no console errors', async ({ page }) => {
  const errors: string[] = [];
  
  page.on('console', msg => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });
  
  await page.goto('/');
  
  // Wait a bit for any async errors to appear
  await page.waitForTimeout(2000);
  
  // Assert no console errors
  expect(errors.length).toBe(0);
});
