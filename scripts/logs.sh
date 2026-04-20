#!/usr/bin/env bash
#
# Tail the module's file-based logs inside the PS container.
# Useful for watching warnings/errors in real time without going through the admin UI.
#
# Usage:  scripts/logs.sh
#
set -euo pipefail

LOG_DIR="/var/www/html/modules/carrefourmarketplace/logs"

echo "Tailing $LOG_DIR/ (Ctrl+C to stop)..."
docker exec -it carrefour_ps sh -c "mkdir -p $LOG_DIR && tail -F $LOG_DIR/*.log 2>/dev/null || echo '(no log files yet — warnings or errors trigger log file creation)'"
