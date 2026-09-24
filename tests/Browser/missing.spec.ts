import { expect, readPhp, row, test } from './fixtures';

test.describe('missing translations page', () => {
    test('lists missing keys with their origin and saves filled values', async ({ page }) => {
        await page.goto('missing');
        await expect(page.getByRole('heading', { name: '14 missing translations across 2 languages' })).toBeVisible();

        const vendorRow = row(page, 'fr::cashier|messages|failed');
        await expect(vendorRow.getByTitle('Vendor package: cashier')).toBeVisible();
        await expect(vendorRow.getByRole('link')).toHaveText('cashier::messages.failed');

        await page.getByPlaceholder('Filter keys, values or languages…').fill('cashier');
        await expect(page.locator('tbody[x-ref="tbody"] tr:visible')).toHaveCount(2);

        await vendorRow.locator('textarea').fill('Paiement échoué.');
        await page.getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText('1 translation(s) saved.')).toBeVisible();
        expect(readPhp('vendor/cashier/fr/messages.php')).toEqual({ paid: 'Paiement reçu.', failed: 'Paiement échoué.' });
    });
});
