<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Pushes current PS stock values to Mirakl (STO01 — POST /api/offers/stocks).
 * Reads the payload's offer_ids and fetches *current* stock at run time so rapid
 * changes coalesced into one job always send the latest value.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourStockUpdateJob extends CarrefourAbstractJob
{
    public function execute()
    {
        $payload = $this->job->getPayloadArray();
        $offerIds = isset($payload['offer_ids']) && is_array($payload['offer_ids']) ? $payload['offer_ids'] : [];
        if (empty($offerIds)) {
            return ['action' => 'noop', 'reason' => 'no_offer_ids'];
        }

        $items = [];
        $updatedOfferIds = [];
        foreach ($offerIds as $offerId) {
            $offer = new CarrefourOffer((int) $offerId);
            if (!Validate::isLoadedObject($offer)) {
                continue;
            }
            $currentStock = (int) StockAvailable::getQuantityAvailableByProduct(
                (int) $offer->id_product,
                (int) $offer->id_product_attribute > 0 ? (int) $offer->id_product_attribute : null
            );
            $currentStock = max(0, $currentStock);
            $items[] = [
                'shop_sku' => (string) $offer->sku,
                'quantity' => $currentStock,
            ];
            $offer->stock_sent = $currentStock;
            $offer->last_synced_at = date('Y-m-d H:i:s');
            $offer->update();
            $updatedOfferIds[] = (int) $offerId;
        }

        if (empty($items)) {
            return ['action' => 'noop', 'reason' => 'no_valid_offers'];
        }

        $query = [];
        if (!empty($this->config->shop_id_mirakl)) {
            $query['shop'] = (string) $this->config->shop_id_mirakl;
        }

        $response = $this->client->post('/offers/stocks', ['stocks' => $items], $query);

        $this->logger->info('stock.push', [
            'count' => count($items),
            'offer_ids' => $updatedOfferIds,
            'status' => (int) $response['status_code'],
        ]);

        return [
            'action' => 'pushed',
            'count' => count($items),
            'offer_ids' => $updatedOfferIds,
        ];
    }
}
