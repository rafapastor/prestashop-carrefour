#!/usr/bin/env bash
#
# Run the job worker inside the PS container for quick manual testing without cron.
#
# Usage:  scripts/run-worker.sh [--max-jobs=10] [--max-seconds=30]
#
set -euo pipefail

docker exec carrefour_ps php /var/www/html/modules/carrefourmarketplace/cron/worker.php --verbose "$@"
