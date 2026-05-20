# JSON API Spec — Laravel 13

A Laravel 13 application following the JSON:API specification, running on **PostgreSQL**, **Redis**, and **Laravel Horizon** — containerized with Docker.

---

## Architecture

### Development (local Laravel + Docker infrastructure)

```
┌──────────────────────────────────────────────────┐
│  Your WSL Machine                                │
│                                                  │
│  Terminal 1:  php artisan serve  (localhost:8000) │
│  Terminal 2:  npm run dev        (Vite HMR)      │
│                                                  │
│  Docker:                                         │
│  ┌──────────┐  ┌─────────┐  ┌──────────┐        │
│  │PostgreSQL │  │  Redis  │  │ Horizon  │        │
│  │ :5432     │  │ :6379   │  │ Queue    │        │
│  └──────────┘  └─────────┘  └──────────┘        │
│                  ┌──────────┐ ┌──────────┐       │
│                  │Scheduler │ │   Pail   │       │
│                  └──────────┘ └──────────┘       │
└──────────────────────────────────────────────────┘
```

### Production (full Docker stack)

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
              │   PHP-FPM (x N workers) │  ← Laravel application
              └──┬──────────┬──────────┬┘
                 │          │          │
           ┌─────▼───┐ ┌───▼────┐ ┌──▼──────┐
           │PostgreSQL│ │ Redis  │ │ Horizon │
           │  :5432   │ │ :6379  │ │ Worker  │
           └──────────┘ └────────┘ └─────────┘
```

---

## Prerequisites

### Development
- **PHP 8.4** installed in WSL
- **Composer** installed in WSL
- **Node.js 20+** installed in WSL
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) with WSL 2 backend

### Production
- Docker only

---

## Quick Start (Development)

> **Dev runs Laravel locally.** Docker provides only PostgreSQL, Redis, Horizon queue, and scheduler.

### 1. Start Docker infrastructure

```bash
# Must be inside WSL
cd /home/dharmasaputraa/projects/learn-laravel/json-api-spec

# Start postgres, redis, queue (horizon), scheduler, pail
docker compose up -d
```

### 2. Setup Laravel

```bash
# Create .env
cp .env.example .env

# Install PHP dependencies
composer install

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate
```

### 3. Run Laravel locally

```bash
# Terminal 1 — Laravel dev server
php artisan serve

# Terminal 2 — Vite (optional, for frontend hot reload)
npm run dev
```

### 4. Access

| Service | URL |
|---|---|
| **App** | http://localhost:8000 |
| **Vite** | http://localhost:5173 (auto-injected by Vite) |

### Tailing Logs

```bash
docker compose logs -f pail    # Live application logs (php artisan pail)
```

### Stop

```bash
docker compose down          # Stop, keep data
docker compose down -v       # Stop, remove all data
```

---

## Production Setup

### 1. Clone and configure

```bash
git clone <your-repo-url> /opt/json-api-spec
cd /opt/json-api-spec
cp .env.example .env
```

### 2. Edit `.env` for production

```bash
nano .env
```

Set these values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
DB_HOST=postgres          # Docker internal hostname
REDIS_HOST=redis          # Docker internal hostname
DB_PASSWORD=your-strong-password
REDIS_PASSWORD=your-redis-password
```

### 3. Generate app key

```bash
docker compose run --rm app php artisan key:generate
```

### 4. Build and start (production mode — full stack)

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

### 5. Run migrations

```bash
docker compose exec app php artisan migrate --force
```

### 6. Optimize

```bash
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

### 7. Verify

```bash
docker compose ps
curl http://localhost/health
```

| Service | URL |
|---|---|
| **App** | http://localhost (port 80) |
| **Horizon** | http://localhost/horizon |
| **Health** | http://localhost/health |

### Redeploy (after code changes)

```bash
git pull origin main

# Rebuild and restart
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# Run new migrations
docker compose exec app php artisan migrate --force

# Restart PHP-FPM to clear OPcache
docker compose restart app

# Re-cache config
docker compose exec app php artisan config:cache
```

---

## Common Commands

### Docker

```bash
docker compose up -d                    # Start infrastructure
docker compose down                      # Stop infrastructure
docker compose down -v                   # Stop + remove all data
docker compose build --no-cache          # Rebuild from scratch
docker compose logs -f queue             # Follow Horizon logs
docker compose logs -f pail              # Follow application logs (pail)
docker compose ps                        # Service status
```

### Laravel Artisan (dev — run locally)

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan config:clear
php artisan cache:clear
php artisan queue:failed
php artisan queue:retry all
```

### Laravel Artisan (prod — run in container)

```bash
docker compose exec app php artisan <command>
```

### Database

```bash
# Connect to PostgreSQL
docker compose exec postgres psql -U laravel -d json_api_spec

# Backup
docker compose exec postgres pg_dump -U laravel json_api_spec > backup.sql

# Restore
cat backup.sql | docker compose exec -T postgres psql -U laravel -d json_api_spec
```

### Redis

```bash
docker compose exec redis redis-cli
docker compose exec redis redis-cli info
docker compose exec redis redis-cli FLUSHALL
```

---

## Troubleshooting

### Port already in use

```bash
# Change in .env
DB_PORT=5433
REDIS_PORT=6380
docker compose up -d
```

### Database connection refused

```bash
docker compose ps postgres
docker compose exec postgres pg_isready -U laravel
```

### Horizon not processing jobs

```bash
docker compose logs queue
docker compose restart queue
```

### Permission issues

```bash
chmod -R 775 storage bootstrap/cache
```

### Reset everything

```bash
docker compose down -v --rmi local
docker compose up --build -d
php artisan migrate:fresh --seed
```

---

## Tech Stack

- **Laravel** 13 (PHP 8.4)
- **PostgreSQL** 16
- **Redis** 7
- **Nginx** 1.27 (production only)
- **Laravel Horizon** 5.x
- **Vite** for frontend assets