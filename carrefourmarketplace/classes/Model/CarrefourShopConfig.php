<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourShopConfig extends ObjectModel
{
    public $id_shop;

    public $api_endpoint;

    public $api_key_encrypted;

    public $sandbox_mode;

    public $shop_id_mirakl;

    public $auto_accept_orders;

    public $default_order_state_id;

    public $stock_sync_enabled;

    public $stock_sync_debounce_seconds;

    public $price_sync_enabled;

    public $order_sync_interval_minutes;

    public $webhook_enabled;

    public $webhook_secret;

    public $sku_strategy;

    public $log_level;

    public $log_retention_days;

    public $date_add;

    public $date_upd;

    public static $definition = [
        'table' => 'carrefour_shop_config',
        'primary' => 'id_carrefour_shop_config',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'api_endpoint' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'size' => 255, 'required' => true],
            'api_key_encrypted' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 2048],
            'sandbox_mode' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'shop_id_mirakl' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'auto_accept_orders' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'default_order_state_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'stock_sync_enabled' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'stock_sync_debounce_seconds' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'price_sync_enabled' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'order_sync_interval_minutes' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'webhook_enabled' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'webhook_secret' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'sku_strategy' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'log_level' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 10],
            'log_retention_days' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Load the configuration row for a given shop, or return null if none exists.
     */
    public static function findForShop($idShop)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_carrefour_shop_config` FROM `' . _DB_PREFIX_ . 'carrefour_shop_config` WHERE `id_shop` = ' . (int) $idShop
        );
        if (!$id) {
            return null;
        }

        return new self($id);
    }

    /**
     * Decrypt and return the API key, or empty string if not set.
     */
    public function getApiKey()
    {
        if (empty($this->api_key_encrypted)) {
            return '';
        }

        return CarrefourCrypto::decrypt($this->api_key_encrypted);
    }

    /**
     * Encrypt and set the API key.
     */
    public function setApiKey($plain)
    {
        $this->api_key_encrypted = $plain === '' || $plain === null ? '' : CarrefourCrypto::encrypt($plain);
    }
}
