---
paths:
  - 'config/database.php,.env*'
  - 'config/cache.php,config/queue.php,config/session.php,.env*'
---

# Config

## MySQL is the primary DB target; SQLite is a temporary local fallback
Production and the intended dev database is MySQL 8.4 (already running as a system service on this host). Local .env currently stays on sqlite only because MySQL app credentials haven't been provisioned yet — .env.example already documents the mysql connection with placeholders. Switch local .env to mysql as soon as credentials exist; don't design new features around sqlite-only behavior.

## Cache/queue/session are already Redis-ready — switch via env only
This Laravel 13 skeleton ships redis stores in config/cache.php and config/queue.php out of the box, and config/session.php supports SESSION_DRIVER=redis natively. Redis isn't installed on this host yet, so CACHE_STORE/QUEUE_CONNECTION/SESSION_DRIVER stay on `database` for now. When Redis is provisioned, flip those three env vars — no code changes needed.
