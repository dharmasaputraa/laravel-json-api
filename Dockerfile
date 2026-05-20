# =============================================================================
# Laravel 13 Standard — Multi-Stage Dockerfile
# Stage 1 (composer-deps) : install PHP dependencies
# Stage 2 (node-assets)   : build frontend assets
# Stage 3 (production)    : final lean image
# =============================================================================

# --------------------------------------------------------------------------- #
# Stage 1 — Composer dependencies                                             #
# --------------------------------------------------------------------------- #
FROM composer:2 AS composer-deps

WORKDIR /app

# Copy only manifest files for better layer caching
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-req=ext-pcntl

# Copy all source then generate optimized autoloader
COPY . .
RUN composer dump-autoload --optimize --no-scripts


# --------------------------------------------------------------------------- #
# Stage 2 — Node / Vite assets                                                #
# --------------------------------------------------------------------------- #
FROM node:20-alpine AS node-assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --ignore-scripts

COPY . .
RUN test -f .env || cp .env.example .env 2>/dev/null || true
RUN npm run build


# --------------------------------------------------------------------------- #
# Stage 3 — Production image (PHP 8.4 FPM)                                    #
# --------------------------------------------------------------------------- #
FROM php:8.4-fpm-alpine AS production

LABEL maintainer="your-team@example.com"
LABEL org.opencontainers.image.description="Laravel 13 + PHP-FPM"

# ---- System dependencies ----
RUN apk add --no-cache \
    libpng libzip icu-libs oniguruma \
    postgresql-libs \
    curl bash shadow \
    wget

# ---- PHP extensions ----
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        libpng-dev libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
        bcmath \
        opcache \
        pcntl \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# ---- PHP configuration ----
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini

# ---- Non-root user ----
RUN addgroup -g 1000 -S laravel \
    && adduser -u 1000 -S laravel -G laravel

# ---- App directory ----
WORKDIR /var/www/html

# Copy from previous stages
COPY --chown=laravel:laravel --from=composer-deps /app .
COPY --chown=laravel:laravel --from=node-assets  /app/public/build ./public/build

# Create required directories
RUN mkdir -p \
        storage/framework/{cache,sessions,views} \
        storage/logs \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache

USER laravel

EXPOSE 9000

# Health check for PHP-FPM
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO- http://localhost:9000/status || exit 1

# Default: run PHP-FPM
# Override in docker-compose for queue worker / scheduler
CMD ["php-fpm"]