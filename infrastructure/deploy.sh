#!/bin/sh
# MyCities-Core — Deploy on Linux. Run from infrastructure/:
#   chmod +x deploy.sh && ./deploy.sh
# Creates .env from .env.example if missing, then build + up.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "[OK] Created .env from .env.example — edit infrastructure/.env and set APP_URL (and DB_* for production)."
    else
        echo "ERROR: .env.example not found in infrastructure/"
        exit 1
    fi
fi

echo "Building images..."
docker-compose --env-file .env build --no-cache

echo "Starting stack..."
docker-compose --env-file .env up -d

echo "[OK] MyCities-Core is up."
echo "First time: run migrations and seed admin user:"
echo "  docker exec mycities-core-laravel php artisan migrate --force"
echo "  docker exec mycities-core-laravel php artisan db:seed"
echo "Then open APP_URL in browser, go to /admin/login — login: admin@mycities.co.za / admin888"
