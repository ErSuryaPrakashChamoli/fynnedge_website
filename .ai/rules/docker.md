---
paths:
  - docker/entrypoint.sh
---

# Docker

## Starting the production container runs migrate --force: deploying is migrating
On every start the entrypoint runs `migrate --force`, then `config:cache`, `route:cache` and `view:cache`. Any new migration therefore runs in production the moment the new image starts, so back up before the container starts, not after.

To roll a release back, run `migrate:rollback` while the NEW image is still deployed, and only then deploy the previous image: the old code does not have the newer migrations' down() methods.

The image's supervisor runs php-fpm, nginx and `queue:work` but no scheduler. `schedule:run` (newsletter, CRIF/Equifax/Experian, debt-consolidation retention) must run outside this image.

Never run config:cache in a local working copy while tests run: a cached config makes the tests ignore phpunit.xml's SQLite settings.
