#!/bin/sh
set -e

# The storage/ named volume starts empty on a host that's never run this
# stack before, which shadows the directories Laravel expects to exist
# (framework/cache/data, framework/sessions, framework/views, logs) —
# self-heal on every start rather than rely on the volume already having
# them, since that's not guaranteed across Docker versions or a volume
# prune.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# Running as root at this point (no USER in the Dockerfile) so this can
# chown the bind-mounted host directory, which — unlike the old baked-in-
# image COPY — starts out owned by whichever host user ran `git clone`, not
# www-data. www-data needs write access everywhere in it, not just storage/:
# composer/npm create vendor/, node_modules/, public/build/ here, and a
# self-deploy's `git merge` can touch any tracked file, including .git/
# itself. Runs on every boot (not marker-guarded) so a volume reset or a
# fresh host-side clone can never leave part of the tree wrongly owned.
#
# .env is its own separate read-only bind mount nested inside this one, so
# chown can never touch it (EROFS) — that's expected and harmless (php-fpm
# only ever reads it), but it does mean the command's own exit code always
# looks like a failure; `|| true` stops that from tripping `set -e`.
chown -R www-data:www-data /var/www/html 2>/dev/null || true

# vendor/ and public/build/ are gitignored, so a fresh checkout (or the
# first boot after switching to the bind-mounted deploy model) won't have
# them yet — install once, then every later boot skips straight past.
# BusyBox's flock (not util-linux's) is what Alpine actually has: no -w
# timeout support, just blocks until it gets the lock. That's fine here —
# it serializes this across app/queue/scheduler, which all share this
# entrypoint and can start concurrently against the same bind-mounted code;
# without it they'd race to write vendor/node_modules at the same time.
(
    flock 200

    if [ ! -f vendor/autoload.php ]; then
        su-exec www-data composer install --no-dev --classmap-authoritative --no-interaction
    fi

    if [ ! -f public/build/manifest.json ]; then
        su-exec www-data npm ci
        su-exec www-data npm run build
    fi
) 200>storage/.bootstrap.lock

# Runs on every container start (app, queue, and scheduler all share this
# entrypoint) — cheap and idempotent, and needs the full set of PHP
# extensions this image has, which a bare composer CLI doesn't.
su-exec www-data php artisan package:discover --ansi

exec su-exec www-data "$@"
