#!/usr/bin/env bash
#
# Wipe the local Docker PrestaShop install and start fresh.
# Destroys the DB volume; module files on disk are untouched.
#
# Usage:  scripts/dev-reset.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Stopping containers and removing volumes..."
docker compose down -v

echo "==> Starting fresh PrestaShop..."
docker compose up -d

echo "==> Waiting for PrestaShop to become responsive..."
for i in $(seq 1 60); do
    if curl -sSfL -o /dev/null http://localhost:8081/; then
        echo "==> PrestaShop is up."
        break
    fi
    sleep 2
done

echo "==> Renaming /admin to /admindev (PS security requirement for browser access)..."
docker exec carrefour_ps sh -c 'test -d /var/www/html/admin && mv /var/www/html/admin /var/www/html/admindev' || true

echo ""
echo "Done."
echo "  Front:   http://localhost:8081"
echo "  Admin:   http://localhost:8081/admindev  (admin@prestashop.com / prestashop_demo)"
