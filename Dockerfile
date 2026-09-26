# syntax=docker/dockerfile:1
#
# DukaMkononi backend (Laravel) — deployment image.
#
# Two stages:
#   1. `vendor`  — installs PHP dependencies with Composer.
#   2. `app`     — PHP-FPM with Nginx expected as a sidecar (see docker-compose.yml).

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
# Stage 2: Runtime (PHP-FPM)
# ---------------------------------------------------------------------------
FROM php:8.2-fpm AS app

# System packages needed to build the PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
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
        backup \
    && chown -R www-data:www-data storage bootstrap/cache backup \
    && chmod +x docker/docker-entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["docker/docker-entrypoint.sh"]
CMD ["php-fpm"]