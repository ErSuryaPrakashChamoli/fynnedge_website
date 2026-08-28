---
paths:
  - 'config/database.php,.env*'
  - 'config/cache.php,config/queue.php,config/session.php,.env*'
  - 'config/{cache,queue,session}.php'
---

# Config

## MySQL is the primary DB target; SQLite is a temporary local fallback
Production and the intended dev database is MySQL 8.4 (already running as a system service on this host). Local .env currently stays on sqlite only because MySQL app credentials haven't been provisioned yet — .env.example already documents the mysql connection with placeholders. Switch local .env to mysql as soon as credentials exist; don't design new features around sqlite-only behavior.

## Cache/queue/session are already Redis-ready — switch via env only
This Laravel 13 skeleton ships redis stores in config/cache.php and config/queue.php out of the box, and config/session.php supports SESSION_DRIVER=redis natively. Redis isn't installed on this host yet, so CACHE_STORE/QUEUE_CONNECTION/SESSION_DRIVER stay on `database` for now. When Redis is provisioned, flip those three env vars — no code changes needed.

## Cache/queue/session are Redis-ready via env only — never hardcode a driver
All three already default to the `database` driver and switch to Redis purely via `.env` (CACHE_STORE=redis, QUEUE_CONNECTION=redis, SESSION_DRIVER=redis) — the `redis` client config in config/database.php is already wired. This environment doesn't have Redis installed, so it's never been activated, but no code change is needed to turn it on later. Don't add a Redis-specific code path or hardcode `env('...', 'redis')` — the existing `env('...', 'database')` defaults are correct and intentional.

Verified (2026-08-28, Phase 12): `php artisan config:cache`, `route:cache`, and `view:cache` all build cleanly with no uncacheable closures. N+1 review of public controllers and Filament tables found no gaps — dot-notation Filament table columns auto-eager-load; public controllers already eager-load explicitly (see LoanProductController::show, JourneyController::show, ApplicationController::show). Added composite `(status, created_at)` indexes to `journey_sessions` and `applications` — the two unbounded-growth tables with an active admin status filter/sort; smaller catalog tables (loan_products, lenders, lender_products) were deliberately left unindexed on `status` since they'll only ever hold dozens of rows.
