import { type Page } from '@playwright/test';
import { expect, test } from './fixtures';

/** Pretend the signed-in user has already seen these tours. */
async function markSeen(page: Page, ids: string[]) {
    await page.evaluate((seen) => {
        const data = JSON.parse(document.getElementById('app')?.dataset.page ?? '{}');
        window.localStorage.setItem(`ewms-tours:${data.props.auth.user.id}`, JSON.stringify(seen));
    }, ids);
}

// Every other spec runs with tour autoplay off; these are about autoplay.
test.use({ tourAutoplay: true });

test('a first visit plays the welcome tour once, then the page tour on the next visit', async ({ page, loginAs }) => {
    await loginAs('employee@ewms.test');
    await page.goto('/dashboard');

    const tour = page.getByTestId('tour-overlay');
    await expect(tour).toBeVisible();
    await expect(tour.getByRole('heading', { name: 'Welcome to EWMS' })).toBeVisible();

    // Step through to the end; admin-only steps are skipped for an employee.
    for (let i = 0; i < 15 && (await tour.getByRole('button', { name: 'Next' }).isVisible()); i++) {
        await tour.getByRole('button', { name: 'Next' }).click();
    }
    await expect(tour.getByRole('heading', { name: 'Help is always here' })).toBeVisible();
    await expect(tour).not.toContainText('Admin tools');
    await tour.getByRole('button', { name: 'Done' }).click();
    await expect(tour).not.toBeVisible();

    // Finishing one tour never chains into the next on the same page view.
    await page.waitForTimeout(1200);
    await expect(tour).not.toBeVisible();

    await page.reload();
    await expect(tour).toBeVisible();
    await expect(tour).toContainText('Your dashboard');
    await tour.getByRole('button', { name: 'Skip tour' }).click();

    await page.reload();
    await page.waitForTimeout(1200);
    await expect(tour).not.toBeVisible();
});

test('the help menu replays the current page tour and the Help Center starts others', async ({ page, loginAs }) => {
    await loginAs('employee@ewms.test');
    // Mark the welcome tour as seen so only what the test starts appears.
    await page.goto('/help');
    await markSeen(page, ['welcome']);
    await page.reload();

    await page.getByRole('heading', { name: 'Help Center' }).waitFor();
    await expect(page.getByRole('heading', { name: 'Boards', exact: true })).toBeVisible();
    // Admin guides aren't listed for an employee.
    await expect(page.getByRole('heading', { name: 'Users and roles' })).toHaveCount(0);

    await page
        .getByRole('listitem')
        .filter({ has: page.getByRole('heading', { name: 'Boards', exact: true }) })
        .getByRole('button', { name: 'Start tour' })
        .click();
    await expect(page).toHaveURL(/\/boards$/);
    const tour = page.getByTestId('tour-overlay');
    await expect(tour).toContainText('Your boards');
    await page.keyboard.press('Escape');
    await expect(tour).not.toBeVisible();

    await page.getByRole('button', { name: 'Help and guided tours' }).click();
    await page.getByRole('menuitem', { name: 'Boards' }).click();
    await expect(tour).toContainText('Your boards');
});

test('a tour inside the task dialog can be stepped through without closing the dialog', async ({ page, loginAs }) => {
    await loginAs('admin@ewms.test');
    await page.goto('/boards');
    await markSeen(page, ['welcome', 'boards', 'board', 'board-manage']);
    await page.reload();

    const boardName = `Tour Board ${Date.now()}`;
    await page.getByRole('button', { name: /new board/i }).click();
    await page.getByLabel('Name').fill(boardName);
    await page.getByRole('button', { name: /create board/i }).click();
    await expect(page).toHaveURL(/\/boards\/\d+/);
    await page
        .getByRole('button', { name: /add task/i })
        .first()
        .click();
    await page.getByPlaceholder('Task title').fill('Tour task');
    await page.getByPlaceholder('Task title').press('Enter');
    await page.locator('[role="button"]').filter({ hasText: 'Tour task' }).click();

    const tour = page.getByTestId('tour-overlay');
    await expect(tour).toContainText('It saves by itself');
    await tour.getByRole('button', { name: 'Next' }).click();
    await expect(tour).toContainText('Who, how urgent, when');
    await expect(page.getByRole('dialog').filter({ hasText: 'Tour task' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(tour).not.toBeVisible();
    // Escape closed the tour, not the task underneath it.
    await expect(page.getByRole('dialog').filter({ hasText: 'Tour task' })).toBeVisible();
});
