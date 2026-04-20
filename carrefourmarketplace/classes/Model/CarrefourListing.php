<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourListing extends ObjectModel
{
    public $id_shop;

    public $name;

    public $status;

    public $category_mapping_mode;

    public $category_mapping_value;

    public $price_mode;

    public $price_variation_operator;

    public $price_variation_value;

    public $stock_mode;

    public $stock_custom_value;

    public $date_add;

    public $date_upd;

    public static $definition = [
        'table' => 'carrefour_listing',
        'primary' => 'id_carrefour_listing',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 200, 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'category_mapping_mode' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 30],
            'category_mapping_value' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100],
            'price_mode' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'price_variation_operator' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'price_variation_value' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'stock_mode' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'stock_custom_value' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}
