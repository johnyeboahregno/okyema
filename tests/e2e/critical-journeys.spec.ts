import { test, expect, Page } from '@playwright/test';

/**
 * Okyema critical journeys, runnable fully offline against the demo user
 * (john@okyema.test / password) that DemoSeeder creates.
 *
 * Provider-dependent journeys (real OAuth connect, real Drive upload, real
 * mail send) are marked as skipped here and are exercised only once real
 * credentials are available.
 */

async function signIn(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'john@okyema.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page.locator('.appbar')).toBeVisible();
}

async function navButton(page: Page, label: string) {
  // The suite runs at a phone viewport (480×900), where primary navigation
  // lives behind the hamburger drawer.
  await page.click('.hamburger');
  await page.locator('.drawer__item', { hasText: label }).click();
}

test('sign in and select a context', async ({ page }) => {
  await signIn(page);

  // Today shows the active context (REGNO is the default).
  await expect(page.locator('.context-chip')).toContainText('Regno');

  // Switch to Launchpad.
  await page.click('.context-chip');
  await page.locator('.user-menu__item', { hasText: 'Launchpad' }).click();
  await expect(page.locator('.context-chip')).toContainText('Launchpad');
});

test('view today and the timeline', async ({ page }) => {
  await signIn(page);

  await expect(page.locator('.hero')).toBeVisible();
  await navButton(page, 'Timeline');
  await expect(page.locator('.greet')).toContainText('Timeline');
});

test('open a meeting, generate a sourced brief and convert a decision', async ({ page }) => {
  await signIn(page);

  await navButton(page, 'Meetings');
  await page.locator('.list__item', { hasText: 'Regno product kickoff' }).click();

  await expect(page.locator('.meeting-detail')).toBeVisible();
  await page.click('button', { hasText: 'Generate summary' });

  await expect(page.locator('.meeting-detail')).toContainText('Summary');
  await expect(page.locator('.meeting-detail')).toContainText('Launch the mobile app');

  await page.locator('button', { hasText: 'Convert to action' }).first().click();
});

test('drafting a reply requires approval and never sends immediately', async ({ page }) => {
  await signIn(page);

  await navButton(page, 'Inbox');
  await page.locator('.list__item').first().click();

  await page.fill('textarea', 'Here are the numbers.');
  await page.click('button', { hasText: 'Draft reply' });

  await expect(page.locator('.meeting-detail')).toContainText('pending your approval');
});

test('capture a receipt and confirm it into an expense', async ({ page }) => {
  await signIn(page);

  await navButton(page, 'Expenses');

  // Upload a fake receipt through the hidden file input.
  await page.setInputFiles('input[type="file"]', {
    name: 'receipt.jpg',
    mimeType: 'image/jpeg',
    buffer: Buffer.from('fake-jpeg-bytes'),
  });

  await expect(page.locator('.card', { hasText: 'Receipt #' })).toBeVisible();
  await page.locator('button', { hasText: 'Confirm' }).click();

  await page.fill('input[placeholder=""]', 'Pret a Manger');
  await page.locator('input[type="date"]').fill('2026-09-22');
  await page.locator('form').locator('button', { hasText: 'Save expense' }).click();
});

test('workspace isolation — a Regno meeting never appears in Launchpad', async ({ page }) => {
  await signIn(page);

  await navButton(page, 'Meetings');
  await expect(page.locator('.list__item', { hasText: 'Regno product kickoff' })).toBeVisible();

  await page.click('.context-chip');
  await page.locator('.user-menu__item', { hasText: 'Launchpad' }).click();

  await expect(page.locator('.list__item', { hasText: 'Regno product kickoff' })).toHaveCount(0);
});

test.skip('connect a Google account with minimum scopes (needs real OAuth credentials)', async () => {
  // Requires a Google Cloud OAuth client + test user. Not runnable offline.
});

test.skip('disconnect a connector and verify revocation (needs a connected account)', async () => {
  // Requires a live connector account. Not runnable offline.
});
