import { expect, row, test } from './fixtures';

test.describe('header key search', () => {
    test('suggests labels with their origin and opens the key editor', async ({ page }) => {
        await page.goto('');
        const search = page.getByPlaceholder('Search key…');

        await search.fill('messages');
        const suggestions = page.locator('header ul li');
        await expect(suggestions).toHaveCount(2);
        await expect(suggestions.first()).toContainText('cashier::messages.paid');
        await expect(suggestions.first()).toContainText('cashier');

        await search.fill('throttle.min');
        await expect(suggestions).toHaveCount(1);
        await search.press('ArrowDown');
        await search.press('Enter');

        await expect(page).toHaveURL(/editstrings\?key=app%7Cauth%7Cthrottle\.minutes$/);
        await expect(page.getByRole('heading', { name: 'Edit: auth.throttle.minutes' })).toBeVisible();
    });

    test('jumps to and highlights the row inside the language editor', async ({ page }) => {
        await page.goto('de');
        await page.getByPlaceholder('Filter keys or values…').fill('Welcome');

        await page.getByPlaceholder('Search key…').fill('cashier::messages.failed');
        await page.locator('header ul li').first().click();

        await expect(page).toHaveURL(/\/de$/);
        await expect(page.getByPlaceholder('Filter keys or values…')).toHaveValue('');
        await expect(row(page, 'cashier|messages|failed')).toBeInViewport();
        await expect(row(page, 'cashier|messages|failed')).toHaveClass(/animate-key-flash/);
    });
});
