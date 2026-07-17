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
# chown a bind-mounted host directory, which — unlike the old baked-in-image
# COPY — isn't already www-data-owned. Everything else runs as www-data.
chown -R www-data:www-data storage bootstrap/cache

# vendor/ and public/build/ are gitignored, so a fresh checkout (or the
# first boot after switching to the bind-mounted deploy model) won't have
# them yet — install once, then every later boot skips straight past. The
# flock serializes this across app/queue/scheduler, which all share this
# entrypoint and can start concurrently against the same bind-mounted code;
# without it they'd race to write vendor/node_modules at the same time.
(
    flock -w 300 200

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
