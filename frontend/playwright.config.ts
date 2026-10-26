import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests against the real stack: Laravel + PostgreSQL (seeded demo
 * data), the outbox relay and the realtime service, behind the built dashboard.
 * Run with `npm run e2e` (after `npm ci` in ../realtime).
 *
 * The API is started with PHP's built-in server and Laravel's router rather
 * than `artisan serve`, which does not pass DB_DATABASE through to the server.
 */
const database = process.env.E2E_DB_DATABASE ?? 'bookly_e2e';
const webhookSecret = 'e2e-webhook-secret';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: 'http://127.0.0.1:4173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Lets a machine with a pre-installed Chromium skip `playwright install`.
        ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE
          ? { launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE } }
          : {}),
      },
    },
  ],
  webServer: [
    {
      command:
        'php artisan migrate:fresh --seed --force && ' +
        // The outbox relay pushes events to the realtime service as they happen.
        '(php artisan outbox:relay --watch --sleep=100 &) && ' +
        // Laravel's router serves from the current directory, like `artisan serve` does.
        'cd public && php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
      cwd: '..',
      env: {
        DB_DATABASE: database,
        APP_ENV: 'local',
        PAYMENT_GATEWAY: 'fake',
        WEBHOOK_ENDPOINTS: 'http://127.0.0.1:3000/webhooks/bookly',
        WEBHOOK_SECRET: webhookSecret,
      },
      url: 'http://127.0.0.1:8000/up',
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: 'npm run build && npm start',
      cwd: '../realtime',
      env: { PORT: '3000', API_URL: 'http://127.0.0.1:8000', WEBHOOK_SECRET: webhookSecret },
      url: 'http://127.0.0.1:3000/health',
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: 'npm run build && npm run preview -- --strictPort --host 127.0.0.1',
      url: 'http://127.0.0.1:4173',
      reuseExistingServer: false,
      timeout: 120_000,
    },
  ],
});
