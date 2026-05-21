# Laravel 13 Standard Docker Setup Guide

> Complete guide for running **Laravel 13** with **PHP-FPM**, **Nginx**, **PostgreSQL**, **Redis**, and **Laravel Horizon** — no Octane, no RoadRunner, no PgBouncer.

---

## Table of Contents

- [Architecture](#architecture)
- [File Structure](#file-structure)
- [Step 1 — Dockerfile](#step-1--dockerfile)
- [Step 2 — Nginx Config](#step-2--nginx-config)
- [Step 3 — PHP Config](#step-3--php-config)
- [Step 4 — OPcache Config](#step-4--opcache-config)
- [Step 5 — PostgreSQL Config](#step-5--postgresql-config)
- [Step 6 — Docker Compose (Development)](#step-6--docker-compose-development)
- [Step 7 — Docker Compose (Production Override)](#step-7--docker-compose-production-override)
- [Step 8 — Environment Variables](#step-8--environment-variables)
- [Step 9 — Laravel Horizon Config](#step-9--laravel-horizon-config)
- [Step 10 — Horizon Service Provider](#step-10--horizon-service-provider)
- [Step 11 — Base Job Class](#step-11--base-job-class)
- [Step 12 — Dockerignore](#step-12--dockerignore)
- [Development Setup](#development-setup)
- [Production Setup](#production-setup)
- [Common Commands](#common-commands)
- [Scaling](#scaling)
- [Troubleshooting](#troubleshooting)

---

## Architecture

```
                    ┌─────────────┐
                    │   Browser    │
                    └──────┬──────┘
                           │ :80
                    ┌──────▼──────┐
                    │    Nginx     │  ← Reverse proxy + static files
                    │  (fastcgi)   │
                    └──────┬──────┘
                           │ :9000 (FastCGI)
              ┌────────────▼────────────┐
              │   PHP-FPM (x N workers) │  ← Standard PHP-FPM pool
              │   php-fpm                │
              └──┬──────────┬──────────┬┘
                 │          │          │
           ┌─────▼───┐ ┌───▼────┐ ┌──▼──────┐
           │PostgreSQL│ │ Redis  │ │ Horizon │
           │  :5432   │ │ :6379  │ │ Worker  │
           └──────────┘ └────────┘ └─────────┘
```

**Services:**

| Service | Image | Description |
|---|---|---|
| `app` | Custom (PHP 8.3 FPM) | Laravel PHP-FPM application |
| `nginx` | `nginx:1.27-alpine` | Reverse proxy, static assets, SSL termination |
| `postgres` | `postgres:16-alpine` | PostgreSQL database |
| `redis` | `redis:7-alpine` | Cache, session, queue backend |
| `queue` | Custom (PHP 8.3 CLI) | Laravel Horizon queue manager |
| `scheduler` | Custom (PHP 8.3 CLI) | Task scheduler (`schedule:work`) |

---

## File Structure

```
project-root/
├── Dockerfile                      # Multi-stage build (composer → node → production)
├── docker-compose.yml              # Full stack for development
├── docker-compose.prod.yml         # Production overrides
├── .dockerignore                   # Docker build exclusions
├── .env                            # Environment config (create from .env.example)
├── .env.example                    # Environment template
├── config/
│   ├── horizon.php                 # Horizon queue configuration
│   └── database.php                # Standard pgsql config (no emulate prepares)
├── app/
│   ├── Jobs/
│   │   └── BaseJob.php             # Abstract base job with retry/backoff
│   └── Providers/
│       └── HorizonServiceProvider.php
├── docker/
│   ├── nginx/
│   │   └── default.conf            # Nginx → PHP-FPM config
│   ├── php/
│   │   ├── php.ini                 # Custom PHP settings
│   │   └── opcache.ini             # OPcache + JIT configuration
│   └── postgres/
│       └── postgresql.conf         # PostgreSQL tuning
└── ...                             # Standard Laravel files
```

---

## Step 1 — Dockerfile

```dockerfile
# =============================================================================
# Laravel 13 Standard — Multi-Stage Dockerfile
# Stage 1 (composer-deps) : install PHP dependencies
# Stage 2 (node-assets)   : build frontend assets
# Stage 3 (production)    : final lean image
# =============================================================================

# --------------------------------------------------------------------------- #
# Stage 1 — Composer dependencies                                             #
# --------------------------------------------------------------------------- #
FROM composer:2.7 AS composer-deps

WORKDIR /app

# Copy only manifest files for better layer caching
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-scripts \
    --prefer-dist

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
# Stage 3 — Production image (PHP 8.3 FPM)                                    #
# --------------------------------------------------------------------------- #
FROM php:8.3-fpm-alpine AS production

LABEL maintainer="your-team@example.com"
LABEL org.opencontainers.image.description="Laravel 13 + PHP-FPM"

# ---- System dependencies ----
RUN apk add --no-cache \
    libpng libzip icu-libs oniguruma \
    curl bash shadow \
    wget

# ---- PHP extensions ----
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
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
```

> **Note:** The image serves both `php-fpm` (app service) and `php artisan` commands
> (queue/scheduler services). The `ENTRYPOINT` is not overridden — docker-compose
> uses `command:` to switch between PHP-FPM and artisan commands.

---

## Step 2 — Nginx Config

File: `docker/nginx/default.conf`

```nginx
# =============================================================================
# Nginx config — PHP-FPM upstream (standard Laravel)
# =============================================================================

upstream php-fpm {
    server app:9000;
}

server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Max upload size
    client_max_body_size 50M;

    # Charset
    charset utf-8;

    # Logging
    access_log /var/log/nginx/access.log;
    error_log  /var/log/nginx/error.log;

    # Health check endpoint — bypass Laravel
    location /health {
        access_log off;
        return 200 '{"status":"ok"}';
        add_header Content-Type application/json;
    }

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing via FastCGI
    location ~ \.php$ {
        fastcgi_pass php-fpm;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param HTTP_HOST $host;
        include fastcgi_params;

        # Timeouts
        fastcgi_read_timeout 60s;
        fastcgi_connect_timeout 60s;

        # Buffers
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Laravel storage link (user uploads)
    location /storage {
        alias /var/www/html/storage/app/public;
        expires 1y;
        add_header Cache-Control "public";
        access_log off;
    }
}
```

---

## Step 3 — PHP Config

File: `docker/php/php.ini`

```ini
; =============================================================================
; php.ini — Custom PHP settings for Laravel
; =============================================================================

[PHP]
memory_limit       = 256M
upload_max_filesize = 50M
post_max_size      = 50M
max_execution_time = 60
max_input_time     = 60

; Error reporting (production values — override in dev if needed)
display_errors     = Off
display_startup_errors = Off
error_reporting    = E_ALL
log_errors         = On
error_log          = /var/log/php/error.log

; Date
date.timezone      = Asia/Jakarta

; Session
session.save_handler = redis
session.save_path    = "tcp://redis:6379?database=2"

; Realpath cache (important for Laravel)
realpath_cache_size  = 4096K
realpath_cache_ttl   = 600

; OPcache (see opcache.ini for full config)
opcache.enable       = 1
```

---

## Step 4 — OPcache Config

File: `docker/php/opcache.ini`

```ini
; =============================================================================
; opcache.ini — OPcache + JIT configuration
; =============================================================================

[opcache]
opcache.enable              = 1
opcache.enable_cli          = 1
opcache.memory_consumption  = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.revalidate_freq     = 0
opcache.save_comments       = 1
opcache.fast_shutdown       = 1
opcache.jit                 = 1255
opcache.jit_buffer_size     = 64M
```

> **Note:** `validate_timestamps = 0` means PHP won't check file modification times.
> After deploying new code, restart PHP-FPM: `docker compose restart app`

---

## Step 5 — PostgreSQL Config

File: `docker/postgres/postgresql.conf`

```ini
; =============================================================================
; postgresql.conf — Custom PostgreSQL configuration for Laravel
; =============================================================================

# Connection
listen_addresses       = '*'
max_connections        = 100
port                   = 5432

# Memory (tune based on server RAM)
shared_buffers         = 128MB
effective_cache_size   = 256MB
work_mem               = 4MB
maintenance_work_mem   = 64MB

# WAL
wal_buffers            = 16MB
min_wal_size           = 80MB
max_wal_size           = 1GB

# Query planner
random_page_cost       = 1.1
effective_io_concurrency = 200

# Logging
log_statement          = 'none'
log_min_duration_statement = 0
log_checkpoints        = on
log_connections        = on
log_disconnections     = on

# Locale
lc_messages            = 'en_US.utf8'
lc_monetary            = 'en_US.utf8'
lc_numeric             = 'en_US.utf8'
lc_time                = 'en_US.utf8'

# Autovacuum
autovacuum             = on
autovacuum_max_workers = 2

# Timezone
timezone               = 'Asia/Jakarta'
log_timezone           = 'Asia/Jakarta'
```

> **Note:** `max_connections = 100` — since there's no PgBouncer, each PHP-FPM worker
> opens its own connection. Monitor and increase if needed.

---

## Step 6 — Docker Compose (Development)

File: `docker-compose.yml`

```yaml
# =============================================================================
# docker-compose.yml — Laravel 13 Standard (Development)
#
# Usage:
#   Dev  : docker compose up
#   Prod : docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
#
# Services:
#   app       — Laravel PHP-FPM
#   nginx     — Reverse proxy + static files
#   postgres  — Database
#   redis     — Cache, session, queue
#   queue     — Laravel Horizon (dashboard + workers)
#   scheduler — Task scheduler
# =============================================================================

services:

  # ---------------------------------------------------------------------------
  # App — Laravel PHP-FPM
  # ---------------------------------------------------------------------------
  app:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    image: ${APP_IMAGE:-laravel-app}:${APP_VERSION:-latest}
    container_name: ${COMPOSE_PROJECT_NAME:-laravel}_app
    restart: unless-stopped
    environment:
      APP_ENV: ${APP_ENV:-local}
      APP_KEY: ${APP_KEY}
      APP_DEBUG: ${APP_DEBUG:-false}
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-laravel}
      DB_USERNAME: ${DB_USERNAME:-laravel}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
      REDIS_PORT: 6379
      CACHE_DRIVER: redis
      SESSION_DRIVER: redis
      QUEUE_CONNECTION: redis
    volumes:
      - app_storage:/var/www/html/storage
    networks:
      - internal
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD-SHELL", "php-fpm -t 2>/dev/null || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s
    deploy:
      replicas: 1
      resources:
        limits:
          memory: 512M
        reservations:
          memory: 128M

  # ---------------------------------------------------------------------------
  # Nginx — Reverse proxy + serve static assets
  # ---------------------------------------------------------------------------
  nginx:
    image: nginx:1.27-alpine
    container_name: ${COMPOSE_PROJECT_NAME:-laravel}_nginx
    restart: unless-stopped
    ports:
      - "${APP_PORT:-80}:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - app_storage:/var/www/html/storage:ro
      - ./public:/var/www/html/public:ro
    networks:
      - internal
      - external
    depends_on:
      app:
        condition: service_healthy

  # ---------------------------------------------------------------------------
  # PostgreSQL 16
  # ---------------------------------------------------------------------------
  postgres:
    image: postgres:16-alpine
    container_name: ${COMPOSE_PROJECT_NAME:-laravel}_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-laravel}
      POSTGRES_USER: ${DB_USERNAME:-laravel}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/postgresql.conf:/etc/postgresql/postgresql.conf:ro
    command: postgres -c config_file=/etc/postgresql/postgresql.conf
    networks:
      - internal
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-laravel} -d ${DB_DATABASE:-laravel}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    deploy:
      resources:
        limits:
          memory: 512M

  # ---------------------------------------------------------------------------
  # Redis 7
  # ---------------------------------------------------------------------------
  redis:
    image: redis:7-alpine
    container_name: ${COMPOSE_PROJECT_NAME:-laravel}_redis
    restart: unless-stopped
    command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    networks:
      - internal
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3

  # ---------------------------------------------------------------------------
  # Queue — Laravel Horizon (dashboard + workers)
  # ---------------------------------------------------------------------------
  queue:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    image: ${APP_IMAGE:-laravel-app}:${APP_VERSION:-latest}
    container_name: ${COMPOSE_PROJECT_NAME:-laravel}_queue
    restart: unless-stopped
    command: ["php", "artisan", "horizon"]
    environment:
      APP_ENV: ${APP_ENV:-local}
      APP_KEY: ${APP_KEY}
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-laravel}
      DB_USERNAME: ${DB_USERNAME:-laravel}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
      REDIS_PORT: 6379
      QUEUE_CONNECTION: redis
      CACHE_DRIVER: redis
      SESSION_DRIVER: redis
    volumes:
      - app_storage:/var/www/html/storage
    networks:
      - internal
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD-SHELL", "php artisan horizon:status | grep -q 'running'"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s
    deploy:
      replicas: 1

  # ---------------------------------------------------------------------------
  # Scheduler — run schedule:work
  # ---------------------------------------------------------------------------
  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    image: ${APP_IMAGE:-laravel-app}:${APP_VERSION:-latest}
    container_name: ${COMPOSE_PROJECT_NAME:-laravel}_scheduler
    restart: unless-stopped
    command: ["php", "artisan", "schedule:work"]
    environment:
      APP_ENV: ${APP_ENV:-local}
      APP_KEY: ${APP_KEY}
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-laravel}
      DB_USERNAME: ${DB_USERNAME:-laravel}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
    volumes:
      - app_storage:/var/www/html/storage
    networks:
      - internal
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy


# =============================================================================
# Volumes
# =============================================================================
volumes:
  postgres_data:
    driver: local
  redis_data:
    driver: local
  app_storage:
    driver: local


# =============================================================================
# Networks
# =============================================================================
networks:
  internal:
    driver: bridge
    internal: true
  external:
    driver: bridge
```

---

## Step 7 — Docker Compose (Production Override)

File: `docker-compose.prod.yml`

```yaml
# =============================================================================
# docker-compose.prod.yml — Production overrides
#
# Usage:
#   docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
# =============================================================================

services:

  app:
    restart: always
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "5"
    deploy:
      replicas: 2            # Min 2 for zero-downtime
      update_config:
        order: start-first
        failure_action: rollback
        delay: 10s
      resources:
        limits:
          cpus: "1.0"
          memory: 512M
        reservations:
          memory: 256M

  nginx:
    restart: always
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "5"

  postgres:
    restart: always
    command: >
      postgres
      -c shared_buffers=256MB
      -c effective_cache_size=512MB
      -c max_connections=200
      -c work_mem=4MB
      -c maintenance_work_mem=64MB
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "3"

  redis:
    restart: always
    command: >
      redis-server
      --appendonly yes
      --maxmemory 512mb
      --maxmemory-policy allkeys-lru
      --requirepass ${REDIS_PASSWORD:-}
    logging:
      driver: json-file
      options:
        max-size: "5m"
        max-file: "3"

  queue:
    restart: always
    environment:
      APP_ENV: production
      HORIZON_GRACEFUL_SHUTDOWN: "60"
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "5"
    deploy:
      replicas: 2
      update_config:
        order: start-first
        failure_action: rollback
        delay: 10s
      resources:
        limits:
          cpus: "0.5"
          memory: 512M
        reservations:
          memory: 128M

  scheduler:
    restart: always
    deploy:
      replicas: 1
```

---

## Step 8 — Environment Variables

### `.env.example`

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database — direct PostgreSQL connection
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis

CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"

# =============================================================================
# Laravel Horizon
# =============================================================================
HORIZON_PATH=horizon
HORIZON_GRACEFUL_SHUTDOWN=60
HORIZON_FAST_COMPLETION=true

# =============================================================================
# Docker
# =============================================================================
APP_PORT=80
```

### Production `.env` differences

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_PASSWORD=your-strong-password-here

REDIS_PASSWORD=your-redis-password

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io        # or your SMTP provider
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
```

---

## Step 9 — Laravel Horizon Config

File: `config/horizon.php`

```php
<?php

return [

    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'middleware' => ['web'],

    'waits' => [
        'redis:emails'  => 30,
        'redis:default' => 60,
        'redis:media'   => 120,
    ],

    'trim' => [
        'recent'        => 60 * 24,
        'pending'       => 60 * 24,
        'completed'     => 60 * 24,
        'recent_failed' => 60 * 24 * 7,
        'failed'        => 60 * 24 * 30,
        'monitored'     => 60 * 24 * 7,
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection'  => 'redis',
                'queue'       => ['emails', 'default', 'media'],
                'balance'     => 'auto',
                'autoScaling' => [
                    'maxProcesses' => 10,
                    'maxWorkTime'  => 5,
                    'maxIdleTime'  => 30,
                ],
                'maxProcesses' => 10,
                'maxTime'      => 0,
                'maxJobs'      => 1000,
                'memory'       => 256,
                'tries'        => 3,
                'timeout'      => 120,
                'nice'         => 0,
                'balanceCooldown' => 3,
                'balanceMaxShift'  => 1,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue'      => ['emails', 'default', 'media'],
                'balance'    => 'simple',
                'processes'  => 3,
                'tries'      => 3,
                'timeout'    => 90,
                'memory'     => 128,
            ],
        ],

        'staging' => [
            'supervisor-1' => [
                'connection'  => 'redis',
                'queue'       => ['emails', 'default', 'media'],
                'balance'     => 'auto',
                'autoScaling' => ['maxProcesses' => 5],
                'maxProcesses' => 5,
                'maxJobs'      => 500,
                'memory'       => 128,
                'tries'        => 3,
                'timeout'      => 120,
            ],
        ],
    ],

    'graceful_shutdown' => env('HORIZON_GRACEFUL_SHUTDOWN', 60),
    'fast_completion' => env('HORIZON_FAST_COMPLETION', true),
];
```

---

## Step 10 — Horizon Service Provider

File: `app/Providers/HorizonServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }

            // Production — restrict to admins
            // TODO: Replace with your actual admin check
            // return $user->hasRole('admin');
            return false;
        });
    }
}
```

Register in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
```

---

## Step 11 — Base Job Class

File: `app/Jobs/BaseJob.php`

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;
    public bool $deleteWhenMissingModels = true;
    public int $maxExceptions = 3;

    public function backoff(): array
    {
        return [
            $this->backoff,          // 1st retry: 60s
            $this->backoff * 5,      // 2nd retry: 300s
            $this->backoff * 15,     // 3rd retry: 900s
        ];
    }

    public function tags(): array
    {
        return [static::class];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Job failed permanently', [
            'job'      => static::class,
            'queue'    => $this->queue ?? 'default',
            'attempts' => $this->attempts(),
            'error'    => $exception->getMessage(),
        ]);
    }
}
```

---

## Step 12 — Dockerignore

File: `.dockerignore`

```
.git
.github
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
.env
.env.backup
.phpunit.result.cache
docker-compose*.yml
Dockerfile
*.md
*.zip
.idea
.vscode
```

---

## Development Setup

### Quick Start

```bash
# 1. Clone the repository
git clone <your-repo-url> my-project
cd my-project

# 2. Create .env
cp .env.example .env

# 3. Generate app key (run in container after build)
docker compose run --rm app php artisan key:generate

# 4. Build and start
docker compose up --build -d

# 5. Run migrations
docker compose exec app php artisan migrate

# 6. (Optional) Seed
docker compose exec app php artisan db:seed

# 7. Access
# App:      http://localhost
# Horizon:  http://localhost/horizon
```

### Stop

```bash
docker compose down          # Stop, keep data
docker compose down -v       # Stop, remove all data
```

---

## Production Setup

### Deploy

```bash
# 1. Clone and configure
git clone <your-repo-url> /opt/my-app
cd /opt/my-app
cp .env.example .env

# 2. Edit .env for production
nano .env
# Set: APP_KEY, APP_ENV=production, APP_DEBUG=false, APP_URL
# Set: DB_PASSWORD, REDIS_PASSWORD, MAIL_*

# 3. Generate app key
docker compose run --rm app php artisan key:generate

# 4. Build and start (production mode)
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# 5. Run migrations
docker compose exec app php artisan migrate --force

# 6. Optimize
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# 7. Verify
docker compose ps
curl http://localhost/health
```

### Update / Redeploy

```bash
git pull origin main

# Rebuild and restart
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# Run new migrations
docker compose exec app php artisan migrate --force

# Restart PHP-FPM to pick up new code (OPcache)
docker compose restart app

# Re-cache config
docker compose exec app php artisan config:cache
```

> **Note:** Since OPcache has `validate_timestamps=0`, you **must** restart PHP-FPM
> after deploying new code: `docker compose restart app`

---

## Common Commands

### Docker

```bash
docker compose up -d                    # Start all services
docker compose down                      # Stop all services
docker compose down -v                   # Stop + remove data
docker compose build --no-cache          # Rebuild from scratch
docker compose logs -f app               # Follow app logs
docker compose logs -f queue             # Follow Horizon logs
docker compose ps                        # Service status
```

### Laravel Artisan

```bash
docker compose exec app php artisan <command>

# Migrations
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:rollback
docker compose exec app php artisan migrate:fresh --seed

# Cache
docker compose exec app php artisan config:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize

# Queue / Horizon
docker compose exec app php artisan horizon:status
docker compose exec app php artisan horizon:pause
docker compose exec app php artisan horizon:continue
docker compose exec app php artisan horizon:terminate
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
docker compose exec app php artisan queue:flush
```

### Database

```bash
docker compose exec postgres psql -U laravel -d laravel
docker compose exec postgres pg_dump -U laravel laravel > backup.sql
cat backup.sql | docker compose exec -T postgres psql -U laravel -d laravel
```

### Redis

```bash
docker compose exec redis redis-cli
docker compose exec redis redis-cli info
docker compose exec redis redis-cli FLUSHALL
```

---

## Scaling

### Horizontal scaling

```bash
# Scale PHP-FPM instances
docker compose up -d --scale app=3

# Scale Horizon workers
docker compose up -d --scale queue=4
```

> **Note:** When scaling `app` beyond 1, Nginx needs load-balancing config:
> ```nginx
> upstream php-fpm {
>     server app_1:9000;
>     server app_2:9000;
>     server app_3:9000;
> }
> ```
> Or use Docker Swarm / Kubernetes for proper orchestration.

### PHP-FPM worker tuning

Edit `docker/php/php.ini` or add to Dockerfile:

```ini
; PHP-FPM pool settings (add to a custom www.conf if needed)
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10
pm.max_requests = 500
```

### PostgreSQL connection tuning

Without PgBouncer, PHP-FPM workers connect directly to PostgreSQL.
Monitor `pg_stat_activity` and adjust `max_connections`:

```sql
-- Check active connections
SELECT count(*) FROM pg_stat_activity;

-- Check max connections
SHOW max_connections;
```

---

## Troubleshooting

### Port 80 already in use

```bash
# Change in .env
APP_PORT=8080
docker compose up -d
```

### PHP-FPM not responding

```bash
docker compose logs app
docker compose restart app
```

### Nginx 502 Bad Gateway

```bash
# PHP-FPM is likely not running or not healthy
docker compose ps app
docker compose logs app
docker compose restart app
```

### Database connection refused

```bash
docker compose ps postgres
docker compose exec postgres pg_isready -U laravel
# Wait ~10s on first start
```

### OPCache showing old code

```bash
# Restart PHP-FPM to clear OPcache
docker compose restart app
```

### Horizon not processing jobs

```bash
docker compose exec app php artisan horizon:status
docker compose logs queue
docker compose restart queue
```

### Permission issues

```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R laravel:laravel storage bootstrap/cache
```

### Reset everything

```bash
docker compose down -v --rmi local
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
```

---

## Key Differences from Octane + RoadRunner Setup

| Aspect | Standard (PHP-FPM) | Octane (RoadRunner) |
|---|---|---|
| PHP Server | PHP-FPM (`php-fpm`) | RoadRunner (`octane:start`) |
| Image | `php:8.3-fpm-alpine` | `php:8.3-cli-alpine` |
| Nginx | `fastcgi_pass app:9000` | `proxy_pass http://app:8000` |
| Workers | Managed by PHP-FPM pool | Managed by RoadRunner |
| Memory | Lower per-request (process dies) | Higher (persistent workers) |
| Performance | Standard | ~2-5x faster (no bootstrap per request) |
| Connection pooler | Not needed (short-lived processes) | PgBouncer recommended |
| OPcache reset | Restart PHP-FPM | `php artisan octane:reload` |
| Complexity | Simple | More complex |
| Best for | Standard apps, teams new to Docker | High-traffic APIs, performance-critical |

---

## License

This guide is provided as-is. Laravel is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).