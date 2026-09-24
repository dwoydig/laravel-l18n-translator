import { expect, readJson, readPhp, row, test } from './fixtures';

test.describe('language editor', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('de');
    });

    test('shows the origin of every key', async ({ page }) => {
        await expect(page.getByRole('columnheader', { name: 'Origin' }).first()).toBeVisible();
        await expect(row(page, 'cashier|messages|failed').getByTitle('Vendor package: cashier')).toHaveText('cashier');
        await expect(row(page, 'app|auth|throttle.minutes').getByTitle('Application translation')).toHaveText('app');
        await expect(row(page, 'cashier|messages|failed').getByRole('link')).toHaveText('cashier::messages.failed');
    });

    test('filters rows by key label and origin', async ({ page }) => {
        await page.getByPlaceholder('Filter keys or values…').fill('cashier');

        await expect(row(page, 'cashier|messages|paid')).toBeVisible();
        await expect(row(page, 'cashier|messages|failed')).toBeVisible();
        await expect(row(page, 'app|*|Welcome back.')).toBeHidden();

        await page.getByPlaceholder('Filter keys or values…').fill('throttle.min');
        await expect(row(page, 'app|auth|throttle.minutes')).toBeVisible();
        await expect(row(page, 'cashier|messages|paid')).toBeHidden();
    });

    test('"Filter missing" selects only untranslated rows', async ({ page }) => {
        await page.getByRole('button', { name: 'Filter missing' }).click();

        await expect(page.locator('tbody[x-ref="tbody"] tr:visible')).toHaveCount(6);
        await expect(row(page, 'app|*|Welcome back.')).toBeHidden();
        await expect(page.getByRole('button', { name: 'Translate 6 keys' })).toBeEnabled();

        await page.getByRole('button', { name: 'Show all' }).click();
        await expect(row(page, 'app|*|Welcome back.')).toBeVisible();
    });

    test('saves group and vendor translations and removes cleared ones', async ({ page }) => {
        await row(page, 'cashier|messages|failed').locator('textarea').fill('Zahlung fehlgeschlagen.');
        await row(page, 'app|auth|throttle.seconds').locator('textarea').fill('Warte :seconds Sekunden.');
        await row(page, 'cashier|messages|paid').locator('textarea').fill('');
        await page.getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText('Translation saved.')).toBeVisible();
        expect(readPhp('vendor/cashier/de/messages.php')).toEqual({ failed: 'Zahlung fehlgeschlagen.' });
        expect(readPhp('de/auth.php')).toEqual({
            failed: 'Diese Zugangsdaten sind ungültig.',
            throttle: { seconds: 'Warte :seconds Sekunden.' },
        });
        await expect(row(page, 'cashier|messages|failed').locator('textarea')).toHaveValue('Zahlung fehlgeschlagen.');
    });

    test('key link opens the edit-string screen', async ({ page }) => {
        await row(page, 'cashier|messages|failed').getByRole('link').click();

        await expect(page).toHaveURL(/editstrings\?key=cashier%7Cmessages%7Cfailed$/);
        await expect(page.getByRole('heading', { name: 'Edit: cashier::messages.failed' })).toBeVisible();
    });

    test('removes selected orphaned keys', async ({ page }) => {
        const orphans = page.locator('form', { hasText: 'orphaned key' });
        await expect(orphans.getByRole('button', { name: 'Remove selected' })).toBeDisabled();

        await orphans.locator('tbody tr', { hasText: 'Orphan' }).click();
        await orphans.getByRole('button', { name: 'Remove selected' }).click();

        await expect(page.getByText('1 orphaned key(s) removed from de.')).toBeVisible();
        expect(readJson('de.json')).toEqual({ 'Welcome back.': 'Willkommen zurück.' });
    });
});
