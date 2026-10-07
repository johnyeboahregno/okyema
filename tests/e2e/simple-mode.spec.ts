import { test, expect, Page } from '@playwright/test';

/**
 * Simple interface mode journeys — one big button.
 *
 * These only run against a server started with OKYEMA_E2E_UI_MODE=simple (the
 * mode is a deployment-level environment variable, not a query parameter):
 *
 *   OKYEMA_E2E_UI_MODE=simple npx playwright test tests/e2e/simple-mode.spec.ts
 */

async function signInSimple(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'john@okyema.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  // Wait for Vue to actually mount (v-cloak is removed only after mount).
  await page.waitForSelector('#app:not([v-cloak])', { timeout: 15000 });
}

test('simple mode — the shell renders the big button', async ({ page }) => {
  await signInSimple(page);

  await expect(page.locator('.big-orb')).toBeVisible();
  await expect(page.getByText('Ready!')).toBeVisible();
});

test('simple mode — attaching a document enables the button', async ({ page }) => {
  await signInSimple(page);

  await page.setInputFiles('input[type="file"]', {
    name: 'notes.txt',
    mimeType: 'text/plain',
    buffer: Buffer.from('Ship the widget by Friday.'),
  });

  await expect(page.locator('.big-chip', { hasText: 'notes.txt' })).toBeVisible();
});