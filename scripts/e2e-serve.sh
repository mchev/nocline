#!/usr/bin/env bash
# Isolated Phare instance for manual / browser end-to-end testing.
# Never touches your real .env, database or install marker: everything lives in /tmp/phare-e2e.
# Usage: scripts/e2e-serve.sh [port] [--fresh]   (fresh = forget the previous install)
set -euo pipefail

PORT="${1:-8099}"
DIR=/tmp/phare-e2e
mkdir -p "$DIR"

if [[ "${2:-}" == "--fresh" ]]; then
    rm -f "$DIR/db.sqlite" "$DIR/installed"
    cp "$(dirname "$0")/../.env.example" "$DIR/.env"
fi
[[ -f "$DIR/.env" ]] || cp "$(dirname "$0")/../.env.example" "$DIR/.env"

echo "Phare e2e instance on http://127.0.0.1:$PORT (data in $DIR)."
echo "In the setup wizard, set the SQLite file to $DIR/db.sqlite (the default points at your real database)."

cd "$(dirname "$0")/../public"
exec env \
    APP_KEY="base64:$(openssl rand -base64 32)" VAULT_MASTER_KEY="$(openssl rand -base64 32)" \
    DB_CONNECTION=sqlite DB_DATABASE="$DIR/db.sqlite" \
    PHARE_ENV_PATH="$DIR/.env" PHARE_INSTALLED_MARKER="$DIR/installed" PHARE_FORCE_INSTALLED=false \
    APP_ENV=local APP_DEBUG=true APP_LOCALE=en APP_URL="http://127.0.0.1:$PORT" \
    SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync MAIL_MAILER=log PHP_CLI_SERVER_WORKERS=4 \
    php -S "127.0.0.1:$PORT" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
