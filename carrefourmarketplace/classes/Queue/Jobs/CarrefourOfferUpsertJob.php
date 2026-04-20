<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Upload or refresh a batch of offers to Mirakl (OF24 flow).
 * Two-phase lifecycle per job:
 *   1. Submit — POST /api/offers with the built payload; save import_id on the job.
 *   2. Poll — GET /api/offers/imports/{import_id} until COMPLETE; fetch error_report
 *             and apply per-offer status.
 *
 * Between 1 and 2 the job is re-queued by the runner with a short delay.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourOfferUpsertJob extends CarrefourAbstractJob
{
    public function execute()
    {
        if (!empty($this->job->mirakl_import_id)) {
            return $this->pollImport();
        }

        return $this->submitImport();
    }

    /**
     * Build the payload from the offer IDs in job.payload and POST to Mirakl.
     */
    private function submitImport()
    {
        $payload = $this->job->getPayloadArray();
        $offerIds = isset($payload['offer_ids']) && is_array($payload['offer_ids']) ? $payload['offer_ids'] : [];
        $listingId = (int) ($payload['listing_id'] ?? 0);

        if (empty($offerIds)) {
            throw new \RuntimeException('Job payload has no offer_ids');
        }

        $listing = new CarrefourListing($listingId);
        if (!Validate::isLoadedObject($listing)) {
            throw new \RuntimeException('Listing ' . $listingId . ' not found');
        }

        $service = new CarrefourOfferService($this->config, new CarrefourCategoryMapper($this->idShop));
        $products = [];
        foreach ($offerIds as $id) {
            $product = $this->loadProductData((int) $id);
            if ($product !== null) {
                $products[] = $product;
            }
        }
        $body = $service->buildBatchPayload($products, $listing);

        if (empty($body['offers'])) {
            throw new \RuntimeException('Offer payload is empty — check SKU/EAN on selected products');
        }

        $query = [];
        if (!empty($this->config->shop_id_mirakl)) {
            $query['shop'] = (string) $this->config->shop_id_mirakl;
        }
        $response = $this->client->post('/offers', $body, $query);
        $decoded = is_array($response['decoded']) ? $response['decoded'] : [];
        $importId = isset($decoded['import_id']) ? (string) $decoded['import_id'] : '';

        if ($importId === '') {
            throw new \RuntimeException('Mirakl response did not contain import_id');
        }

        $this->job->mirakl_import_id = $importId;
        $this->job->update();
        $this->markOffersSyncing($offerIds);

        $this->logger->info('offer.submit', [
            'import_id' => $importId,
            'count' => count($body['offers']),
            'listing_id' => $listingId,
        ]);

        return [
            'action' => 'submitted',
            'import_id' => $importId,
            'offer_count' => count($body['offers']),
            'poll_again' => true,
            'poll_delay_seconds' => 15,
        ];
    }

    /**
     * Poll import status. If complete, parse error_report and persist per-offer status.
     */
    private function pollImport()
    {
        $importId = (string) $this->job->mirakl_import_id;
        $response = $this->client->get('/offers/imports/' . rawurlencode($importId));
        $decoded = is_array($response['decoded']) ? $response['decoded'] : [];
        $status = isset($decoded['import_status']) ? (string) $decoded['import_status'] : 'UNKNOWN';

        if (!in_array($status, ['COMPLETE', 'COMPLETE_WITH_ERRORS'], true)) {
            $this->logger->info('offer.poll', ['import_id' => $importId, 'status' => $status]);

            return [
                'action' => 'polling',
                'import_id' => $importId,
                'status' => $status,
                'poll_again' => true,
                'poll_delay_seconds' => 30,
            ];
        }

        $errorCsv = '';
        if (!empty($decoded['has_error_report'])) {
            $csvResp = $this->client->get('/offers/imports/' . rawurlencode($importId) . '/error_report');
            $errorCsv = isset($csvResp['body']) ? (string) $csvResp['body'] : '';
        }

        $errorMap = $this->indexErrors($errorCsv);
        $payload = $this->job->getPayloadArray();
        $offerIds = isset($payload['offer_ids']) && is_array($payload['offer_ids']) ? $payload['offer_ids'] : [];

        $listedCount = 0;
        $errorCount = 0;
        foreach ($offerIds as $id) {
            $offer = new CarrefourOffer((int) $id);
            if (!Validate::isLoadedObject($offer)) {
                continue;
            }
            if (isset($errorMap[$offer->sku])) {
                $err = $errorMap[$offer->sku];
                $offer->status = 'error';
                $offer->last_error_code = mb_substr((string) ($err['error_code'] ?? 'ERR'), 0, 50);
                $offer->last_error_message = mb_substr((string) ($err['error_message'] ?? ''), 0, 5000);
                $offer->last_error_at = date('Y-m-d H:i:s');
                ++$errorCount;
            } else {
                $offer->status = 'listed';
                $offer->last_error_code = null;
                $offer->last_error_message = null;
                ++$listedCount;
            }
            $offer->last_synced_at = date('Y-m-d H:i:s');
            $offer->update();
        }

        $this->logger->info('offer.complete', [
            'import_id' => $importId,
            'status' => $status,
            'listed' => $listedCount,
            'errors' => $errorCount,
        ]);

        return [
            'action' => 'completed',
            'import_id' => $importId,
            'status' => $status,
            'listed' => $listedCount,
            'errors' => $errorCount,
        ];
    }

    /**
     * Load PS product fields needed by OfferService. Minimal implementation — Phase 3b
     * will enrich with attribute reference, variation stock, etc.
     */
    private function loadProductData($idCarrefourOffer)
    {
        $offer = new CarrefourOffer($idCarrefourOffer);
        if (!Validate::isLoadedObject($offer)) {
            return null;
        }

        $idProduct = (int) $offer->id_product;
        $idAttribute = (int) $offer->id_product_attribute;
        $product = new Product($idProduct, false, $this->idShop > 0 ? null : null);
        if (!Validate::isLoadedObject($product)) {
            return null;
        }

        $price = (float) Product::getPriceStatic(
            $idProduct,
            true,
            $idAttribute > 0 ? $idAttribute : null,
            6,
            null,
            false,
            true,
            1,
            false,
            null,
            null,
            null,
            $specific_price,
            true,
            true,
            null,
            false
        );
        $quantity = (int) StockAvailable::getQuantityAvailableByProduct($idProduct, $idAttribute > 0 ? $idAttribute : null);

        $attributeReference = '';
        if ($idAttribute > 0) {
            $attributeReference = (string) Db::getInstance()->getValue(sprintf(
                'SELECT `reference` FROM `%sproduct_attribute` WHERE `id_product_attribute` = %d',
                _DB_PREFIX_,
                $idAttribute
            ));
        }

        return [
            'id_product' => $idProduct,
            'id_product_attribute' => $idAttribute,
            'reference' => (string) $product->reference,
            'attribute_reference' => $attributeReference,
            'ean13' => (string) ($idAttribute > 0 ? $this->getAttributeEan($idAttribute) : $product->ean13),
            'price' => $price,
            'quantity' => $quantity,
            'description_short' => is_array($product->description_short) ? (reset($product->description_short) ?: '') : (string) $product->description_short,
            'condition' => (string) $product->condition,
            'id_category_default' => (int) $product->id_category_default,
        ];
    }

    private function getAttributeEan($idAttribute)
    {
        return (string) Db::getInstance()->getValue(sprintf(
            'SELECT `ean13` FROM `%sproduct_attribute` WHERE `id_product_attribute` = %d',
            _DB_PREFIX_,
            (int) $idAttribute
        ));
    }

    private function markOffersSyncing(array $offerIds)
    {
        $ids = array_map('intval', $offerIds);
        if (empty($ids)) {
            return;
        }
        Db::getInstance()->execute(sprintf(
            'UPDATE `%scarrefour_offer` SET `status` = "syncing", `date_upd` = NOW()
             WHERE `id_carrefour_offer` IN (%s)',
            _DB_PREFIX_,
            implode(',', $ids)
        ));
    }

    private function indexErrors($csv)
    {
        $rows = MiraklErrorReport::parse((string) $csv);
        $map = [];
        foreach ($rows as $row) {
            $sku = $row['shop_sku'] ?? $row['sku'] ?? null;
            if ($sku !== null && $sku !== '') {
                $map[$sku] = $row;
            }
        }

        return $map;
    }
}
