<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourCategoryMapping extends ObjectModel
{
    public $id_shop;

    public $id_category_ps;

    public $category_code_mirakl;

    public $category_label_mirakl;

    public $date_add;

    public $date_upd;

    public static $definition = [
        'table' => 'carrefour_category_mapping',
        'primary' => 'id_carrefour_category_mapping',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_category_ps' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'category_code_mirakl' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100, 'required' => true],
            'category_label_mirakl' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}
