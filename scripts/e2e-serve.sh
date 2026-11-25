#!/usr/bin/env bash
# Isolated Nocline instance for manual / browser end-to-end testing.
# Never touches your real .env, database or install marker: everything lives in /tmp/nocline-e2e.
# Usage: scripts/e2e-serve.sh [port] [--fresh]   (fresh = forget the previous install)
set -euo pipefail

PORT="${1:-8099}"
DIR=/tmp/nocline-e2e
mkdir -p "$DIR"

if [[ "${2:-}" == "--fresh" ]]; then
    rm -f "$DIR/db.sqlite" "$DIR/installed"
    cp "$(dirname "$0")/../.env.example" "$DIR/.env"
fi
[[ -f "$DIR/.env" ]] || cp "$(dirname "$0")/../.env.example" "$DIR/.env"

echo "Nocline e2e instance on http://127.0.0.1:$PORT (data in $DIR)."
echo "In the setup wizard, set the SQLite file to $DIR/db.sqlite (the default points at your real database)."

# Keep APP_KEY stable across restarts so browser sessions survive (installer writes $DIR/.env).
APP_KEY_FROM_FILE="$(grep -E '^APP_KEY=' "$DIR/.env" 2>/dev/null | head -1 | cut -d= -f2-)"
if [[ -z "$APP_KEY_FROM_FILE" ]]; then
    APP_KEY_FROM_FILE="base64:$(openssl rand -base64 32)"
fi

cd "$(dirname "$0")/../public"
exec env \
    APP_KEY="$APP_KEY_FROM_FILE" VAULT_MASTER_KEY="$(openssl rand -base64 32)" \
    DB_CONNECTION=sqlite DB_DATABASE="$DIR/db.sqlite" \
    NOCLINE_ENV_PATH="$DIR/.env" NOCLINE_INSTALLED_MARKER="$DIR/installed" NOCLINE_FORCE_INSTALLED=false \
    APP_ENV=local APP_DEBUG=false APP_LOCALE=en APP_URL="http://127.0.0.1:$PORT" \
    SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync MAIL_MAILER=log \
    php -d display_errors=0 -S "127.0.0.1:$PORT" ../scripts/e2e-server-router.php
