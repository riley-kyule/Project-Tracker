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
    // name concatenates its drag handle's aria-label too (and, in select
    // mode, a selection checkbox's), so a name-based query is ambiguous.
    // The explicit role="button" attribute is unique to the card itself —
    // the drag handle/checkbox are real <button>/<input> with only an
    // implicit role — so filtering on rendered text instead of accessible
    // name sides around the ambiguity entirely.
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
    // dnd-kit's keyboard coordinate getter measures layout asynchronously
    // between steps, so firing Space/ArrowRight/Space back-to-back races it
    // under real browser timing. Its aria-live announcements turned out too
    // implementation-specific to assert on reliably here (single overwritten
    // string, and this app's own closestCorners collision resolution doesn't
    // map cleanly onto dnd-kit's default announcement text) — a short,
    // explicit pause between steps is the honest fix, not a clever wait.
    const handle = card.locator('button[aria-label^="Reorder"]');
    await handle.focus();
    await page.keyboard.press('Space');
    await page.waitForTimeout(200);
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(200);
    await page.keyboard.press('Space');

    // Persisted server-side, not just local state: reload and re-check.
    await page.reload();
    await expect(page.getByTestId('board-column').first()).toBeVisible();
    const columnTexts = await page.getByTestId('board-column').allTextContents();
    expect(columnTexts[0]).not.toContain(taskTitle);
    expect(columnTexts[1]).toContain(taskTitle);
});

test('the Select toggle reveals checkboxes on demand, and a bulk action applies to the selection', async ({ page, loginAs }) => {
    await loginAs('admin@ewms.test');

    const boardName = `E2E Bulk Board ${Date.now()}`;
    const taskTitle = `E2E Bulk Task ${Date.now()}`;

    await page.goto('/boards');
    await page.getByRole('button', { name: /new board/i }).click();
    await page.getByLabel('Name').fill(boardName);
    await page.getByRole('button', { name: /create board/i }).click();
    await expect(page).toHaveURL(/\/boards\/\d+/);

    await page
        .getByRole('button', { name: /add task/i })
        .first()
        .click();
    await page.getByPlaceholder('Task title').fill(taskTitle);
    await page.getByPlaceholder('Task title').press('Enter');
    const card = page.locator('[role="button"]').filter({ hasText: taskTitle });
    await expect(card).toBeVisible();

    // Checkboxes are hidden until "Select" is toggled on — the eyesore this
    // feature specifically replaced.
    await expect(card.getByRole('checkbox')).toHaveCount(0);

    await page.getByRole('button', { name: 'Select', exact: true }).click();
    await expect(card.getByRole('checkbox')).toBeVisible();

    await card.getByRole('checkbox').click();
    await expect(page.getByText('1 selected')).toBeVisible();

    await page.getByRole('button', { name: 'More actions' }).click();
    await page.getByRole('menuitem', { name: 'Duplicate' }).click();

    // Checked before any page.reload() below: a real browser reload always
    // resets client-side state (Select mode included) regardless of
    // anything the app does, so that's not a meaningful place to assert
    // this. The bulk action's own Inertia visit (preserveState: true) is
    // what's actually under test here — it clears the selection via
    // onDone() but leaves Select mode itself on, ready for another batch.
    await expect(page.getByText('1 selected')).not.toBeVisible();
    await expect(page.getByRole('checkbox').first()).toBeVisible();
    await page.getByRole('button', { name: 'Done selecting' }).click();
    await expect(page.getByRole('checkbox')).toHaveCount(0);

    // Server-side confirmation, not just an optimistic UI change.
    await page.reload();
    await expect(page.getByText(`${taskTitle} (copy)`)).toBeVisible();
});
