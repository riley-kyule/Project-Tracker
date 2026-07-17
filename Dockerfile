# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# app — the PHP-FPM runtime. The app/queue/scheduler services in
# docker-compose.yml all build from this target and differ only in command.
#
# Application code is NOT baked into this image — app/queue/scheduler/
# webserver all bind-mount the real working directory from the host (see
# docker-compose.yml), so the in-app self-deploy feature (config/deploy.php)
# can update it in place with git/composer/npm, all installed below.
# entrypoint.sh installs vendor/ and public/build/ on first boot if missing.
# This trades image immutability for a working "Deploy now" button — see
# docs/DEPLOYMENT.md's "Optional in-app self-deploy" section.
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache \
        postgresql-dev icu-dev libzip-dev oniguruma-dev libxml2-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        git nodejs npm su-exec \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring xml zip bcmath intl gd pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-recommended.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads-recommended.ini

WORKDIR /var/www/html

# entrypoint.sh starts as root (needed to chown the bind-mounted storage/
# volume, which won't already be www-data-owned like a baked-in COPY was)
# and drops to www-data via su-exec before running composer/npm/php-fpm.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# webserver — nginx. Serves public/ directly (via the same bind mount as the
# app service) and forwards *.php to the app service's php-fpm over the
# internal Compose network. Caddy (a separate stack, not managed in this
# repo — see the external "proxy" network in docker-compose.yml) terminates
# TLS in front of this and never talks to PHP directly.
# -----------------------------------------------------------------------------
FROM nginx:1.27-alpine AS webserver
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
