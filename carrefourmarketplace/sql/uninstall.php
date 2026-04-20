<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$prefix = _DB_PREFIX_;

$tables = [
    'carrefour_log',
    'carrefour_category_mapping',
    'carrefour_job',
    'carrefour_order_line',
    'carrefour_order',
    'carrefour_offer',
    'carrefour_listing',
    'carrefour_shop_config',
];

foreach ($tables as $table) {
    if (Db::getInstance()->execute('DROP TABLE IF EXISTS `' . $prefix . $table . '`') == false) {
        return false;
    }
}
