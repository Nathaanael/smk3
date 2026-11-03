#!/usr/bin/env bash
# Update the production server to the latest `main`.
# Usage (on the server, inside the project folder): bash deploy.sh
#
# The whole script is wrapped in main() so bash reads it completely before running:
# `git pull` may change this file, and bash would otherwise keep reading the new version mid-run.

main() {
    set -euo pipefail
    cd "$(dirname "$0")"

    if [ ! -f .env ]; then
        echo "ERROR: .env not found. Copy .env.example to .env and fill in the production values." >&2
        exit 1
    fi

    echo "==> Pulling latest code"
    git pull company main

    echo "==> Installing PHP dependencies"
    composer install --no-dev --optimize-autoloader --no-interaction

    echo "==> Building CSS/JS"
    npm ci
    npm run build

    echo "==> Clearing caches"
    php artisan optimize:clear

    echo "==> Done"
}

main "$@"
