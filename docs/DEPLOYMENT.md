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
docker compose exec app composer install --no-dev --classmap-authoritative --no-interaction
docker compose exec app npm ci
docker compose exec app npm run build
docker compose exec app php artisan migrate --force
docker compose restart app queue scheduler
docker compose exec app php artisan optimize
```

The `composer install`/`npm ci`/`npm run build` steps are not optional here, even though this looks redundant with what `entrypoint.sh` does on container start. `entrypoint.sh` only installs those on a container's *first ever* boot (guarded by `vendor/autoload.php`/`public/build/manifest.json` already existing) — on every later `git pull`, that guard means neither one reruns automatically, so a manual redeploy that skips these two steps silently leaves the old Composer classmap and the old JS bundle in place: new PHP classes 500 with "Class ... not found", and new frontend code never reaches the browser no matter how many times it's hard-refreshed. `App\Jobs\DeployLatestRelease` (the in-app "Deploy now" button) already runs both unconditionally on every deploy for exactly this reason — this manual path must match it.

The `docker compose restart app` is equally non-optional, and easy to miss even after getting the two steps above right: `opcache.validate_timestamps=0` (`docker/php/opcache.ini`) is a deliberate production setting, but it means PHP-FPM never rechecks source files against what it already has compiled in memory. Regenerating the Composer classmap and the JS bundle on disk does nothing for a php-fpm worker that's still running — it keeps serving the old bytecode, "class not found" and all, until the container process itself restarts. `docker compose up -d` will not force that on an already-running container. The deploy job's *own* steps (composer/npm/artisan) never hit this themselves, since each runs as a fresh CLI process (`opcache.enable_cli=0`) — but the live `app`/`scheduler` processes still needed an explicit restart after those steps ran, which `DeployLatestRelease` now does too where configured; see "Restarting app/scheduler after a deploy" below.

`docker/entrypoint.sh` installs `vendor/` and `public/build/` on first boot if they're missing (both are gitignored, so a fresh checkout won't have them) — a `flock` around that section stops `app`/`queue`/`scheduler` from racing each other since they all share the same bind-mounted code and can start concurrently.

**Host user must match the container's `www-data` uid/gid.** Since these containers write into the bind-mounted checkout as `www-data`, that uid has to match whichever host account owns it — otherwise every chown/composer/npm/git operation the container does ends up owned by a uid the host user can't write to (host-side `git pull` starts failing, eventually with git's own "dubious ownership" refusal). The `Dockerfile` re-keys `www-data` to `1000:1000` by default; check the actual host account with `id` and pass `--build-arg WWW_UID=… --build-arg WWW_GID=…` (or add `args:` to `docker-compose.yml`'s `app`/`queue`/`scheduler` `build:` blocks) if it's different.

### Optional in-app self-deploy

Administrators and the CEO see a "Check for Updates" control in the sidebar. It always allows a read-only `git fetch` and ahead/behind comparison against the deploy branch. Actually triggering a deploy from it additionally requires `DEPLOY_SELF_UPDATE_ENABLED=true`, off by default. Where enabled, `App\Jobs\DeployLatestRelease` is queued and runs this exact release sequence *inside the `queue` container* (it's a queued job — `queue`, not `app`, is what actually executes it) against the bind-mounted code — `git merge --ff-only`, `composer install`, `npm ci && npm run build`, `migrate --force`, `optimize`, then a restart step (below), then `queue:restart` last — and records each attempt in the `deployments` table with an audit log entry. It holds the same `flock` as `entrypoint.sh`'s bootstrap install so a deploy can't race a container restart.

**Trade-off:** this only works because the containers bind-mount live code instead of baking it into the image at build time (see above) — a container that builds from `COPY`'d, image-baked code has no `.git` directory and no `composer`/`npm` binaries to run these commands against, and `DEPLOY_SELF_UPDATE_ENABLED=true` would just fail outright. If you ever move back to a pure immutable-artifact pipeline (a separate CI build + registry push, no bind mount), turn this back off — running `git merge`/`composer install`/`npm run build` inside a container with no persistent, writable checkout doesn't make sense and won't work.

**Restarting `app`/`scheduler` after a deploy.** Regenerating files on disk isn't enough on its own: `app` (PHP-FPM) has `opcache.validate_timestamps=0` and won't notice modified files without a process restart, and `scheduler` (`php artisan schedule:work`) is a long-lived daemon that keeps running whatever code it already loaded, indefinitely — it has no restart-signal mechanism the way `queue:work` does. (`queue` needs no special handling: `queue:restart` signals the worker to exit after its current job, and `restart: unless-stopped` respawns the container with fresh code.)

A process inside one container can't restart a *different* container without access to the Docker socket, so this requires deliberately opting in:
- `docker-compose.yml` bind-mounts `/var/run/docker.sock` into the `queue` service only — **this gives that one container control over any container on the host, not just this stack.** Only add this if you've accepted that trade-off.
- The `app` build target installs `docker-cli`/`docker-cli-compose` and adds `www-data` to a `docker` group. That group's GID must match the host socket's actual group (`stat -c '%g' /var/run/docker.sock`) — pass `--build-arg DOCKER_GID=...` at build time if it isn't the default `999`, the same way `WWW_UID`/`WWW_GID` work.
- Set `DEPLOY_COMPOSE_PROJECT` in `.env` to the actual Compose project name (check with `docker compose ls` — it's the directory basename on the host, e.g. `ewms`, but the container's bind-mounted path is `/var/www/html`, which would infer the wrong name if left to Compose's default inference). `DeployLatestRelease` uses this to run `docker compose --project-name <that> restart app scheduler` from inside the `queue` container against the bind-mounted `docker-compose.yml`. Left unset, that restart step is skipped (logged, not silently ignored) rather than risk targeting the wrong project.

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
