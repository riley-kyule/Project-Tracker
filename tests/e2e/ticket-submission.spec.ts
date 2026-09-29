import { expect, test } from './fixtures';

test('an employee can submit a support ticket and see it in the queue', async ({ page, loginAs }) => {
    await loginAs('employee@ewms.test');

    const ticketTitle = `E2E laptop issue ${Date.now()}`;

    await page.goto('/tickets');
    await page.getByRole('button', { name: /new ticket/i }).click();

    await page.getByText('What is this about?').click();
    await page.getByRole('option', { name: 'Other' }).click();

    // exact: the tickets table's "Sort by Title" header also matches a loose label query.
    await page.getByLabel('Title', { exact: true }).fill(ticketTitle);
    await page.getByLabel('What happened?').fill('Screen flickers on startup — filed via the Playwright e2e suite.');
    await page.getByRole('button', { name: /submit ticket/i }).click();

    await expect(page.getByText(ticketTitle)).toBeVisible();
});
