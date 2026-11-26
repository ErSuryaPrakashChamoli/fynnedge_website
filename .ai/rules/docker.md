---
paths:
  - docker/entrypoint.sh
---

# Docker

## Migrations run only when a start sets RUN_MIGRATIONS=true
Since 2026-11-26 (Owner approval, INF-01) the entrypoint migrates only when the container is started with RUN_MIGRATIONS=true (`migrate --force --isolated`). Without it, a start with pending migrations REFUSES to boot and lists them, so new code never runs on an old schema and an unplanned restart never changes the database. Release: back up, run a one-off `docker compose run --rm -e RUN_MIGRATIONS=true app true`, then start normally (docs/debt-consolidation/31-DEPLOYMENT-RUNBOOK.md). A fresh empty database needs RUN_MIGRATIONS=true on its first start (no migrations table yet, so the pending check finds nothing). config:cache, route:cache and view:cache still run on every start.

To roll a release back, run `migrate:rollback` while the NEW image is still deployed, and only then deploy the previous image: the old code does not have the newer migrations' down() methods.

The image's supervisor runs php-fpm, nginx and `queue:work` but no scheduler. `schedule:run` (newsletter, CRIF/Equifax/Experian, debt-consolidation retention) must run outside this image.

Never run config:cache in a local working copy while tests run: a cached config makes the tests ignore phpunit.xml's SQLite settings.
