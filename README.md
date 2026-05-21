# Laravel 13 JSON:API

A Laravel 13 learning project that explores and demonstrates different strategies for building JSON:API v1.0 spec-compliant REST APIs. The project implements a blog-like domain with full CRUD operations and compares multiple approaches for resource transformation and query filtering.

## Features

- JSON:API v1.0 spec-compliant response structure
- Multiple resource transformation approaches (JsonApiResource, Manual, Inline)
- Four query filtering strategies (Eloquent, Query Builder, Raw SQL, Spatie)
- Auto-generated API documentation via Scramble
- Queue management with Laravel Horizon
- Real-time log tailing with Laravel Pail
- Dockerized development and production environments
- Post filtering benchmarks for strategy comparison

## Tech Stack

| Component        | Technology                         |
| ---------------- | ---------------------------------- |
| Framework        | Laravel 13                         |
| Language         | PHP 8.3                            |
| Database         | PostgreSQL 16                      |
| Cache / Queue    | Redis 7                            |
| Test Framework   | Pest 4                             |
| API Docs         | Scramble (OpenAPI auto-generation) |
| Query Builder    | Spatie Laravel Query Builder 7     |
| Code Style       | Laravel Pint                       |
| Frontend         | Tailwind CSS 4, Vite 8             |
| Containerization | Docker, Docker Compose             |

## Prerequisites

- PHP 8.3+
- Composer 2
- Node.js 18+ and npm
- Docker and Docker Compose (for containerized services)
- Git

## Getting Started

### 1. Clone the Repository

```bash
git clone <repository-url>
cd json-api-spec
```

### 2. Environment Configuration

```bash
cp .env.example .env
```

Review and update the `.env` file with your local database and Redis credentials. The default values are configured for the Docker setup.

### 3. Start Infrastructure Services

```bash
docker compose up -d
```

This starts PostgreSQL, Redis, and supporting services.

### 4. Install Dependencies

```bash
composer install
npm install
```

### 5. Application Key

```bash
php artisan key:generate
```

### 6. Database Setup

```bash
php artisan migrate
php artisan db:seed
```

### 7. Start the Development Server

```bash
php artisan serve
```

In a separate terminal, start the frontend asset watcher:

```bash
npm run dev
```

The application will be available at `http://localhost:8000`.

## API Documentation

API documentation is auto-generated using Scramble and is available at:

```
http://localhost:8000/docs/api
```

The documentation covers all available endpoints, request/response schemas, and query parameters compliant with the JSON:API specification.

## Architecture Overview

### Resource Transformation Approaches

The project demonstrates three approaches for building JSON:API responses:

- **JsonApiResource** -- A dedicated base class that encapsulates the JSON:API structure. Recommended for consistency and reuse.
- **Manual** -- Explicit construction of the JSON:API response array in each resource class. Useful for understanding the spec internals.
- **Inline** -- Direct inline array construction in controllers (anti-pattern, shown for comparison only).

### Query Filtering Strategies

Four strategies are available for filtering API resources, each with trade-offs:

| Strategy      | Description                                                      |
| ------------- | ---------------------------------------------------------------- |
| Eloquent      | Uses Eloquent ORM with scopes and where clauses                  |
| Query Builder | Uses Laravel's database query builder for more control           |
| Raw SQL       | Direct SQL queries for maximum performance and flexibility       |
| Spatie        | Declarative filtering via `spatie/laravel-query-builder` package |

A benchmarking tool is available to compare the performance of these strategies:

```bash
php artisan benchmark:read-posts --strategy=eloquent --iterations=100
```

Valid strategy options: `eloquent`, `query-builder`, `raw`, `spatie`.

### Domain Models

- **Post** -- Blog posts with title, content, status (draft/published), and view count
- **Category** -- Post categories with hierarchical support
- **Tag** -- Tags for posts with many-to-many relationships
- **Comment** -- Comments on posts
- **Author** -- Post authors

## Docker Services

The development Docker Compose setup provides the following services:

| Service   | Purpose                                    | Port |
| --------- | ------------------------------------------ | ---- |
| postgres  | PostgreSQL 16 database                     | 5432 |
| redis     | Redis cache and queue backend              | 6379 |
| queue     | Laravel Horizon (queue worker + dashboard) | --   |
| scheduler | Laravel scheduler (`schedule:work`)        | --   |
| pail      | Real-time log tailing (`pail --timeout=0`) | --   |

### Production Deployment

For production, use the production overlay:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

This adds the PHP-FPM application container and an Nginx reverse proxy with SSL support.

## Testing

The project uses Pest as its test framework. Run the test suite with:

```bash
# Run all tests
php artisan test

# Run with Pest directly
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage
```

## Code Style

Laravel Pint is used for code style enforcement:

```bash
# Check for style issues
vendor/bin/pint --test

# Fix style issues
vendor/bin/pint
```

## Project Structure

```
json-api-spec/
├── app/
│   ├── Console/          # Artisan commands (benchmarks)
│   ├── Enums/            # PHP enums (PostStatus, etc.)
│   ├── Exceptions/       # Exception handler
│   ├── Http/
│   │   ├── Controllers/  # API controllers
│   │   ├── Middleware/    # Custom middleware
│   │   ├── Requests/     # Form request validation
│   │   └── Resources/    # JSON:API resource classes
│   ├── Jobs/             # Queued jobs (with BaseJob pattern)
│   ├── Models/           # Eloquent models
│   ├── Providers/        # Service providers
│   └── Services/         # Business logic services
├── config/               # Application configuration
├── database/
│   ├── migrations/       # Database schema migrations
│   └── seeders/          # Database seeders
├── dev-docs/             # Development documentation and references
│   ├── API_SETUP_GUIDE.md
│   ├── LARAVEL-STANDARD-SETUP.md
│   ├── benchmark/
│   └── post-filtering/
├── docker/               # Docker configuration files
├── public/               # Web root
├── resources/            # Views and frontend assets
├── routes/
│   ├── api.php           # API routes
│   ├── console.php       # Artisan command definitions
│   └── web.php           # Web routes
├── tests/                # Pest test files
├── docker-compose.yml        # Development Docker setup
├── docker-compose.prod.yml   # Production Docker overlay
├── Dockerfile                # Multi-stage PHP-FPM image
└── vite.config.js            # Vite frontend build config
```

## Documentation

Additional development documentation is available in the `dev-docs/` directory:

- **API_SETUP_GUIDE.md** -- Guide for setting up new JSON:API resources and Scramble documentation
- **LARAVEL-STANDARD-SETUP.md** -- Complete Docker setup reference for Laravel 13
- **benchmark/** -- Read posts benchmark documentation
- **post-filtering/** -- Reference implementation for the four filtering strategies
