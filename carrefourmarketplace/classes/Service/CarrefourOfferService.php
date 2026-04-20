<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Builds Mirakl OF24 offer payloads from PrestaShop product data + listing rules.
 * Pure logic — no DB, no HTTP. Designed to be unit-testable.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourOfferService
{
    const STATE_NEW = '11';
    const STATE_USED = '10';

    /** @var CarrefourShopConfig */
    private $config;

    /** @var CarrefourCategoryMapper|null */
    private $mapper;

    public function __construct(CarrefourShopConfig $config, ?CarrefourCategoryMapper $mapper = null)
    {
        $this->config = $config;
        $this->mapper = $mapper;
    }

    /**
     * Build one offer entry for OF24. Returns null if the product lacks identifiers we need.
     *
     * $product is a flat associative array — see the SKU/EAN/price/quantity/reference/description keys below.
     * In unit tests callers pass arrays directly; in production the Job layer builds these from PS Product.
     */
    public function buildOfferPayload(array $product, CarrefourListing $listing, array $options = [])
    {
        $sku = $this->buildSku($product);
        if ($sku === '') {
            return null;
        }

        $productId = null;
        $productIdType = null;
        if (!empty($product['ean13'])) {
            $productId = (string) $product['ean13'];
            $productIdType = 'EAN';
        } elseif (!empty($product['reference'])) {
            $productId = (string) $product['reference'];
            $productIdType = 'SHOP_SKU';
        } else {
            return null;
        }

        $price = $this->computePrice((float) ($product['price'] ?? 0), $listing);
        $stock = $this->computeStock((int) ($product['quantity'] ?? 0), $listing);
        $state = isset($product['condition']) && $product['condition'] === 'used' ? self::STATE_USED : self::STATE_NEW;

        $offer = [
            'shop_sku' => $sku,
            'product_id' => $productId,
            'product_id_type' => $productIdType,
            'price' => round($price, 2),
            'quantity' => max(0, $stock),
            'state_code' => $state,
            'update_delete' => !empty($options['delete']) ? 'delete' : 'update',
        ];

        $categoryCode = $this->resolveCategoryCode($product, $listing);
        if ($categoryCode !== null && $categoryCode !== '') {
            $offer['category_code'] = $categoryCode;
        }

        if (!empty($product['description_short'])) {
            $offer['description'] = mb_substr(strip_tags((string) $product['description_short']), 0, 500);
        }
        if (!empty($product['leadtime_to_ship'])) {
            $offer['leadtime_to_ship'] = (int) $product['leadtime_to_ship'];
        }

        return $offer;
    }

    /**
     * Build a full OF24 batch payload from a list of products.
     */
    public function buildBatchPayload(array $products, CarrefourListing $listing, array $options = [])
    {
        $offers = [];
        foreach ($products as $product) {
            $offer = $this->buildOfferPayload($product, $listing, $options);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return ['offers' => $offers];
    }

    /**
     * Build the Mirakl shop_sku from a product based on the shop's configured strategy.
     */
    public function buildSku(array $product)
    {
        $strategy = (string) $this->config->sku_strategy;
        switch ($strategy) {
            case 'product_ref':
                return (string) ($product['reference'] ?? '');

            case 'ean13':
                return (string) ($product['ean13'] ?? '');

            case 'attribute_ref_fallback_product':
            default:
                if (!empty($product['attribute_reference'])) {
                    return (string) $product['attribute_reference'];
                }

                return (string) ($product['reference'] ?? '');
        }
    }

    public function computePrice($basePrice, CarrefourListing $listing)
    {
        if ((string) $listing->price_mode !== 'custom') {
            return (float) $basePrice;
        }
        $op = (string) $listing->price_variation_operator;
        $val = (float) ($listing->price_variation_value ?? 0);
        switch ($op) {
            case '%_up':
                return round((float) $basePrice * (1 + $val / 100), 2);

            case '%_down':
                return round((float) $basePrice * max(0, 1 - $val / 100), 2);

            case 'fixed_up':
                return round((float) $basePrice + $val, 2);

            case 'fixed_down':
                return round(max(0, (float) $basePrice - $val), 2);

            default:
                return (float) $basePrice;
        }
    }

    public function computeStock($baseStock, CarrefourListing $listing)
    {
        if ((string) $listing->stock_mode === 'custom' && $listing->stock_custom_value !== null) {
            return (int) $listing->stock_custom_value;
        }

        return max(0, (int) $baseStock);
    }

    /**
     * Decide whether an offer needs to be resent: pending/error always; otherwise only when price/stock diverge.
     */
    public function shouldUpsert(CarrefourOffer $offer, $currentPrice, $currentStock)
    {
        $status = (string) $offer->status;
        if ($status === 'pending' || $status === 'error') {
            return true;
        }
        if (abs((float) $offer->price_sent - (float) $currentPrice) > 0.005) {
            return true;
        }
        if ((int) $offer->stock_sent !== (int) $currentStock) {
            return true;
        }

        return false;
    }

    private function resolveCategoryCode(array $product, CarrefourListing $listing)
    {
        $mode = (string) $listing->category_mapping_mode;
        switch ($mode) {
            case 'single_category':
                $val = (string) $listing->category_mapping_value;

                return $val !== '' ? $val : null;

            case 'custom_attribute':
                /* Phase 3b will read from a PS feature/attribute named by $listing->category_mapping_value. */
                return !empty($product['category_code_custom']) ? (string) $product['category_code_custom'] : null;

            case 'category_mapping':
            default:
                $idCat = (int) ($product['id_category_default'] ?? 0);
                if ($idCat === 0 || $this->mapper === null) {
                    return null;
                }
                $mapping = $this->mapper->getMappingForPsCategory($idCat);

                return $mapping ? $mapping['code'] : null;
        }
    }
}
