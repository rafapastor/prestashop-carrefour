<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Install schema for carrefourmarketplace v1.0.0.
 * See memory-bank/implementationPlan.md for the canonical schema description.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];
$prefix = _DB_PREFIX_;
$engine = _MYSQL_ENGINE_;
$charset = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

/* Per-shop configuration */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_shop_config` (
    `id_carrefour_shop_config` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT UNSIGNED NOT NULL,
    `api_endpoint` VARCHAR(255) NOT NULL DEFAULT \'https://carrefoures-prod.mirakl.net/api\',
    `api_key_encrypted` VARCHAR(2048) DEFAULT NULL,
    `sandbox_mode` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `shop_id_mirakl` VARCHAR(50) DEFAULT NULL,
    `auto_accept_orders` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `default_order_state_id` INT UNSIGNED DEFAULT NULL,
    `stock_sync_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `stock_sync_debounce_seconds` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `price_sync_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `order_sync_interval_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    `webhook_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `webhook_secret` VARCHAR(64) DEFAULT NULL,
    `sku_strategy` VARCHAR(50) NOT NULL DEFAULT \'attribute_ref_fallback_product\',
    `log_level` VARCHAR(10) NOT NULL DEFAULT \'info\',
    `log_retention_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_shop_config`),
    UNIQUE KEY `uk_id_shop` (`id_shop`)
) ENGINE=' . $engine . ' ' . $charset . ';';

/* Listing = sync profile (set of products + rules) */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_listing` (
    `id_carrefour_listing` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT UNSIGNED NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT \'active\',
    `category_mapping_mode` VARCHAR(30) NOT NULL DEFAULT \'category_mapping\',
    `category_mapping_value` VARCHAR(100) DEFAULT NULL,
    `price_mode` VARCHAR(20) NOT NULL DEFAULT \'product\',
    `price_variation_operator` VARCHAR(20) NOT NULL DEFAULT \'none\',
    `price_variation_value` DECIMAL(10,2) DEFAULT NULL,
    `stock_mode` VARCHAR(20) NOT NULL DEFAULT \'product\',
    `stock_custom_value` INT UNSIGNED DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_listing`),
    KEY `idx_id_shop` (`id_shop`)
) ENGINE=' . $engine . ' ' . $charset . ';';

/* Offer = one row per (shop, PS product + attribute) tracked against Mirakl */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_offer` (
    `id_carrefour_offer` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT UNSIGNED NOT NULL,
    `id_carrefour_listing` INT UNSIGNED DEFAULT NULL,
    `id_product` INT UNSIGNED NOT NULL,
    `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
    `sku` VARCHAR(50) NOT NULL,
    `ean` VARCHAR(20) DEFAULT NULL,
    `offer_id_mirakl` BIGINT UNSIGNED DEFAULT NULL,
    `product_sku_mirakl` VARCHAR(50) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT \'pending\',
    `price_sent` DECIMAL(15,6) DEFAULT NULL,
    `stock_sent` INT DEFAULT NULL,
    `last_synced_at` DATETIME DEFAULT NULL,
    `last_error_code` VARCHAR(50) DEFAULT NULL,
    `last_error_message` TEXT DEFAULT NULL,
    `last_error_at` DATETIME DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_offer`),
    UNIQUE KEY `uk_shop_product` (`id_shop`,`id_product`,`id_product_attribute`),
    KEY `idx_sku` (`sku`),
    KEY `idx_status` (`status`),
    KEY `idx_listing` (`id_carrefour_listing`)
) ENGINE=' . $engine . ' ' . $charset . ';';

