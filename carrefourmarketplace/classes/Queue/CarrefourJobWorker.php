<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Sequential job worker. Intended to be invoked via system cron or PS cron module:
 *   php cron/worker.php --max-jobs=50 --max-seconds=55
 *
 * It iterates over all shops that have a CarrefourShopConfig row, claims one job
 * at a time per shop and dispatches it via JobRunner until the limits are hit.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourJobWorker
{
    /** @var int */
    private $maxJobs;

    /** @var int */
    private $maxSeconds;

    /** @var int */
    private $sleepWhenEmptyMs;

    /** @var bool */
    private $stopFlag = false;

    public function __construct($maxJobs = 50, $maxSeconds = 55, $sleepWhenEmptyMs = 500)
    {
        $this->maxJobs = max(1, (int) $maxJobs);
        $this->maxSeconds = max(1, (int) $maxSeconds);
        $this->sleepWhenEmptyMs = max(50, (int) $sleepWhenEmptyMs);
    }

    public function run()
    {
        $this->installSignalHandlers();
        $deadline = time() + $this->maxSeconds;
        $processed = 0;
        $runner = new CarrefourJobRunner();

        while (!$this->stopFlag && $processed < $this->maxJobs && time() < $deadline) {
            $found = false;
            foreach ($this->getActiveShopIds() as $idShop) {
                if ($this->stopFlag || $processed >= $this->maxJobs || time() >= $deadline) {
                    break;
                }
                $queue = new CarrefourJobQueue($idShop);
                $job = $queue->claim();
                if ($job === null) {
                    continue;
                }
                $found = true;
                $runner->run($job);
                $processed++;
            }
            if (!$found) {
                usleep($this->sleepWhenEmptyMs * 1000);
            }
        }

        return $processed;
    }

    /**
     * Returns shop IDs that have a Carrefour configuration row.
     */
    private function getActiveShopIds()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `id_shop` FROM `' . _DB_PREFIX_ . 'carrefour_shop_config`'
        );
        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ids[] = (int) $r['id_shop'];
            }
        }

        return $ids;
    }

    private function installSignalHandlers()
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $handler = function () {
            $this->stopFlag = true;
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }
}
