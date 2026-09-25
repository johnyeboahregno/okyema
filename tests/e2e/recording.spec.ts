import { test, expect, Page } from '@playwright/test';

/**
 * Recorder journey (SIMPLE interface, Whisper on-device).
 *
 * Stubs the browser mic/audio APIs and the transformers.js import so the test
 * runs offline and deterministically, then asserts the recording is
 * transcribed and saved.
 *
 * Run against the simple shell:
 *   OKYEMA_E2E_UI_MODE=simple npx playwright test tests/e2e/recording.spec.ts
 */

async function stubRecorderApis(page: Page) {
  await page.addInitScript(() => {
    const fakeStream = { getTracks: () => [] as any[] };
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia: async () => fakeStream },
    });

    const node = { connect: () => node, disconnect: () => {} };

    class FakeAudioContext {
      sampleRate = 16000;
      destination = {};
      createMediaStreamSource() { return node; }
      createScriptProcessor() {
        const proc: any = { ...node, onaudioprocess: null };
        setTimeout(() => {
          proc.onaudioprocess?.({ inputBuffer: { getChannelData: () => new Float32Array(16000) } });
        }, 0);
        return proc;
      }
      createGain() { return { gain: { value: 0 }, ...node }; }
      close() { return Promise.resolve(); }
    }

    (window as any).AudioContext = FakeAudioContext;
    (window as any).webkitAudioContext = FakeAudioContext;
  });

  // Fulfil the transformers.js dynamic import with a fake pipeline.
  await page.route('**/transformers*', async (route) => {
    await route.fulfill({
      contentType: 'application/javascript',
      body: 'export const pipeline = async () => async () => ({ text: "We decided to ship the widget." });',
    });
  });
}

async function signInSimple(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'john@okyema.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page.locator('.simple-app')).toBeVisible();
}

test('recording is transcribed and saved', async ({ page }) => {
  await stubRecorderApis(page);
  await signInSimple(page);

  await page.locator('.page-dot[aria-label="Note Taker"]').click();
  await page.locator('.recorder__btn').click(); // Start
  await expect(page.locator('.recorder__btn')).toContainText('Stop');
  await page.locator('.recorder__btn').click(); // Stop

  await expect(page.getByText('Saved — transcript filed as a meeting.')).toBeVisible();
});
