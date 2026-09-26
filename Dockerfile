# syntax=docker/dockerfile:1
#
# DukaMkononi backend (Laravel) — deployment image.
#
# Two stages:
#   1. `vendor` — installs PHP dependencies with Composer.
#   2. `app`    — nginx (HTTP :80) + PHP-FPM (FastCGI) in ONE container,
#                 supervised by a tiny entrypoint so PaaS platforms
#                 (Render, Railway, Fly.io...) that scan for an open HTTP
#                 port find one immediately.
#
# docker-compose.yml runs this same image and publishes port 80.

# ---------------------------------------------------------------------------
# Stage 1: Composer dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-plugins \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

# ---------------------------------------------------------------------------
# Stage 2: Runtime — nginx + PHP-FPM in one container
# ---------------------------------------------------------------------------
FROM php:8.2-fpm AS app

# nginx serves HTTP; the lib* packages are needed to build the PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        unzip \
        curl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        intl \
        zip \
        bcmath \
        exif \
        pcntl \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# PHP runtime configuration (memory limits, opcache, etc.)
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-dukamkononi.ini"

# Container-local nginx: replace the main config entirely (our file is a
# complete main config — worker/events/http — not a conf.d site snippet).
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Application source
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Writable paths: framework caches + logs + runtime backups
RUN mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache/data \
        storage/logs \
        /var/log/nginx \
        /var/lib/nginx \
        backup \
    && chown -R www-data:www-data storage bootstrap/cache backup /var/lib/nginx /var/log/nginx \
    && chmod +x docker/docker-entrypoint.sh docker/supervisord.sh

USER www-data

EXPOSE 8080

ENTRYPOINT ["docker/docker-entrypoint.sh"]
CMD ["bash", "docker/supervisord.sh"]
