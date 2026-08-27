# WordPress site onboarding (one-off tool)

Logs into each site's wp-admin with an existing admin account, creates an
Application Password named "EWMS", and writes the results to a local CSV.
Not part of the EWMS app itself — a throwaway operational tool for
connecting many sites to EWMS's WordPress Users page at once.

**Never put credential files inside this repo.** The script refuses to run
if `--output-dir` is inside any git working tree, but the input file is your
responsibility — keep it entirely outside `/srv/exotic/stacks/ewms` (or
wherever this checkout lives).

## Input format

A CSV with a header row, saved somewhere outside this repo:

```
name,domain,wp_admin_username,wp_admin_password
Exotic Kenya,exotickenya.com,admin,SomePassword123
Exotic Ghana,exoticghana.com,rileyseo,AnotherPassword456
```

## Running it

### Option A — natively (e.g. on a Mac), no Docker needed

Playwright has first-class native support on macOS/Linux/Windows — Docker is
only needed if you'd rather not install anything on the host at all. From
this directory:

```bash
npm install
npx playwright install chromium   # one-time browser download
node onboard.js --input=/path/to/sites.csv --output-dir=/path/to/out --limit=2
```

Keep `sites.csv` and `--output-dir` outside this repo entirely (e.g. in your
home folder) — the script refuses to run if `--output-dir` is inside a git
working tree, but that's a backstop, not a substitute for keeping the input
file elsewhere too.

### Option B — via Docker (e.g. on a server you'd rather not install Node on)

```bash
docker run --rm -it \
  -v "$(pwd)":/work \
  -v /root/wp-onboarding:/data \
  -w /work \
  mcr.microsoft.com/playwright:v1.62.1-jammy \
  bash -c "npm install && node onboard.js --input=/data/sites.csv --output-dir=/data/out --limit=2"
```

Replace `/root/wp-onboarding` with wherever you've put `sites.csv` on the
host (outside the repo).

### Either way

Start with `--limit=2` against two real sites and check the output CSV
before running the full batch — this hasn't been exercised against your
actual sites' theme/plugins yet, and WordPress core's Application Passwords
markup could in principle differ by version. Once you're confident, drop
`--limit` (or raise it) to run the rest:

```bash
node onboard.js --input=/path/to/sites.csv --output-dir=/path/to/out --start-at=2
```

`--start-at=N` resumes from row N of the input file — useful if a run gets
interrupted partway through. The script never modifies the input file, so
re-running any range is always safe.

## Output

A timestamped CSV in `--output-dir`, one row per site:

```
name,domain,wp_username,wp_app_password,status,error
```

`status` is one of:
- `ok` — Application Password created, ready to import
- `two_factor_required` — login needs 2FA; create this one manually in wp-admin
- `login_failed` — wrong credentials, or the site rejected the login some other way
- `app_password_failed` — logged in fine, but Application Passwords aren't available (feature disabled, or the page markup didn't match)
- `error` — network/timeout/unexpected failure; check the `error` column

Only `ok` rows are meant to be imported into EWMS.

## Importing into EWMS

Once you have a results CSV with `ok` rows, import it directly into the
database via `php artisan wordpress:import-sites`. That command runs inside
EWMS's own `app` container, so if you ran this tool locally (e.g. on a Mac)
rather than on the EWMS server, copy the results file there first —
`scp` is the simplest way:

```bash
scp /path/to/results.csv riley@crm:~/wp-import.csv
```

Then, on the EWMS server:

```bash
docker compose exec app php artisan wordpress:import-sites /path/to/results.csv
```

The path must be reachable from inside the `app` container — copy the CSV
somewhere under the EWMS bind mount first (e.g. the stack directory itself,
just not tracked by git), or adjust the volume/path accordingly. Never into
this repo's own directory in a way that could get `git add`ed.

Delete both the input credentials file and the results CSV once the import
succeeds. Application Passwords are revocable per-site if you ever need to
rotate them; the plaintext admin passwords in the input file are not worth
keeping around any longer than necessary either way.
