import { expect, readPhp, test } from './fixtures';

test.describe('add and edit strings', () => {
    test('adds a key to a vendor file', async ({ page }) => {
        await page.goto('addstring');
        const save = page.getByRole('button', { name: 'Save' });
        await expect(save).toBeDisabled();

        await page.locator('select[name="target"]').selectOption({ label: 'cashier · messages.php' });
        await page.locator('#key').fill('refunded');
        await page.locator('textarea[data-lang="en"]').fill('Refunded');
        await page.locator('textarea[data-lang="fr"]').fill('Remboursé');
        await save.click();

        await expect(page.getByText("Key 'cashier::messages.refunded' added to all translation files.")).toBeVisible();
        expect(readPhp('vendor/cashier/en/messages.php')).toMatchObject({ refunded: 'Refunded' });
        expect(readPhp('vendor/cashier/fr/messages.php')).toMatchObject({ refunded: 'Remboursé' });
    });

    test('requires a key and source text', async ({ page }) => {
        await page.goto('addstring');

        await page.locator('#key').fill('x');
        await page.locator('#key').fill('');
        await expect(page.getByText('Translation key is required.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Save' })).toBeDisabled();
    });

    test('translates all languages from the source via DeepL', async ({ page }) => {
        await page.goto('addstring');
        await page.locator('select[name="target"]').selectOption({ label: 'app · auth.php' });
        await page.locator('#key').fill('reset.link');
        await page.locator('textarea[data-lang="en"]').fill('Reset :name');

        await page.getByRole('button', { name: 'Translate' }).click();

        await expect(page.locator('textarea[data-lang="de"]')).toHaveValue('[DE] Reset :name');
        await expect(page.locator('textarea[data-lang="fr"]')).toHaveValue('[FR] Reset :name');
    });

    test('edits an existing key across languages', async ({ page }) => {
        await page.goto('editstrings?key=cashier%7Cmessages%7Cpaid');
        await expect(page.getByTitle('Vendor package: cashier')).toBeVisible();
        await expect(page.locator('textarea[data-lang="de"]')).toHaveValue('Zahlung erhalten.');

        await page.locator('textarea[data-lang="de"]').fill('Bezahlt.');
        await page.locator('textarea[data-lang="fr"]').fill('');
        await page.getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText("Key 'cashier::messages.paid' updated across all languages.")).toBeVisible();
        expect(readPhp('vendor/cashier/de/messages.php')).toEqual({ paid: 'Bezahlt.' });
        expect(readPhp('vendor/cashier/fr/messages.php')).toEqual([]);
    });
});
