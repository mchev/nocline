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
    if [ "${PHARE_AUTO_INSTALL:-false}" != "true" ]; then
        return 0
    fi

    if [ -f storage/app/phare-installed ]; then
        return 0
    fi

    : "${PHARE_INSTALL_ADMIN_EMAIL:?PHARE_INSTALL_ADMIN_EMAIL required when PHARE_AUTO_INSTALL=true}"
    : "${PHARE_INSTALL_ADMIN_PASSWORD:?PHARE_INSTALL_ADMIN_PASSWORD required}"
    : "${PHARE_INSTALL_URL:=http://localhost:8080}"

    php artisan phare:install \
        --db="${DB_CONNECTION:-pgsql}" \
        --db-host="${DB_HOST:-postgres}" \
        --db-port="${DB_PORT:-5432}" \
        --db-name="${DB_DATABASE:-phare}" \
        --db-user="${DB_USERNAME:-phare}" \
        --db-password="${DB_PASSWORD:-phare}" \
        --admin-name="${PHARE_INSTALL_ADMIN_NAME:-Admin}" \
        --admin-email="${PHARE_INSTALL_ADMIN_EMAIL}" \
        --admin-password="${PHARE_INSTALL_ADMIN_PASSWORD}" \
        --name="${PHARE_INSTALL_INSTANCE_NAME:-Phare}" \
        --url="${PHARE_INSTALL_URL}" \
        --locale="${PHARE_INSTALL_LOCALE:-en}" \
        --timezone="${PHARE_INSTALL_TIMEZONE:-UTC}"
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