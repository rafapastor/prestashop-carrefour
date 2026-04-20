#!/usr/bin/env bash
#
# Dumps a concise summary of the module state from the running PS database.
# Useful when debugging: paste the output into a bug report.
#
# Usage:  scripts/dump-state.sh
#
set -euo pipefail

MYSQL="docker exec carrefour_db mysql -uroot -padmin -Dprestashop -e"

echo "=== ps_carrefour_shop_config ==="
$MYSQL "SELECT id_shop, api_endpoint, sandbox_mode, shop_id_mirakl, stock_sync_enabled, webhook_enabled, log_level FROM ps_carrefour_shop_config" 2>/dev/null || echo "(table missing — module not installed)"

echo ""
echo "=== ps_carrefour_listing (counts) ==="
$MYSQL "SELECT id_shop, status, COUNT(*) AS count FROM ps_carrefour_listing GROUP BY id_shop, status" 2>/dev/null || echo "(empty or table missing)"

echo ""
echo "=== ps_carrefour_offer (by status) ==="
$MYSQL "SELECT id_shop, status, COUNT(*) AS count FROM ps_carrefour_offer GROUP BY id_shop, status" 2>/dev/null || echo "(empty or table missing)"

echo ""
echo "=== ps_carrefour_job (by status) ==="
$MYSQL "SELECT id_shop, type, status, COUNT(*) AS count FROM ps_carrefour_job GROUP BY id_shop, type, status ORDER BY id_shop, type" 2>/dev/null || echo "(empty or table missing)"

echo ""
echo "=== ps_carrefour_order (by state) ==="
$MYSQL "SELECT id_shop, state, COUNT(*) AS count FROM ps_carrefour_order GROUP BY id_shop, state" 2>/dev/null || echo "(empty or table missing)"

echo ""
echo "=== Latest 10 log entries ==="
$MYSQL "SELECT date_add, level, channel, LEFT(message, 120) AS message FROM ps_carrefour_log ORDER BY id_carrefour_log DESC LIMIT 10" 2>/dev/null || echo "(empty or table missing)"
