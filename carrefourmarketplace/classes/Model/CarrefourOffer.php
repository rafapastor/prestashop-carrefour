<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourOffer extends ObjectModel
{
    public $id_shop;

    public $id_carrefour_listing;

    public $id_product;

    public $id_product_attribute;

    public $sku;

    public $ean;

    public $offer_id_mirakl;

    public $product_sku_mirakl;

    public $status;

    public $price_sent;

    public $stock_sent;

    public $last_synced_at;

    public $last_error_code;

    public $last_error_message;

    public $last_error_at;

    public $date_add;

    public $date_upd;

    public static $definition = [
        'table' => 'carrefour_offer',
        'primary' => 'id_carrefour_offer',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_carrefour_listing' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'sku' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'ean' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'offer_id_mirakl' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'product_sku_mirakl' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'price_sent' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'stock_sent' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'last_synced_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'last_error_code' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'last_error_message' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'last_error_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}
