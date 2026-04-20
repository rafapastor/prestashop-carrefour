<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourOrder extends ObjectModel
{
    public $id_shop;

    public $order_id_mirakl;

    public $commercial_id;

    public $id_order;

    public $state;

    public $payment_type;

    public $total_price;

    public $currency_iso_code;

    public $customer_email;

    public $raw_payload;

    public $shipping_deadline;

    public $created_date_mirakl;

    public $last_synced_at;

    public $date_add;

    public $date_upd;

    public static $definition = [
        'table' => 'carrefour_order',
        'primary' => 'id_carrefour_order',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'order_id_mirakl' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'commercial_id' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'state' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'payment_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 30],
            'total_price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'currency_iso_code' => ['type' => self::TYPE_STRING, 'validate' => 'isLanguageIsoCode', 'size' => 3],
            'customer_email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 200],
            'raw_payload' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'shipping_deadline' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'created_date_mirakl' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'last_synced_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}
