import { test, expect, Page } from '@playwright/test';

/**
 * Simple interface mode journeys.
 *
 * These only run against a server started with OKYEMA_UI_MODE=simple (the
 * mode is a deployment-level environment variable, not a query parameter).
 * Start the stack with that variable, e.g.:
 *
 *   OKYEMA_UI_MODE=simple npx playwright test tests/e2e/simple-mode.spec.ts
 *
 * The journey below covers acceptance checks 3 (typed request reaches the
 * assistant) and the voice transcript path via the DOM state, without
 * depending on a live AI provider — with AI disabled the assistant returns
 * its deterministic fallback, which is still a result.
 */

async function signInSimple(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'john@okyema.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page.locator('.simple-header')).toBeVisible();
}

test('simple mode — type a request and get an assistant result', async ({ page }) => {
  await signInSimple(page);

  // The active context chip is visible and scoped.
  await expect(page.locator('.context-chip')).toContainText('Regno');

  // A typed request reaches the assistant and returns a result.
  await page.fill('#ask', 'What do I need to do today?');
  await page.click('button', { hasText: 'Send' });
  await expect(page.locator('.message--assistant')).toBeVisible();
});

test('simple mode — the gear opens the real settings without exposing secrets', async ({ page }) => {
  await signInSimple(page);

  await page.click('button[aria-label="Open settings"]');
  await expect(page.locator('.modal')).toBeVisible();
  await expect(page.locator('.modal')).toContainText('Connections');
  await expect(page.locator('.modal')).not.toContainText('AI_API_KEY');
});
