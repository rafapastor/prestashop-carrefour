<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * CLI entry: enqueue an order_sync job for each configured shop.
 * Useful when the main worker cron is not running but you still want a scheduled pull.
 *
 * Usage (from PS root):
 *   php modules/carrefourmarketplace/cron/orders-sync.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$psRoot = realpath(dirname(__FILE__) . '/../../..');
if ($psRoot === false || !is_file($psRoot . '/config/config.inc.php')) {
    fwrite(STDERR, "Cannot locate PrestaShop config.inc.php\n");
    exit(1);
}
require_once $psRoot . '/config/config.inc.php';
require_once $psRoot . '/modules/carrefourmarketplace/carrefourmarketplace.php';

$shopIds = Db::getInstance()->executeS(
    'SELECT DISTINCT `id_shop` FROM `' . _DB_PREFIX_ . 'carrefour_shop_config`'
);
$enqueued = 0;
if (is_array($shopIds)) {
    foreach ($shopIds as $row) {
        $idShop = (int) $row['id_shop'];

        try {
            $queue = new CarrefourJobQueue($idShop);
            $queue->enqueue('order_sync', []);
            ++$enqueued;
        } catch (\Exception $e) {
            fwrite(STDERR, sprintf("[carrefour-order-sync] shop %d failed: %s\n", $idShop, $e->getMessage()));
        }
    }
}
fwrite(STDOUT, sprintf("[carrefour-order-sync] enqueued=%d\n", $enqueued));
exit(0);
