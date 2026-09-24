import { expect, readPhp, row, test } from './fixtures';

test.describe('DeepL batch translation', () => {
    test('translates the selected missing keys and saves them', async ({ page }) => {
        await page.goto('de');
        await page.getByRole('button', { name: 'Filter missing' }).click();
        await page.getByRole('button', { name: 'Translate 6 keys' }).click();

        await expect(row(page, 'app|auth|throttle.minutes').locator('textarea')).toHaveValue('[DE] Try again in :minutes minutes.');
        await expect(row(page, 'cashier|messages|failed').locator('textarea')).toHaveValue('[DE] Payment failed.');

        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page.getByText('Translation saved.')).toBeVisible();
        expect(readPhp('vendor/cashier/de/messages.php')).toEqual({
            paid: 'Zahlung erhalten.',
            failed: '[DE] Payment failed.',
        });
    });

    test('shows the usage in the header popover', async ({ page }) => {
        await page.goto('de');
        await page.locator('header').getByRole('button', { name: /DeepL/ }).click();

        await expect(page.getByText('1,000 / 500,000 chars')).toBeVisible();
    });

    test('blocks translation when the selection exceeds the remaining budget', async ({ page, deeplUsage }) => {
        await deeplUsage({ character_count: 999, character_limit: 1000 });
        await page.goto('de');
        await page.getByRole('button', { name: 'Filter missing' }).click();

        await expect(page.getByRole('button', { name: 'Translate 6 keys' })).toBeDisabled();
        await page.locator('header').getByRole('button', { name: /DeepL/ }).click();
        await expect(page.getByText('over budget')).toBeVisible();
    });

    test('coverage selection opens the missing page with the languages preselected', async ({ page }) => {
        await page.goto('coverage');
        await expect(page.locator('tr', { hasText: 'German' })).toContainText('33%');

        await page.getByRole('button', { name: 'Select incomplete' }).click();
        await page.getByRole('button', { name: /Translate 2 languages/ }).click();

        await expect(page).toHaveURL(/missing\?langs=/);
        await expect(page.getByRole('button', { name: 'Translate 14 keys' })).toBeEnabled();
    });
});
