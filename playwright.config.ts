import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

/**
 * Fully-offline browser E2E configuration for Okyema.
 *
 * A self-contained local stack — no MySQL, no internet:
 *   - a dedicated SQLite file (OKYEMA_E2E_DB) so runs never touch the
 *     dev database
 *   - the PHP built-in server started by Playwright itself
 *   - the schema is migrated and demo-seeded by the `webServer` command,
 *     so `npx playwright test` is the only command needed
 */

const PORT = 8124;
const BASE_URL = `http://127.0.0.1:${PORT}`;

const E2E_DB =
  process.env.OKYEMA_E2E_DB ??
  path.resolve(__dirname, 'database', 'okyema-e2e.sqlite').replace(/\\/g, '/');

export default defineConfig({
  testDir: './tests/e2e',

  fullyParallel: false,
  workers: 1,

  forbidOnly: !!process.env.CI,
  retries: 0,

  reporter: [['list']],

  use: {
    baseURL: BASE_URL,
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 480, height: 900 },
      },
    },
  ],

  webServer: {
    command: [
      'php artisan config:clear',
      `php -r "file_exists(getenv('DB_DATABASE')) || touch(getenv('DB_DATABASE'));"`,
      'php artisan migrate:fresh --force',
      'php artisan db:seed --class=DemoSeeder --force',
      `php artisan serve --host=127.0.0.1 --port=${PORT}`,
    ].join(' && '),

    url: `${BASE_URL}/login`,
    timeout: 120_000,
    reuseExistingServer: false,

    env: {
      APP_ENV: 'testing',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: E2E_DB,
      SESSION_DRIVER: 'file',
      AI_ENABLED: 'false',
      OKYEMA_UI_MODE: process.env.OKYEMA_E2E_UI_MODE ?? 'classic',
      SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${PORT},localhost:${PORT}`,
    },
  },
});
