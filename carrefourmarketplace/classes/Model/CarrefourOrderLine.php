<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourOrderLine extends ObjectModel
{
    public $id_carrefour_order;

    public $order_line_id_mirakl;

    public $offer_sku;

    public $product_sku_mirakl;

    public $product_title;

    public $quantity;

    public $unit_price;

    public $total_price;

    public $shipping_price;

    public $commission_amount;

    public $line_state;

    public $accepted_at;

    public $shipped_at;

    public $tracking_number;

    public $carrier_name;

    public $date_add;

    public $date_upd;

    public static $definition = [
        'table' => 'carrefour_order_line',
        'primary' => 'id_carrefour_order_line',
        'fields' => [
            'id_carrefour_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'order_line_id_mirakl' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'offer_sku' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'product_sku_mirakl' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'product_title' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'quantity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'unit_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'total_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'shipping_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'commission_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'line_state' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'accepted_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'shipped_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'tracking_number' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100],
            'carrier_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}
