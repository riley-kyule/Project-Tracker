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

# Runs on every container start (app, queue, and scheduler all share this
# entrypoint) — cheap and idempotent, and needs the full set of PHP
# extensions this image has, which composer-build's bare PHP CLI doesn't.
php artisan package:discover --ansi

exec "$@"
