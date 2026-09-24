import { defineConfig, devices } from '@playwright/test';

const PORT = 8787;

export default defineConfig({
    testDir: './tests/Browser',
    // All tests share one lang directory on disk, so they must run one after another.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${PORT}/admin/translations/`,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        command: `vendor/bin/testbench serve --port=${PORT}`,
        url: `http://127.0.0.1:${PORT}/admin/translations`,
        env: { XDEBUG_MODE: 'off' },
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});
