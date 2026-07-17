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

# Re-key www-data to the host account's uid/gid instead of Alpine's default
# 82:82. docker-compose.yml bind-mounts the real host checkout into these
# containers, so whatever uid www-data ends up as is the uid that ends up
# owning every file entrypoint.sh's chown (and composer/npm/git, all run as
# www-data) touches in it — if that doesn't match the host account, the
# host user loses write access to their own checkout, including .git.
# Override at build time with --build-arg WWW_UID/WWW_GID if that account
# isn't 1000:1000 (check with `id` on the host).
ARG WWW_UID=1000
ARG WWW_GID=1000
RUN deluser www-data 2>/dev/null; \
    delgroup www-data 2>/dev/null; \
    addgroup -g "${WWW_GID}" www-data && \
    adduser -D -u "${WWW_UID}" -G www-data -h /var/www -s /sbin/nologin www-data && \
    chown -R www-data:www-data /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-recommended.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads-recommended.ini

WORKDIR /var/www/html

# entrypoint.sh starts as root (needed to chown the bind-mounted checkout,
# which won't already be www-data-owned like a baked-in COPY was) and
# drops to www-data via su-exec for composer/npm/artisan — but not for
# php-fpm itself, which handles its own privilege drop internally (see the
# comment in entrypoint.sh for why that distinction matters).
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
