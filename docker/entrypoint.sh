#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Provide it via environment (php artisan key:generate --show)." >&2
    exit 1
fi

# Runtime, not build time: docker-compose mounts a named volume over
# storage/app, and a mount arrives with its own contents and ownership —
# which masks the Dockerfile's build-time chown. If storage/app/public ends
# up missing or owned by root, php-fpm (www-data) cannot write uploads into
# it and every admin image upload fails. Re-assert both on each boot, after
# the mounts are in place.
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan storage:link --force || true

# Uploaded images are served from storage/app/public through this symlink
# only. Without it they are stored fine and 404 on every page, so say so
# rather than booting quietly into a site with no images.
if [ ! -e public/storage ]; then
    echo "WARNING: public/storage symlink is missing — uploaded images will 404." >&2
fi

# Migrations run only when this start was told to run them. A restart, a
# crash loop or a scale-out never touches the schema. The release process
# runs the migration once, deliberately, and watches its output
# (docs/debt-consolidation/31-DEPLOYMENT-RUNBOOK.md).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "RUN_MIGRATIONS=true: applying pending migrations." >&2
    php artisan migrate --force --isolated
else
    PENDING=$(php artisan migrate:status --pending 2>/dev/null | grep -c "Pending" || true)
    if [ "${PENDING:-0}" -gt 0 ]; then
        echo "REFUSING TO START: ${PENDING} migration(s) pending and RUN_MIGRATIONS is not true." >&2
        echo "Run the release migration step, or go back to the previous image." >&2
        php artisan migrate:status --pending >&2 || true
        exit 1
    fi
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
