<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Trim carrefour_log rows older than each shop's log_retention_days.
 *
 * Usage: php modules/carrefourmarketplace/cron/logs-cleanup.php
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

$rows = Db::getInstance()->executeS(
    'SELECT `id_shop`, `log_retention_days` FROM `' . _DB_PREFIX_ . 'carrefour_shop_config`'
);
$deletedTotal = 0;
if (is_array($rows)) {
    foreach ($rows as $r) {
        $idShop = (int) $r['id_shop'];
        $days = max(1, (int) $r['log_retention_days']);
        $sql = sprintf(
            'DELETE FROM `%scarrefour_log` WHERE `id_shop` = %d AND `date_add` < DATE_SUB(NOW(), INTERVAL %d DAY)',
            _DB_PREFIX_,
            $idShop,
            $days
        );
        Db::getInstance()->execute($sql);
        $deletedTotal += (int) Db::getInstance()->Affected_Rows();
    }
}
fwrite(STDOUT, sprintf("[carrefour-logs-cleanup] deleted=%d\n", $deletedTotal));
exit(0);
