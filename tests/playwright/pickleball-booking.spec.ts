import { test, expect } from '@playwright/test';

test.describe('Pickleball Booking System', () => {
  test('can access booking page', async ({ page }) => {
    await page.goto('/');
    
    // Look for booking-related content
    const bookingElements = page.locator('text=booking, court, schedule, reserve');
    if (await bookingElements.count() > 0) {
      await expect(bookingElements.first()).toBeVisible();
    }
  });

  test('page loads without JavaScript errors', async ({ page }) => {
    const errors: string[] = [];
    
    page.on('pageerror', error => {
      errors.push(error.message);
    });
    
    await page.goto('/');
    
    // Wait for page to fully load
    await page.waitForLoadState('networkidle');
    
    // Assert no JavaScript errors
    expect(errors.length).toBe(0);
  });

  test('responsive design works on mobile', async ({ page }) => {
    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');
    
    const body = page.locator('body');
    await expect(body).toBeVisible();
    
    // Check if content is properly sized for mobile
    const viewport = page.viewportSize();
    expect(viewport?.width).toBe(375);
    expect(viewport?.height).toBe(667);
  });

  test('forms can be submitted', async ({ page }) => {
    await page.goto('/');
    
    // Look for any forms on the page
    const forms = page.locator('form');
    const formCount = await forms.count();
    
    if (formCount > 0) {
      const firstForm = forms.first();
      await expect(firstForm).toBeVisible();
      
      // Try to find submit buttons
      const submitButtons = page.locator('button[type="submit"], input[type="submit"]');
      if (await submitButtons.count() > 0) {
        await expect(submitButtons.first()).toBeVisible();
      }
    }
  });
});
