# Production deployment

## Required configuration

Start from `.env.example`, inject secrets through the deployment platform, and never commit the production environment file.

At minimum, set:

```dotenv
APP_NAME=EWMS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ewms.example.com
APP_TIMEZONE=UTC
APP_KEY=base64:generated-secret

ALLOW_ACCOUNT_DELETION=false

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

DB_CONNECTION=pgsql
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
```

Use distinct database and Redis credentials with the least privileges required by the application. Configure a real `MAIL_MAILER` and organizational sender address so password resets and notifications are deliverable.

TLS terminates in front of the app at a reverse proxy — Caddy, run as a separate stack, not managed in this repo. `bootstrap/app.php` already trusts its `X-Forwarded-*` headers (`at: '*'`, safe since the app is only ever reachable through that proxy) so secure URLs, cookies, client IP audit data, and HSTS resolve correctly; `docker/nginx.conf` sets the matching FastCGI params since nginx itself sits between Caddy and PHP-FPM. `docker-compose.yml`'s `webserver` service joins an external Docker network named `proxy` (not created by this repo) for Caddy to reach it — if that network doesn't already exist on a new host, create it (`docker network create proxy`) or match whatever your Caddy stack expects before first `docker compose up`.

## Release sequence

```bash
composer install --no-dev --classmap-authoritative --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Run `php artisan test`, `vendor/bin/pint --test`, `npm run check`, and dependency audits in CI before deploying the artifact.

### Docker Compose deployment (as built)

`docker-compose.yml` builds four images from the single multi-stage `Dockerfile` (`app`, `webserver`, `queue`, `scheduler` all share the `app` target and differ only in command) plus `postgres`/`redis`. **Application code is not baked into these images** — `app`, `queue`, `scheduler`, and `webserver` all bind-mount the real working directory (`.:/var/www/html`) from the host, specifically so the in-app self-deploy feature below can update it in place. This is a deliberate departure from a pure immutable-artifact model, made in exchange for a working "Deploy now" button — see the trade-off note below before assuming this is the only valid setup.

Manual redeploy on a host running this stack:

```bash
git pull origin main
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose restart queue scheduler
```

`docker/entrypoint.sh` installs `vendor/` and `public/build/` on first boot if they're missing (both are gitignored, so a fresh checkout won't have them) — a `flock` around that section stops `app`/`queue`/`scheduler` from racing each other since they all share the same bind-mounted code and can start concurrently.

**Host user must match the container's `www-data` uid/gid.** Since these containers write into the bind-mounted checkout as `www-data`, that uid has to match whichever host account owns it — otherwise every chown/composer/npm/git operation the container does ends up owned by a uid the host user can't write to (host-side `git pull` starts failing, eventually with git's own "dubious ownership" refusal). The `Dockerfile` re-keys `www-data` to `1000:1000` by default; check the actual host account with `id` and pass `--build-arg WWW_UID=… --build-arg WWW_GID=…` (or add `args:` to `docker-compose.yml`'s `app`/`queue`/`scheduler` `build:` blocks) if it's different.

### Optional in-app self-deploy

Administrators and the CEO see a "Check for Updates" control in the sidebar. It always allows a read-only `git fetch` and ahead/behind comparison against the deploy branch. Actually triggering a deploy from it additionally requires `DEPLOY_SELF_UPDATE_ENABLED=true`, off by default. Where enabled, `App\Jobs\DeployLatestRelease` runs this exact release sequence *inside the `app` container* against the bind-mounted code — `git merge --ff-only`, `composer install`, `npm ci && npm run build`, `migrate --force`, `optimize`, `queue:restart` — and records each attempt in the `deployments` table with an audit log entry. It holds the same `flock` as `entrypoint.sh`'s bootstrap install so a deploy can't race a container restart.

**Trade-off:** this only works because the containers bind-mount live code instead of baking it into the image at build time (see above) — a container that builds from `COPY`'d, image-baked code has no `.git` directory and no `composer`/`npm` binaries to run these commands against, and `DEPLOY_SELF_UPDATE_ENABLED=true` would just fail outright. If you ever move back to a pure immutable-artifact pipeline (a separate CI build + registry push, no bind mount), turn this back off — running `git merge`/`composer install`/`npm run build` inside a container with no persistent, writable checkout doesn't make sense and won't work.

## Long-running processes

- Run `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` under Supervisor, systemd, or an equivalent orchestrator.
- Run `php artisan schedule:run` every minute. The scheduler generates recurring tasks and due notifications.
- Monitor failed jobs and queue latency. Establish a documented retry or discard procedure.

## Data and files

- Back up PostgreSQL and private attachments together, encrypt backups, and test restores.
- Define retention for database sessions, logs, failed jobs, notifications, and attachments.
- Keep attachments outside the public web root. Downloads must continue through authorized application routes.
- The upload layer validates extension, MIME content, size, checksum, and parent-record access. Integrate malware scanning before accepting higher-risk formats or external users.

## Release verification

- Confirm `/up` succeeds from the load balancer.
- Exercise login, a board mutation, an attachment download, a ticket lifecycle change, and audit history with representative roles.
- Verify secure cookie attributes, security headers, mail delivery, queue processing, scheduled commands, backups, and error reporting.
- Roll back application code only with a database-compatible release. Review every migration's `down` path before executing rollback in production.
