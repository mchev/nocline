#!/usr/bin/env bash
# Update a Nocline hub installed from a git clone: fetch the latest release tag (or the one you pass),
# install dependencies, rebuild assets, then let `php artisan nocline:update` back up and migrate.
# Usage: scripts/update.sh [vX.Y.Z]
set -euo pipefail
cd "$(dirname "$0")/.."

command -v git >/dev/null || { echo "git is required." >&2; exit 1; }
[[ -z "$(git status --porcelain --untracked-files=no)" ]] || { echo "Local changes found: commit or stash them first." >&2; exit 1; }

git fetch --tags --quiet
TARGET="${1:-$(git tag --list 'v*' --sort=-v:refname | head -n1)}"
[[ -n "$TARGET" ]] || { echo "No release tag found. Is this a clone of the Nocline repository?" >&2; exit 1; }

echo "Updating to $TARGET..."
git checkout --quiet "$TARGET"

composer install --no-dev --optimize-autoloader --no-interaction
if command -v npm >/dev/null; then
    npm ci --no-audit --no-fund && npm run build
else
    echo "npm not found: using the assets already in public/build."
fi

php artisan nocline:update
