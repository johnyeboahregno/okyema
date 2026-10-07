import { test, expect, Page } from '@playwright/test';

/**
 * Hold-to-talk journey (SIMPLE interface).
 *
 * Stubs the browser mic/audio APIs and the transformers.js import so the test
 * runs offline and deterministically: hold the orb, release, and the captured
 * audio is transcribed on-device and sent to the assistant.
 *
 * Run against the simple shell:
 *   OKYEMA_E2E_UI_MODE=simple npx playwright test tests/e2e/hold-to-talk.spec.ts
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
      body: 'export const pipeline = async () => async () => ({ text: "Ship the widget by Friday." });',
    });
  });
}

async function signInSimple(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'john@okyema.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  // Wait for Vue to actually mount (v-cloak is removed only after mount).
  await page.waitForSelector('#app:not([v-cloak])', { timeout: 15000 });
}

test('holding the orb records and releases to send', async ({ page }) => {
  await stubRecorderApis(page);
  await signInSimple(page);

  const orb = page.locator('.big-orb');
  const box = await orb.boundingBox();
  if (!box) throw new Error('Orb has no bounding box');

  const x = box.x + box.width / 2;
  const y = box.y + box.height / 2;

  await page.mouse.move(x, y);
  await page.mouse.down();

  // Hold past the 280ms threshold so recording starts.
  await page.waitForTimeout(700);
  await expect(page.locator('.big-orb')).toHaveClass(/is-recording/);

  await page.mouse.up();

  await expect(page.locator('.big-msg--assistant').first()).toBeVisible();
});
