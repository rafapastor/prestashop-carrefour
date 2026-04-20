<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourLog extends ObjectModel
{
    public $id_shop;

    public $level;

    public $channel;

    public $message;

    public $context;

    public $id_job;

    public $date_add;

    public static $definition = [
        'table' => 'carrefour_log',
        'primary' => 'id_carrefour_log',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'level' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 10, 'required' => true],
            'channel' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'message' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 500, 'required' => true],
            'context' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'id_job' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}
