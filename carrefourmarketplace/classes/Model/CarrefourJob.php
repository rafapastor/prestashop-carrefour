<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourJob extends ObjectModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRYING = 'retrying';

    public $id_shop;

    public $type;

    public $status;

    public $priority;

    public $payload;

    public $result;

    public $mirakl_import_id;

    public $attempts;

    public $max_attempts;

    public $last_error_code;

    public $last_error_message;

    public $scheduled_at;

    public $started_at;

    public $completed_at;

    public $date_add;

    public $date_upd;

    public static $definition = [
        'table' => 'carrefour_job',
        'primary' => 'id_carrefour_job',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'priority' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'payload' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'result' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'mirakl_import_id' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'attempts' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'max_attempts' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'last_error_code' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50],
            'last_error_message' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'scheduled_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'started_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'completed_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    public function getPayloadArray()
    {
        if (empty($this->payload)) {
            return [];
        }
        $decoded = json_decode($this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setPayloadArray(array $payload)
    {
        $this->payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getResultArray()
    {
        if (empty($this->result)) {
            return [];
        }
        $decoded = json_decode($this->result, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setResultArray(array $result)
    {
        $this->result = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
