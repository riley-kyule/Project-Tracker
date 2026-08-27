#!/usr/bin/env node
/**
 * Logs into each WordPress site's wp-admin with an existing admin account,
 * creates an Application Password named "EWMS", and records the result.
 *
 * This is deliberately NOT part of the EWMS Laravel app — it's a one-off
 * operational tool for the initial onboarding of many sites at once, run by
 * hand against a local CSV of admin credentials. It must never run against
 * files inside the EWMS git repo (see assertNotInsideGitRepo below) — the
 * input carries live admin passwords, and the output carries generated
 * Application Passwords, both of which are sensitive.
 *
 * Usage:
 *   node onboard.js --input=/path/to/sites.csv --output-dir=/path/to/out [--limit=2] [--start-at=0]
 *
 * Input CSV columns (header row required): name,domain,wp_admin_username,wp_admin_password
 * Output CSV columns: name,domain,wp_username,wp_app_password,status,error
 *   status is one of: ok | login_failed | two_factor_required | app_password_failed | error
 *
 * Recommended: run with --limit=2 first against a couple of real sites and
 * check the output before running the full batch — WordPress core's
 * Application Passwords markup is stable but this hasn't been exercised
 * against your actual sites/theme/plugins yet.
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

function parseArgs(argv) {
    const args = { limit: Infinity, startAt: 0 };
    for (const raw of argv.slice(2)) {
        const [key, value] = raw.replace(/^--/, '').split(/=(.*)/s);
        if (key === 'input') args.input = value;
        else if (key === 'output-dir') args.outputDir = value;
        else if (key === 'limit') args.limit = parseInt(value, 10);
        else if (key === 'start-at') args.startAt = parseInt(value, 10);
    }
    if (!args.input || !args.outputDir) {
        console.error('Usage: node onboard.js --input=sites.csv --output-dir=./out [--limit=N] [--start-at=N]');
        process.exit(1);
    }
    return args;
}

/** Refuses to write anywhere inside a git working tree, walking up from the target directory. */
function assertNotInsideGitRepo(targetDir) {
    let dir = path.resolve(targetDir);
    while (true) {
        if (fs.existsSync(path.join(dir, '.git'))) {
            console.error(`Refusing to run: "${targetDir}" is inside a git repository (found .git at ${dir}).`);
            console.error('This tool writes live credentials to disk — point --output-dir somewhere outside any git checkout.');
            process.exit(1);
        }
        const parent = path.dirname(dir);
        if (parent === dir) return; // reached filesystem root
        dir = parent;
    }
}

// --- Minimal RFC4180-ish CSV parsing/writing (no external dependency) ---

function parseCsv(text) {
    const rows = [];
    let row = [];
    let field = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inQuotes) {
            if (c === '"' && text[i + 1] === '"') {
                field += '"';
                i++;
            } else if (c === '"') {
                inQuotes = false;
            } else {
                field += c;
            }
        } else if (c === '"') {
            inQuotes = true;
        } else if (c === ',') {
            row.push(field);
            field = '';
        } else if (c === '\n' || c === '\r') {
            if (c === '\r' && text[i + 1] === '\n') i++;
            row.push(field);
            field = '';
            if (row.length > 1 || row[0] !== '') rows.push(row);
            row = [];
        } else {
            field += c;
        }
    }
    if (field !== '' || row.length > 0) {
        row.push(field);
        rows.push(row);
    }

    const [header, ...data] = rows;
    return data.map((r) => Object.fromEntries(header.map((h, idx) => [h.trim(), (r[idx] ?? '').trim()])));
}

