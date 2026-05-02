# Services Marketplace API

A Laravel 12 API-based services marketplace platform. Users can list paid services ("servings"), manage availability schedules, and transact using a wallet-based payment system where time (hours) is the currency unit. Built following Domain-Driven Design (DDD) principles.

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Auth | Laravel Sanctum 4.2 (token-based API) |
| Database | SQLite (dev), configurable for MySQL/PostgreSQL |
| Queue | Database driver (async image cleanup) |
| Testing | PHPUnit 11, Faker, Mockery |
| Linting | Laravel Pint |
| Build | Vite |

## Architecture

Clean layered architecture following DDD patterns:

```
Presentation/     --> HTTP layer: Controllers, Middleware, Form Requests
      |
Application/      --> Application Services: Business orchestration, transaction management
      |
Domain/           --> Interfaces: Repository contracts, Service contracts
      ^
Infrastructure/   --> Implementations: Eloquent Models, Repositories, Event Listeners
```

**Dependency flow**: Controllers depend on Domain interfaces -> Application services depend on Repository interfaces -> Infrastructure provides concrete implementations -> IoC container wires everything in `AppServiceProvider`.

## Database Schema

16 tables organized around these core entities:

```
Users --1:*-- Wallets --*:1-- PaymentUnits
  |
  +--1:*-- Servings --*:1-- ServingCategories (hierarchical)
              |
              +-- *:1-- ServingTypes
              +-- *:1-- PaymentUnits
              +-- 1:*-- ServingAvailabilitySlots
```

Key tables:
- **users** - Authentication, profile info, role-based access (`user`/`admin`)
- **wallets** - User wallets with balance in payment units (default: 2 hours on registration)
- **servings** - Service listings with title, description, cost, location, image
- **serving_categories** - Hierarchical categories (parent/children)
- **serving_availability_slots** - Recurring (day-of-week) or specific-date scheduling
- **email_verification_attempts** - OTP tracking with expiry

## Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & npm

### Installation

```bash
# Full setup: install deps, create .env, generate key, migrate, build assets
composer run setup

# Or manually:
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Development

```bash
# Start dev server (with queue, logs, and Vite hot reload)
composer run dev

# Run tests
composer run test

# Format code with Pint
./vendor/bin/pint

# Format code (dry run)
./vendor/bin/pint --test
```

## API Endpoints

### Authentication (no auth required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/send-otp` | Send OTP verification email |
| POST | `/api/auth/register-customer` | Register with OTP verification |
| POST | `/api/auth/login` | Login (returns Sanctum token) |

### Servings (requires `auth:sanctum` + `user` role)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/servings/add-paid` | Create a paid serving listing |
| PUT | `/api/servings/add-paid/{id}` | Update a paid serving listing |

### Debug (development only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/test-monitor` | Test AOP request monitoring |
| GET | `/api/test-error` | Test error logging |

All responses follow the JSON envelope: `{ "success": bool, "data"?: mixed, "message"?: string }`

## Key Patterns

| Pattern | Implementation |
|---------|----------------|
| Repository | `Domain/Repositories/*Interface` -> `Infrastructure/Repositories/*` |
| Service Layer | `Domain/Services/*Interface` -> `Application/Services/*` |
| Form Requests | All input validated via dedicated `Presentation/Requests/*` classes |
| Transactions | `Traits/HandlesDatabaseTransactions` wraps writes atomically |
| AOP Logging | `Traits/Loggable` + `Events/MethodExecuted` + `Infrastructure/Listeners/LogMethodExecutionHandler` |
| Queued Jobs | `Jobs/DeleteServingImageJob` for async image cleanup |
| Role Middleware | `EnsureUserRole` / `EnsureAdminRole` for access control |
| OTP Flow | Send email -> Store attempt (10-min expiry) -> Verify on registration -> Mark used |

## Project Notes

- **Localization**: Syrian phone number validation (`+963` format), Arabic wallet title ("Raseed al-Sa'at")
- **Image uploads**: Max 5MB, stored in `storage/app/public/servings`
- **Security**: Old images safely deleted via queued job (checks no serving still references URL before deleting)
- **Health check**: Available at `/up`
