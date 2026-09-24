import { test as base, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { cpSync, readFileSync, rmSync } from 'node:fs';
import path from 'node:path';

const FIXTURES_PATH = path.join(__dirname, '..', 'Fixtures', 'lang');
// Must match BrowserTestServiceProvider::LANG_PATH.
const LANG_PATH = path.join(__dirname, '.lang');

export type DeeplUsage = { character_count: number; character_limit: number };

type Fixtures = {
    /** Overrides the usage the mocked DeepL endpoint reports; call before navigating. */
    deeplUsage: (usage: DeeplUsage) => Promise<void>;
};

/** Restores tests/Fixtures/lang into the lang directory served by testbench. */
export function resetLang(): void {
    rmSync(LANG_PATH, { recursive: true, force: true });
    cpSync(FIXTURES_PATH, LANG_PATH, { recursive: true });
}

export function readJson(relativePath: string): Record<string, string> {
    return JSON.parse(readFileSync(path.join(LANG_PATH, relativePath), 'utf8'));
}

/** Evaluates a PHP lang file with the PHP CLI and returns its array. */
export function readPhp(relativePath: string): Record<string, unknown> {
    const output = execFileSync('php', ['-r', 'echo json_encode(require $argv[1]);', path.join(LANG_PATH, relativePath)], {
        env: { ...process.env, XDEBUG_MODE: 'off' },
    });
    return JSON.parse(output.toString());
}

/**
 * Mocks the package's own DeepL proxy endpoints: translations are returned as "[TARGET] source text".
 */
async function mockDeepl(page: Page, usage: DeeplUsage): Promise<void> {
    await page.route('**/admin/translations/deepl/usage', route => route.fulfill({ json: usage }));
    await page.route('**/admin/translations/deepl', async route => {
        const { text, target_lang } = route.request().postDataJSON();
        await route.fulfill({ json: { text: `[${target_lang}] ${text}` } });
    });
}

export const test = base.extend<Fixtures>({
    deeplUsage: async ({ page }, use) => {
        await use(async usage => {
            await page.unroute('**/admin/translations/deepl/usage');
            await page.route('**/admin/translations/deepl/usage', route => route.fulfill({ json: usage }));
        });
    },
    page: async ({ page }, use) => {
        resetLang();
        await mockDeepl(page, { character_count: 1000, character_limit: 500000 });
        await use(page);
    },
});

/** Returns the editor/missing-page row of a translation id. */
export function row(page: Page, id: string) {
    return page.locator(`tbody tr[data-key="${id}"]`);
}

export { expect };