function csvEscape(value) {
    const s = String(value ?? '');
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

function writeCsvRow(stream, columns, row) {
    stream.write(columns.map((c) => csvEscape(row[c])).join(',') + '\n');
}

// --- WordPress automation ---

const OUTPUT_COLUMNS = ['name', 'domain', 'wp_username', 'wp_app_password', 'status', 'error'];

function normalizeDomain(domain) {
    const trimmed = domain.trim().replace(/\/+$/, '');
    return /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
}

async function onboardSite(browser, site) {
    const baseUrl = normalizeDomain(site.domain);
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.fill('#user_login', site.wp_admin_username);
        await page.fill('#user_pass', site.wp_admin_password);
        await Promise.all([page.waitForLoadState('domcontentloaded'), page.click('#wp-submit')]);

        // A 2FA plugin typically replaces the normal wp-admin redirect with its
        // own challenge page — detect common markers rather than assume any one plugin.
        const bodyText = await page.textContent('body').catch(() => '');
        if (/two.factor|authentication code|verification code|one-time password/i.test(bodyText || '') && !page.url().includes('/wp-admin/')) {
            return { status: 'two_factor_required', error: '2FA challenge detected — create this one manually.' };
        }

        const loginError = await page.locator('#login_error').first();
        if (await loginError.count()) {
            const message = (await loginError.textContent())?.trim() || 'Login rejected.';
            return { status: 'login_failed', error: message.replace(/\s+/g, ' ') };
        }

        if (!page.url().includes('/wp-admin/')) {
            return { status: 'login_failed', error: `Unexpected post-login URL: ${page.url()}` };
        }

        await page.goto(`${baseUrl}/wp-admin/profile.php`, { waitUntil: 'domcontentloaded', timeout: 30000 });

        const nameField = page.locator('#new_application_password_name');
        if (!(await nameField.count())) {
            return { status: 'app_password_failed', error: 'Application Passwords section not found on profile.php (feature may be disabled on this site).' };
        }

        await nameField.fill('EWMS');
        await page.click('#do_new_application_password');

        // WP core renders the generated password into a readonly input right after
        // creation — it's shown exactly once. Give the AJAX call a generous window.
        const passwordField = page.locator('input.wp-application-password-value, #new-application-password-value');
        await passwordField.first().waitFor({ state: 'visible', timeout: 20000 });
        const appPassword = (await passwordField.first().inputValue()).trim();

        if (!appPassword) {
            return { status: 'app_password_failed', error: 'Application Password field appeared but was empty.' };
        }

        return { status: 'ok', wp_app_password: appPassword };
    } catch (err) {
        return { status: 'error', error: String(err.message || err).replace(/\s+/g, ' ').slice(0, 300) };
    } finally {
        await context.close();
    }
}

async function main() {
    const args = parseArgs(process.argv);
    assertNotInsideGitRepo(args.outputDir);

    const sites = parseCsv(fs.readFileSync(args.input, 'utf8'));
    const batch = sites.slice(args.startAt, args.startAt + args.limit);

    fs.mkdirSync(args.outputDir, { recursive: true });
    const failureDir = path.join(args.outputDir, 'failures');
    fs.mkdirSync(failureDir, { recursive: true });

    const outPath = path.join(args.outputDir, `wordpress-app-passwords-${Date.now()}.csv`);
    const out = fs.createWriteStream(outPath, { flags: 'w' });
    out.write(OUTPUT_COLUMNS.join(',') + '\n');

    console.log(`Onboarding ${batch.length} site(s) (of ${sites.length} total in the input file)…`);
    console.log(`Writing results to ${outPath}\n`);

    const browser = await chromium.launch({ headless: true });
    let ok = 0, skipped = 0, failed = 0;

    for (const [i, site] of batch.entries()) {
        process.stdout.write(`[${i + 1}/${batch.length}] ${site.name} (${site.domain}) … `);

        const result = await onboardSite(browser, site);
        const row = { name: site.name, domain: site.domain, wp_username: site.wp_admin_username, wp_app_password: '', ...result };
        writeCsvRow(out, OUTPUT_COLUMNS, row);

        if (result.status === 'ok') {
            ok++;
            console.log('ok');
        } else if (result.status === 'two_factor_required') {
            skipped++;
            console.log('skipped (2FA)');
        } else {
            failed++;
            console.log(`FAILED — ${result.status}: ${result.error}`);
        }

        // Deliberately sequential with a pause between sites, not parallel —
        // several of these sites run aggressive bot/WAF protection on
        // wp-login.php specifically, and hammering 131 logins back-to-back is
        // exactly the pattern that trips it.
        await new Promise((r) => setTimeout(r, 3000 + Math.random() * 2000));
    }

    await browser.close();
    out.end();

    console.log(`\nDone: ${ok} succeeded, ${skipped} skipped (need manual attention), ${failed} failed.`);
    console.log(`Results: ${outPath}`);
    if (skipped + failed > 0) {
        console.log('Re-run the failed/skipped rows individually after fixing the underlying issue — this script is safe to re-run, it never modifies input.');
    }
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
