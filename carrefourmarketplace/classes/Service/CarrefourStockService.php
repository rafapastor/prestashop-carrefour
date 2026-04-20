<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Debouncing stock-change dispatcher. Called from the actionUpdateQuantity hook.
 * Coalesces rapid changes into a single StockUpdateJob per shop (window = debounce_seconds).
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourStockService
{
    public function onStockChange($idShop, $idProduct, $idProductAttribute)
    {
        $idShop = (int) $idShop;
        $idProduct = (int) $idProduct;
        $idProductAttribute = (int) $idProductAttribute;

        $offerId = (int) Db::getInstance()->getValue(sprintf(
            'SELECT `id_carrefour_offer` FROM `%scarrefour_offer`
             WHERE `id_shop` = %d AND `id_product` = %d AND `id_product_attribute` = %d',
            _DB_PREFIX_,
            $idShop,
            $idProduct,
            $idProductAttribute
        ));
        if ($offerId === 0) {
            return; /* product not tracked in any Carrefour listing */
        }

        $config = CarrefourShopConfig::findForShop($idShop);
        if ($config === null || !$config->stock_sync_enabled) {
            return;
        }

        $debounce = max(1, (int) $config->stock_sync_debounce_seconds);
        $pendingJobId = $this->findPendingStockJob($idShop);

        if ($pendingJobId > 0) {
            $this->mergeOfferIntoJob($pendingJobId, $offerId);

            return;
        }

        $queue = new CarrefourJobQueue($idShop);
        try {
            $queue->enqueue(
                'stock_update',
                ['offer_ids' => [$offerId]],
                date('Y-m-d H:i:s', time() + $debounce),
                3
            );
        } catch (\Exception $e) {
            /* swallow — don't let stock-sync failures break PS stock updates */
            $logger = new CarrefourLogger($idShop, 'stock', (string) $config->log_level);
            $logger->error('stock.enqueue_failed', [
                'offer_id' => $offerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function findPendingStockJob($idShop)
    {
        return (int) Db::getInstance()->getValue(sprintf(
            'SELECT `id_carrefour_job` FROM `%scarrefour_job`
             WHERE `id_shop` = %d AND `type` = "stock_update"
             AND `status` IN ("%s","%s")
             AND `scheduled_at` > NOW()
             ORDER BY `scheduled_at` ASC LIMIT 1',
            _DB_PREFIX_,
            (int) $idShop,
            pSQL(CarrefourJob::STATUS_PENDING),
            pSQL(CarrefourJob::STATUS_RETRYING)
        ));
    }

    private function mergeOfferIntoJob($jobId, $offerId)
    {
        $job = new CarrefourJob((int) $jobId);
        if (!Validate::isLoadedObject($job)) {
            return;
        }
        $payload = $job->getPayloadArray();
        $ids = isset($payload['offer_ids']) && is_array($payload['offer_ids']) ? $payload['offer_ids'] : [];
        $ids = array_values(array_unique(array_map('intval', array_merge($ids, [(int) $offerId]))));
        $payload['offer_ids'] = $ids;
        $job->setPayloadArray($payload);
        $job->update();
    }
}
