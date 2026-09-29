import { type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { expect, test } from './fixtures';

/*
 * Opens every page as several roles and plays each page's guided tour to the
 * end. Fails on any uncaught script error, any server error, or a tour that
 * can't be stepped through. Record pages (a ticket, a project…) are reached
 * by following the first link on their list page, seeded below.
 */

test.use({ tourAutoplay: true });
test.describe.configure({ mode: 'serial' });

const LIST_PAGES = [
    '/dashboard',
    '/boards',
    '/tickets',
    '/projects',
    '/search?q=te',
    '/help',
    '/dashboards/ceo',
    '/dashboards/department',
    '/seo-board/hod',
    '/cs-board/hod',
    '/dashboards/it',
    '/reports/tasks',
    '/reports/workload',
    '/reports/remote-support',
    '/marketing-statistics',
    '/marketing-statistics/ga4',
    '/marketing-statistics/gsc',
    '/marketing-statistics/comparison',
    '/marketing-statistics/freshness',
    '/my-reports',
    '/hr/employees',
    '/hr/assets',
    '/hr/leave',
    '/hr/leave/balances',
    '/hr/leave/types',
    '/hr/leave/holidays',
    '/hr/leave/settings',
    '/hr/payroll',
    '/hr/payroll/settings',
    '/hr/payroll/rate-sets',
    '/hr/performance',
    '/hr/me/profile',
    '/hr/me/leave',
    '/hr/me/payslips',
    '/seo-board/settings',
    '/cs-board/settings',
    '/admin/sla-policies',
    '/admin/wordpress-users',
    '/admin/departments',
    '/admin/labels',
    '/admin/users',
    '/admin/queue-health',
    '/admin/report-deliveries',
    '/admin/permissions',
    '/admin/mcp',
    '/settings/profile',
    '/settings/appearance',
    '/settings/notifications',
    '/settings/integrations',
];

/** List page → pattern of the first record link to follow from it. */
const RECORD_PAGES: [string, RegExp][] = [
    ['/boards', /^\/boards\/\d+$/],
    ['/tickets', /^\/tickets\/\d+$/],
    ['/projects', /^\/projects\/\d+$/],
    ['/hr/employees', /^\/hr\/employees\/\d+$/],
    ['/hr/assets', /^\/hr\/assets\/\d+$/],
    ['/hr/leave', /^\/hr\/leave\/requests\/\d+$/],
    ['/hr/payroll', /^\/hr\/payroll\/\d+$/],
    ['/hr/performance', /^\/hr\/performance\/reviews\/\d+$/],
];

test.beforeAll(() => {
    execFileSync('php', ['artisan', 'tinker', '--execute', "require 'tests/e2e/support/seed-smoke.php';"], {
        env: { ...process.env, DB_DATABASE: 'ewms_e2e' },
    });
});

/** Mark only the welcome tour as seen, so each page's own tour is the one that plays. */
async function resetTours(page: Page) {
    await page.evaluate(() => {
        const data = JSON.parse(document.getElementById('app')?.dataset.page ?? '{}');
        window.localStorage.setItem(`ewms-tours:${data.props.auth.user.id}`, JSON.stringify(['welcome']));
    });
}

async function visitAndTour(page: Page, url: string, errors: string[], played: string[] = []) {
    const response = await page.goto(url);
    const status = response?.status() ?? 0;
    // 403/404 just means this role can't open the page — that's the policy working.
    if (status >= 500) errors.push(`${url} → HTTP ${status}`);
    if (status >= 400) return;

    await resetTours(page);
    await page.reload();

    const tour = page.getByTestId('tour-overlay');
    // Not every page has a tour step this role can see; give autoplay a moment.
    if (!(await tour.isVisible({ timeout: 2500 }).catch(() => false))) {
        await page.waitForTimeout(1200);
        if (!(await tour.isVisible())) return;
    }

    for (let i = 0; i < 15; i++) {
        const next = tour.getByRole('button', { name: /^(Next|Done)$/ });
        await expect(next, `${url}: tour step ${i + 1}`).toBeVisible({ timeout: 4000 });
        const label = await next.innerText();
        await next.click();
        if (label === 'Done') break;
    }
    played.push(url);
    await expect(tour, `${url}: tour should close after Done`).not.toBeVisible({ timeout: 4000 });
}

for (const email of ['admin@ewms.test', 'employee@ewms.test']) {
    test(`every page loads and its tour plays through, as ${email}`, async ({ page, loginAs }) => {
        test.setTimeout(600_000);
        const errors: string[] = [];
        const played: string[] = [];
        page.on('pageerror', (error) => errors.push(`${page.url()} → ${error.message}`));
        page.on('response', (response) => {
            if (response.status() >= 500) errors.push(`${response.url()} → HTTP ${response.status()}`);
        });

        await loginAs(email);
        await page.goto('/dashboard');

        for (const url of LIST_PAGES) {
            await visitAndTour(page, url, errors, played);
        }

        for (const [listUrl, pattern] of RECORD_PAGES) {
            const response = await page.goto(listUrl);
            if ((response?.status() ?? 0) >= 400) continue;
            const hrefs = await page.locator('a[href]').evaluateAll((links) => links.map((a) => a.getAttribute('href') ?? ''));
            const record = hrefs.find((href) => pattern.test(href));
            if (record) await visitAndTour(page, record, errors, played);
        }

        // Score board HOD panels, started from their own "Take the tour" button.
        for (const url of ['/seo-board/hod', '/cs-board/hod']) {
            const response = await page.goto(url);
            if ((response?.status() ?? 0) >= 400) continue;
            await page.waitForTimeout(1500);
            const tour = page.getByTestId('tour-overlay');
            if (await tour.isVisible()) await tour.getByRole('button', { name: 'Close tour' }).click();
            const button = page.getByRole('button', { name: 'Take the tour' }).first();
            if (!(await button.isVisible())) continue;
            await button.click();
            await expect(tour, `${url}: Take the tour`).toBeVisible();
            for (let i = 0; i < 15; i++) {
                const next = tour.getByRole('button', { name: /^(Next|Done)$/ });
                const label = await next.innerText();
                await next.click();
                if (label === 'Done') break;
            }
            await expect(tour).not.toBeVisible();
            played.push(`${url} (HOD panel)`);
        }

        // The task window and its follow-up tour.
        await page.goto('/boards');
        const board = (await page.locator('a[href]').evaluateAll((links) => links.map((a) => a.getAttribute('href') ?? ''))).find((h) =>
            /^\/boards\/\d+$/.test(h),
        );
        if (board) {
            await page.goto(board);
            await resetTours(page);
            const seen = ['welcome', 'board', 'board-manage'];
            await page.evaluate((ids) => {
                const data = JSON.parse(document.getElementById('app')?.dataset.page ?? '{}');
                window.localStorage.setItem(`ewms-tours:${data.props.auth.user.id}`, JSON.stringify(ids));
            }, seen);
            await page.reload();
            const addTask = page.getByRole('button', { name: /add task/i }).first();
            if (await addTask.isVisible()) {
                await addTask.click();
                await page.getByPlaceholder('Task title').fill('Smoke task');
                await page.getByPlaceholder('Task title').press('Enter');
                const card = page.locator('[role="button"]').filter({ hasText: 'Smoke task' }).first();
                for (const tourTitle of ['It saves by itself', 'Checklists']) {
                    await card.click();
                    const tour = page.getByTestId('tour-overlay');
                    await expect(tour).toContainText(tourTitle, { timeout: 6000 });
                    for (let i = 0; i < 15; i++) {
                        const next = tour.getByRole('button', { name: /^(Next|Done)$/ });
                        const label = await next.innerText();
                        await next.click();
                        if (label === 'Done') break;
                    }
                    await expect(tour).not.toBeVisible();
                    await page.keyboard.press('Escape');
                    await expect(page.getByRole('dialog')).not.toBeVisible();
                }
            }
        }

        console.log(`${email}: tours played on ${played.length} pages → ${played.join(', ')}`);
        expect(errors).toEqual([]);
        // Guard against the test silently doing nothing (e.g. autoplay switched off).
        expect(played.length).toBeGreaterThan(email.startsWith('admin') ? 40 : 8);
    });
}
