#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Provide it via environment (php artisan key:generate --show)." >&2
    exit 1
fi

php artisan storage:link --force || true

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
