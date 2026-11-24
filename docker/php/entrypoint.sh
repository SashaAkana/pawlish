#!/bin/sh
set -eu
cd /var/www/html
if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY is required. Generate a key and store it in src/.env.' >&2
    exit 1
fi
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
if [ "${1:-}" = php-fpm ]; then
    rm -f /tmp/pawlish-ready
    mkdir -p database public
    touch database/database.sqlite
    chown -R www-data:www-data database
    # Keep Nginx and PHP on the same release of the static assets.
    cp -a /opt/pawlish-public/. public/
    su-exec www-data php artisan config:clear
    if [ "${RUN_MIGRATIONS:-1}" = 1 ]; then
        su-exec www-data php artisan migrate --force
    fi
    # A cache backed by SQLite also requires completed migrations.
    su-exec www-data php artisan package:discover --ansi
    touch /tmp/pawlish-ready
    exec "$@"
fi
exec su-exec www-data "$@"
