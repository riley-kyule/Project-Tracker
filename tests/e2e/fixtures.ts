import { test as base, expect } from '@playwright/test';

type Fixtures = {
    loginAs: (email: string) => Promise<void>;
};

/**
 * EWMS has no password login — Google SSO is the only real sign-in method —
 * so there's no OAuth flow for the suite to drive. loginAs hits the
 * Playwright-only bypass (routes/e2e.php) instead; see its docblock for why
 * that's safe to ship (registered only under ALLOW_E2E_LOGIN, which the
 * webServer config here sets against a throwaway database only).
 */
export const test = base.extend<Fixtures>({
    loginAs: async ({ page, context }, use) => {
        await use(async (email: string) => {
            // page.request bypasses page JS entirely, so nothing mirrors the
            // XSRF-TOKEN cookie Laravel sets into the X-XSRF-TOKEN header the
            // way axios/Inertia normally do in the browser — a plain POST
            // here 419s. Visit any page first to get the cookie, then carry
            // it over by hand.
            await page.goto('/login');
            const xsrfCookie = (await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN');
            if (!xsrfCookie) {
                throw new Error('No XSRF-TOKEN cookie present before e2e login');
            }

            const response = await page.request.post('/_e2e/login', {
                data: { email },
                headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie.value) },
            });
            if (!response.ok()) {
                throw new Error(`e2e login failed for ${email}: ${response.status()} ${await response.text()}`);
            }
        });
    },
});

export { expect };
