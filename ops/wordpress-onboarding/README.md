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

The Playwright browser needs its own dependencies — run it via the official
Playwright Docker image rather than installing anything into the EWMS
containers. From this directory:

```bash
docker run --rm -it \
  -v "$(pwd)":/work \
  -v /root/wp-onboarding:/data \
  -w /work \
  mcr.microsoft.com/playwright:v1.48.0-jammy \
  bash -c "npm install && node onboard.js --input=/data/sites.csv --output-dir=/data/out --limit=2"
```

Replace `/root/wp-onboarding` with wherever you've put `sites.csv` on the
host (outside the repo). Start with `--limit=2` against two real sites and
check the output CSV before running the full batch — this hasn't been
exercised against your actual sites' theme/plugins yet, and WordPress core's
Application Passwords markup could in principle differ by version.

Once you're confident, drop `--limit` (or raise it) to run the rest:

```bash
... node onboard.js --input=/data/sites.csv --output-dir=/data/out --start-at=2
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
database — see `php artisan wordpress:import-sites` in the main EWMS repo
(run via `docker compose exec app php artisan wordpress:import-sites /path/to/results.csv`
on the EWMS server; the path must be reachable from inside the `app`
container, so copy the CSV somewhere under the EWMS bind mount first, or
adjust the volume/path accordingly — never into this repo's own directory).

Delete both the input credentials file and the results CSV once the import
succeeds. Application Passwords are revocable per-site if you ever need to
rotate them; the plaintext admin passwords in the input file are not worth
keeping around any longer than necessary either way.
