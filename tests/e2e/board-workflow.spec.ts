import { expect, test } from './fixtures';

test('quick-create a task, open it, and move it between columns by keyboard', async ({ page, loginAs }) => {
    await loginAs('admin@ewms.test');

    const boardName = `E2E Board ${Date.now()}`;
    const taskTitle = `E2E Task ${Date.now()}`;

    // Create a fresh company-wide board so column layout (DEFAULT_COLUMNS)
    // is predictable, rather than depending on a seeded department board's
    // workflow-template preset.
    await page.goto('/boards');
    await page.getByRole('button', { name: /new board/i }).click();
    await page.getByLabel('Name').fill(boardName);
    await page.getByRole('button', { name: /create board/i }).click();

    // BoardController::store() redirects straight to the new board's own
    // page — no need to navigate there separately.
    await expect(page).toHaveURL(/\/boards\/\d+/);
    await expect(page.getByRole('heading', { name: boardName })).toBeVisible();

    // Quick-add into the first column.
    await page
        .getByRole('button', { name: /add task/i })
        .first()
        .click();
    await page.getByPlaceholder('Task title').fill(taskTitle);
    await page.getByPlaceholder('Task title').press('Enter');

    // Not getByRole('button', { name: taskTitle }): the card's accessible
    // name concatenates its selection checkbox's aria-label too (an admin
    // can bulk-select), so a name-based query is ambiguous. The explicit
    // role="button" attribute is unique to the card itself — the drag
    // handle and checkbox are real <button>/<input> with only an implicit
    // role — so filtering on rendered text instead of accessible name sides
    // around the ambiguity entirely.
    const card = page.locator('[role="button"]').filter({ hasText: taskTitle });
    await expect(card).toBeVisible();

    // Open via keyboard (Enter on the focused card), not a click — this is
    // the actual accessibility fix (KeyboardSensor + split drag handle,
    // see task-card.tsx) exercised end-to-end rather than in isolation.
    await card.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('heading', { name: new RegExp(taskTitle) })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('heading', { name: new RegExp(taskTitle) })).not.toBeVisible();

    // Move it to the next column via dnd-kit's keyboard drag protocol: Tab
    // to the card's dedicated drag handle, Space to pick up, ArrowRight to
    // move to the adjacent column, Space to drop.
    // Same accessible-name ambiguity as above — scope to a real <button>
    // inside the card rather than matching by name.
    const handle = card.locator('button[aria-label^="Reorder"]');
    await handle.focus();
    await page.keyboard.press('Space');
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Space');

    // Persisted server-side, not just local state: reload and re-check.
    await page.reload();
    await expect(page.getByTestId('board-column').first()).toBeVisible();
    const columnTexts = await page.getByTestId('board-column').allTextContents();
    expect(columnTexts[0]).not.toContain(taskTitle);
    expect(columnTexts[1]).toContain(taskTitle);
});
