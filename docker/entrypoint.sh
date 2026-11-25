#!/bin/sh
set -e

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="$(php artisan key:generate --show)"
fi

php artisan package:discover --ansi 2>/dev/null || true

wait_for_database() {
    php artisan migrate --force --no-interaction 2>/dev/null && return 0
    i=0
    while [ "$i" -lt 60 ]; do
        if php artisan migrate --force --no-interaction; then
            return 0
        fi
        i=$((i + 1))
        sleep 2
    done
    echo "Database not ready after 120s" >&2
    exit 1
}

maybe_auto_install() {
    if [ "${NOCLINE_AUTO_INSTALL:-false}" != "true" ]; then
        return 0
    fi

    if [ -f storage/app/nocline-installed ]; then
        return 0
    fi

    : "${NOCLINE_INSTALL_ADMIN_EMAIL:?NOCLINE_INSTALL_ADMIN_EMAIL required when NOCLINE_AUTO_INSTALL=true}"
    : "${NOCLINE_INSTALL_ADMIN_PASSWORD:?NOCLINE_INSTALL_ADMIN_PASSWORD required}"
    : "${NOCLINE_INSTALL_URL:=http://localhost:8080}"

    php artisan nocline:install \
        --db="${DB_CONNECTION:-pgsql}" \
        --db-host="${DB_HOST:-postgres}" \
        --db-port="${DB_PORT:-5432}" \
        --db-name="${DB_DATABASE:-nocline}" \
        --db-user="${DB_USERNAME:-nocline}" \
        --db-password="${DB_PASSWORD:-nocline}" \
        --admin-name="${NOCLINE_INSTALL_ADMIN_NAME:-Admin}" \
        --admin-email="${NOCLINE_INSTALL_ADMIN_EMAIL}" \
        --admin-password="${NOCLINE_INSTALL_ADMIN_PASSWORD}" \
        --name="${NOCLINE_INSTALL_INSTANCE_NAME:-Nocline}" \
        --url="${NOCLINE_INSTALL_URL}" \
        --locale="${NOCLINE_INSTALL_LOCALE:-en}" \
        --timezone="${NOCLINE_INSTALL_TIMEZONE:-UTC}"
}

ROLE="${1:-web}"
shift || true

wait_for_database
maybe_auto_install

php artisan config:cache
php artisan route:cache
php artisan view:cache

case "$ROLE" in
    web)
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    worker)
        exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    *)
        exec "$ROLE" "$@"
        ;;
esac