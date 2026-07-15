# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# node-build — compiles the Vite/React frontend into public/build.
# -----------------------------------------------------------------------------
FROM node:22-alpine AS node-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# -----------------------------------------------------------------------------
# composer-build — installs PHP dependencies and generates the optimized
# autoloader. Nothing from this stage's own PHP CLI ships in the final image.
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --optimize-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# -----------------------------------------------------------------------------
# app — the PHP-FPM runtime. The app/queue/scheduler services in
# docker-compose.yml all build from this target and differ only in command.
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache \
        postgresql-dev icu-dev libzip-dev oniguruma-dev libxml2-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring xml zip bcmath intl gd pcntl opcache

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-recommended.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads-recommended.ini

WORKDIR /var/www/html
COPY --from=composer-build /app /var/www/html
COPY --from=node-build /app/public/build /var/www/html/public/build
RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# webserver — nginx. Serves public/ directly and forwards *.php to the app
# service's php-fpm over the internal Compose network. Traefik terminates
# TLS in front of this and never talks to PHP directly.
# -----------------------------------------------------------------------------
FROM nginx:1.27-alpine AS webserver
COPY --from=composer-build /app/public /var/www/html/public
COPY --from=node-build /app/public/build /var/www/html/public/build
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
