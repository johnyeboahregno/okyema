import { test, expect, Page } from '@playwright/test';

/**
 * Ask journey (SIMPLE interface — one big button).
 *
 * Signs in to the simple shell, types a request and presses the big button,
 * then asserts an assistant answer comes back. Runs fully offline against a
 * seeded SQLite database with AI disabled, so the reply is the fallback.
 *
 * Run against the simple shell:
 *   OKYEMA_E2E_UI_MODE=simple npx playwright test tests/e2e/ask.spec.ts
 */

async function signInSimple(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'john@okyema.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page.locator('.big-app')).toBeVisible();
}

test('the big button sends a typed request and shows an answer', async ({ page }) => {
  await signInSimple(page);

  await page.fill('textarea.big-input', 'What do I need to do today?');
  await expect(page.locator('.big-button')).toBeEnabled();
  await page.locator('.big-button').click();

  await expect(page.locator('.big-msg--assistant').first()).toBeVisible();
});
