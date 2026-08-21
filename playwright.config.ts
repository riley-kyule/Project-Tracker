import { defineConfig, devices } from '@playwright/test';

const port = 8010;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1, // shared DB fixture data — parallel workers would race each other's mutations
    reporter: 'html',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'on-first-retry',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        // Fresh schema + seed data (Local Admin/Employee, sample boards —
        // see DatabaseSeeder) every run, against a throwaway database never
        // shared with real local dev data. See routes/e2e.php for why
        // ALLOW_E2E_LOGIN is needed at all.
        command: 'php artisan migrate:fresh --seed --force && php artisan serve --port=' + port,
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_ENV: 'local',
            APP_DEBUG: 'true',
            ALLOW_E2E_LOGIN: 'true',
            DB_DATABASE: 'ewms_e2e',
            SESSION_DRIVER: 'database',
            MAIL_MAILER: 'log',
            QUEUE_CONNECTION: 'sync',
            CACHE_STORE: 'array',
        },
    },
});
