<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Pull Mirakl orders updated since the last successful sync.
 * Self-re-schedules based on order_sync_interval_minutes after each run.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourOrderSyncJob extends CarrefourAbstractJob
{
    public function execute()
    {
        $payload = $this->job->getPayloadArray();
        $since = isset($payload['since']) ? (string) $payload['since'] : null;

        if ($since === null) {
            $since = (string) Db::getInstance()->getValue(sprintf(
                'SELECT MAX(`last_synced_at`) FROM `%scarrefour_order` WHERE `id_shop` = %d',
                _DB_PREFIX_,
                $this->idShop
            ));
            if ($since === null || $since === '' || $since === false) {
                $since = date('c', time() - 7 * 24 * 3600);
            } else {
                $since = date('c', strtotime($since));
            }
        }

        $service = new CarrefourOrderService($this->idShop, $this->client, $this->config, $this->logger);
        $result = $service->pullRecentOrders($since);

        /* Self-reschedule: enqueue next run */
        $interval = max(1, (int) $this->config->order_sync_interval_minutes);
        try {
            $queue = new CarrefourJobQueue($this->idShop);
            $queue->enqueue(
                'order_sync',
                [],
                date('Y-m-d H:i:s', time() + $interval * 60),
                4
            );
        } catch (\Exception $e) {
            $this->logger->warn('order.reschedule_failed', ['error' => $e->getMessage()]);
        }

        return array_merge(['action' => 'synced', 'since' => $since], $result);
    }
}
