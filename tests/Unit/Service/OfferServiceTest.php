<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CarrefourOfferService — pure-logic payload builder.
 * We inject fake CarrefourShopConfig / CarrefourListing objects; real ObjectModels are not required.
 */
class OfferServiceTest extends TestCase
{
    /**
     * Helper — build a minimal fake config object (public properties mimic ObjectModel fields).
     */
    private function makeConfig($skuStrategy = 'attribute_ref_fallback_product')
    {
        $cfg = new \stdClass();
        $cfg->sku_strategy = $skuStrategy;
        $cfg->id_shop = 1;

        /* Force the real ObjectModel class when autoloader can't load Shop class in tests */
        return $this->mockConfig($cfg);
    }

    /**
     * Build a stand-in that passes instanceof CarrefourShopConfig.
     * We use a real (empty) instance and set public properties on it.
     */
    private function mockConfig($src)
    {
        $reflection = new ReflectionClass(CarrefourShopConfig::class);
        $obj = $reflection->newInstanceWithoutConstructor();
        foreach (get_object_vars($src) as $k => $v) {
            $obj->$k = $v;
        }

        return $obj;
    }

    private function mockListing(array $overrides = [])
    {
        $defaults = [
            'id' => 1,
            'id_shop' => 1,
            'name' => 'Test listing',
            'status' => 'active',
            'category_mapping_mode' => 'single_category',
            'category_mapping_value' => 'HOME_APPLIANCES',
            'price_mode' => 'product',
            'price_variation_operator' => 'none',
            'price_variation_value' => null,
            'stock_mode' => 'product',
            'stock_custom_value' => null,
        ];
        $reflection = new ReflectionClass(CarrefourListing::class);
        $obj = $reflection->newInstanceWithoutConstructor();
        foreach (array_merge($defaults, $overrides) as $k => $v) {
            $obj->$k = $v;
        }

        return $obj;
    }

    public function test_build_sku_uses_attribute_ref_when_present()
    {
        $service = new CarrefourOfferService($this->makeConfig('attribute_ref_fallback_product'));
        $sku = $service->buildSku(['reference' => 'PROD1', 'attribute_reference' => 'PROD1-RED-M']);
        $this->assertSame('PROD1-RED-M', $sku);
    }

    public function test_build_sku_falls_back_to_product_reference()
    {
        $service = new CarrefourOfferService($this->makeConfig('attribute_ref_fallback_product'));
        $sku = $service->buildSku(['reference' => 'PROD1', 'attribute_reference' => '']);
        $this->assertSame('PROD1', $sku);
    }

    public function test_build_sku_strategy_product_ref()
    {
        $service = new CarrefourOfferService($this->makeConfig('product_ref'));
        $sku = $service->buildSku(['reference' => 'PROD1', 'attribute_reference' => 'X']);
        $this->assertSame('PROD1', $sku);
    }

    public function test_build_sku_strategy_ean13()
    {
        $service = new CarrefourOfferService($this->makeConfig('ean13'));
        $sku = $service->buildSku(['ean13' => '8412345678901', 'reference' => 'X']);
        $this->assertSame('8412345678901', $sku);
    }

