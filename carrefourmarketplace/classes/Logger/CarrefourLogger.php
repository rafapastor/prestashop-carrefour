<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourLogger
{
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARN = 'warn';
    const LEVEL_ERROR = 'error';

    private static $levelOrder = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARN => 2,
        self::LEVEL_ERROR => 3,
    ];

    private $shopId;

    private $channel;

    private $minLevel;

    public function __construct($shopId = null, $channel = 'module', $minLevel = self::LEVEL_INFO)
    {
        $this->shopId = $shopId;
        $this->channel = $channel;
        $this->minLevel = $minLevel;
    }

    public function debug($message, array $context = [], $idJob = null)
    {
        $this->log(self::LEVEL_DEBUG, $message, $context, $idJob);
    }

    public function info($message, array $context = [], $idJob = null)
    {
        $this->log(self::LEVEL_INFO, $message, $context, $idJob);
    }

    public function warn($message, array $context = [], $idJob = null)
    {
        $this->log(self::LEVEL_WARN, $message, $context, $idJob);
    }

    public function error($message, array $context = [], $idJob = null)
    {
        $this->log(self::LEVEL_ERROR, $message, $context, $idJob);
    }

    private function log($level, $message, array $context, $idJob)
    {
        if (!isset(self::$levelOrder[$level])) {
            return;
        }
        if (self::$levelOrder[$level] < self::$levelOrder[$this->minLevel]) {
            return;
        }

        $message = mb_substr((string) $message, 0, 500);
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        try {
            $log = new CarrefourLog();
            $log->id_shop = $this->shopId;
            $log->level = $level;
            $log->channel = $this->channel;
            $log->message = $message;
            $log->context = $contextJson;
            $log->id_job = $idJob;
            $log->add();
        } catch (\Exception $e) {
            $this->writeToFile($level, $message, $contextJson);
        }

        if (self::$levelOrder[$level] >= self::$levelOrder[self::LEVEL_WARN]) {
            $this->writeToFile($level, $message, $contextJson);
        }
    }

    private function writeToFile($level, $message, $contextJson)
    {
        $dir = _PS_MODULE_DIR_ . 'carrefourmarketplace/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        $file = $dir . '/carrefour-' . date('Y-m-d') . '.log';
        $line = sprintf(
            '[%s] shop=%s %s.%s %s%s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            $this->shopId !== null ? (int) $this->shopId : '-',
            strtoupper($this->channel),
            strtoupper($level),
            $message,
            $contextJson !== null ? ' ' . $contextJson : ''
        );
        @file_put_contents($file, $line, FILE_APPEND);
    }
}