/* Mirakl order mirror */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_order` (
    `id_carrefour_order` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT UNSIGNED NOT NULL,
    `order_id_mirakl` VARCHAR(50) NOT NULL,
    `commercial_id` VARCHAR(50) DEFAULT NULL,
    `id_order` INT UNSIGNED DEFAULT NULL,
    `state` VARCHAR(50) NOT NULL,
    `payment_type` VARCHAR(30) DEFAULT NULL,
    `total_price` DECIMAL(15,6) DEFAULT NULL,
    `currency_iso_code` CHAR(3) DEFAULT NULL,
    `customer_email` VARCHAR(200) DEFAULT NULL,
    `raw_payload` LONGTEXT DEFAULT NULL,
    `shipping_deadline` DATETIME DEFAULT NULL,
    `created_date_mirakl` DATETIME DEFAULT NULL,
    `last_synced_at` DATETIME NOT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_order`),
    UNIQUE KEY `uk_shop_mirakl_id` (`id_shop`,`order_id_mirakl`),
    KEY `idx_id_order` (`id_order`),
    KEY `idx_state` (`state`)
) ENGINE=' . $engine . ' ' . $charset . ';';

/* Mirakl order lines */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_order_line` (
    `id_carrefour_order_line` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_carrefour_order` INT UNSIGNED NOT NULL,
    `order_line_id_mirakl` VARCHAR(50) NOT NULL,
    `offer_sku` VARCHAR(50) NOT NULL,
    `product_sku_mirakl` VARCHAR(50) DEFAULT NULL,
    `product_title` VARCHAR(255) DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `unit_price` DECIMAL(15,6) DEFAULT NULL,
    `total_price` DECIMAL(15,6) DEFAULT NULL,
    `shipping_price` DECIMAL(15,6) DEFAULT NULL,
    `commission_amount` DECIMAL(15,6) DEFAULT NULL,
    `line_state` VARCHAR(50) NOT NULL,
    `accepted_at` DATETIME DEFAULT NULL,
    `shipped_at` DATETIME DEFAULT NULL,
    `tracking_number` VARCHAR(100) DEFAULT NULL,
    `carrier_name` VARCHAR(100) DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_order_line`),
    UNIQUE KEY `uk_order_line` (`id_carrefour_order`,`order_line_id_mirakl`),
    KEY `idx_offer_sku` (`offer_sku`)
) ENGINE=' . $engine . ' ' . $charset . ';';

/* Async job queue */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_job` (
    `id_carrefour_job` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT \'pending\',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `payload` LONGTEXT NOT NULL,
    `result` LONGTEXT DEFAULT NULL,
    `mirakl_import_id` VARCHAR(50) DEFAULT NULL,
    `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    `last_error_code` VARCHAR(50) DEFAULT NULL,
    `last_error_message` TEXT DEFAULT NULL,
    `scheduled_at` DATETIME NOT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_job`),
    KEY `idx_status_scheduled` (`status`,`scheduled_at`),
    KEY `idx_type` (`type`),
    KEY `idx_mirakl_import` (`mirakl_import_id`)
) ENGINE=' . $engine . ' ' . $charset . ';';

/* PS category <-> Mirakl hierarchy mapping */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_category_mapping` (
    `id_carrefour_category_mapping` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT UNSIGNED NOT NULL,
    `id_category_ps` INT UNSIGNED NOT NULL,
    `category_code_mirakl` VARCHAR(100) NOT NULL,
    `category_label_mirakl` VARCHAR(255) DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_category_mapping`),
    UNIQUE KEY `uk_shop_category` (`id_shop`,`id_category_ps`),
    KEY `idx_mirakl` (`category_code_mirakl`)
) ENGINE=' . $engine . ' ' . $charset . ';';

/* Structured log (DB mirror of important events) */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'carrefour_log` (
    `id_carrefour_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT UNSIGNED DEFAULT NULL,
    `level` VARCHAR(10) NOT NULL,
    `channel` VARCHAR(50) NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `context` LONGTEXT DEFAULT NULL,
    `id_job` BIGINT UNSIGNED DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_carrefour_log`),
    KEY `idx_shop_level_date` (`id_shop`,`level`,`date_add`),
    KEY `idx_channel` (`channel`),
    KEY `idx_job` (`id_job`)
) ENGINE=' . $engine . ' ' . $charset . ';';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