    public function test_price_mode_product_returns_base_price()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing(['price_mode' => 'product']);
        $this->assertSame(10.0, $service->computePrice(10.0, $listing));
    }

    public function test_price_mode_custom_applies_percent_up()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing([
            'price_mode' => 'custom',
            'price_variation_operator' => '%_up',
            'price_variation_value' => 10,
        ]);
        $this->assertSame(11.0, $service->computePrice(10.0, $listing));
    }

    public function test_price_mode_custom_applies_percent_down_floors_at_zero()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing([
            'price_mode' => 'custom',
            'price_variation_operator' => '%_down',
            'price_variation_value' => 150,
        ]);
        $this->assertSame(0.0, $service->computePrice(10.0, $listing));
    }

    public function test_price_mode_custom_fixed_up()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing([
            'price_mode' => 'custom',
            'price_variation_operator' => 'fixed_up',
            'price_variation_value' => 2.50,
        ]);
        $this->assertSame(12.49, $service->computePrice(9.99, $listing));
    }

    public function test_stock_mode_custom_uses_fixed_value()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing(['stock_mode' => 'custom', 'stock_custom_value' => 5]);
        $this->assertSame(5, $service->computeStock(0, $listing));
        $this->assertSame(5, $service->computeStock(999, $listing));
    }

    public function test_stock_mode_product_uses_base_and_floors_at_zero()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing(['stock_mode' => 'product']);
        $this->assertSame(7, $service->computeStock(7, $listing));
        $this->assertSame(0, $service->computeStock(-3, $listing));
    }

    public function test_build_offer_payload_with_ean()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing();
        $offer = $service->buildOfferPayload(
            [
                'reference' => 'REF1',
                'attribute_reference' => '',
                'ean13' => '8412345678901',
                'price' => 19.99,
                'quantity' => 12,
                'description_short' => '<p>Very nice widget</p>',
                'condition' => 'new',
                'id_category_default' => 3,
            ],
            $listing
        );

        $this->assertIsArray($offer);
        $this->assertSame('REF1', $offer['shop_sku']);
        $this->assertSame('8412345678901', $offer['product_id']);
        $this->assertSame('EAN', $offer['product_id_type']);
        $this->assertSame(19.99, $offer['price']);
        $this->assertSame(12, $offer['quantity']);
        $this->assertSame('11', $offer['state_code']);
        $this->assertSame('HOME_APPLIANCES', $offer['category_code']);
        $this->assertSame('Very nice widget', $offer['description']);
        $this->assertSame('update', $offer['update_delete']);
    }

    public function test_build_offer_payload_used_condition_maps_to_state_10()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing();
        $offer = $service->buildOfferPayload(
            [
                'reference' => 'R1',
                'ean13' => '8412345678901',
                'price' => 1,
                'quantity' => 1,
                'condition' => 'used',
            ],
            $listing
        );
        $this->assertSame('10', $offer['state_code']);
    }

    public function test_build_offer_payload_returns_null_without_identifiers()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing();
        $offer = $service->buildOfferPayload(
            [
                'reference' => '',
                'attribute_reference' => '',
                'ean13' => '',
                'price' => 1,
                'quantity' => 1,
            ],
            $listing
        );
        $this->assertNull($offer);
    }

    public function test_build_batch_payload_filters_out_invalid_entries()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing();
        $batch = $service->buildBatchPayload(
            [
                ['reference' => 'OK1', 'ean13' => '8412345678901', 'price' => 1, 'quantity' => 1],
                ['reference' => '', 'ean13' => '', 'price' => 2, 'quantity' => 2],
                ['reference' => 'OK2', 'ean13' => '8412345678902', 'price' => 3, 'quantity' => 3],
            ],
            $listing
        );

        $this->assertArrayHasKey('offers', $batch);
        $this->assertCount(2, $batch['offers']);
        $this->assertSame('OK1', $batch['offers'][0]['shop_sku']);
        $this->assertSame('OK2', $batch['offers'][1]['shop_sku']);
    }

    public function test_build_batch_delete_flag_propagates_to_update_delete()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $listing = $this->mockListing();
        $batch = $service->buildBatchPayload(
            [['reference' => 'R1', 'ean13' => '8412345678901', 'price' => 1, 'quantity' => 0]],
            $listing,
            ['delete' => true]
        );
        $this->assertSame('delete', $batch['offers'][0]['update_delete']);
    }

    public function test_should_upsert_returns_true_for_pending_or_error()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $reflection = new ReflectionClass(CarrefourOffer::class);
        $offer = $reflection->newInstanceWithoutConstructor();
        $offer->status = 'pending';
        $offer->price_sent = 10.00;
        $offer->stock_sent = 5;
        $this->assertTrue($service->shouldUpsert($offer, 10.00, 5));

        $offer->status = 'error';
        $this->assertTrue($service->shouldUpsert($offer, 10.00, 5));
    }

    public function test_should_upsert_returns_false_when_listed_and_unchanged()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $reflection = new ReflectionClass(CarrefourOffer::class);
        $offer = $reflection->newInstanceWithoutConstructor();
        $offer->status = 'listed';
        $offer->price_sent = 9.99;
        $offer->stock_sent = 5;
        $this->assertFalse($service->shouldUpsert($offer, 9.99, 5));
    }

    public function test_should_upsert_returns_true_when_price_changes()
    {
        $service = new CarrefourOfferService($this->makeConfig());
        $reflection = new ReflectionClass(CarrefourOffer::class);
        $offer = $reflection->newInstanceWithoutConstructor();
        $offer->status = 'listed';
        $offer->price_sent = 9.99;
        $offer->stock_sent = 5;
        $this->assertTrue($service->shouldUpsert($offer, 10.99, 5));
    }
}
