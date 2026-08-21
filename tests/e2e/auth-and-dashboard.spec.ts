import { expect, test } from './fixtures';

test('an unauthenticated visit to the dashboard redirects to login', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByRole('heading', { name: /log in|sign in/i })).toBeVisible();
});

test('a logged-in employee sees their dashboard', async ({ page, loginAs }) => {
    await loginAs('employee@ewms.test');
    await page.goto('/dashboard');

    await expect(page).toHaveURL(/\/dashboard/);
    // Drill-down: the dashboard links out to My Reports / boards / tickets —
    // following one confirms the page isn't just a static shell.
    await expect(page.getByRole('link', { name: /boards/i }).first()).toBeVisible();
});

test('mobile viewport still exposes primary navigation', async ({ page, loginAs }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await loginAs('employee@ewms.test');
    await page.goto('/dashboard');

    // Desktop nav collapses behind the sidebar trigger at this width —
    // opening it should still reach every primary destination.
    await page.getByRole('button', { name: /toggle sidebar/i }).click();
    await expect(page.getByRole('link', { name: /boards/i }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: /service desk|tickets/i }).first()).toBeVisible();
});
