<?php

use PHPUnit\Framework\TestCase;

/**
 * Verifies the N+1 protection in CarrefourCategoryMapper::getMappingForPsCategory:
 * however many lookups happen, only ONE SELECT should hit the DB (the initial cache load).
 */
class CategoryMapperCacheTest extends TestCase
{
    protected function setUp(): void
    {
        Db::reset();
    }

    public function test_cache_hydrates_on_first_lookup_and_skips_db_on_subsequent_calls()
    {
        /* Pre-queue the result of the single getAllMappings() SELECT */
        Db::$nextResults = [
            [
                ['id_category_ps' => 1, 'category_code_mirakl' => 'ELEC', 'category_label_mirakl' => 'Electronics'],
                ['id_category_ps' => 2, 'category_code_mirakl' => 'HOME', 'category_label_mirakl' => 'Home'],
                ['id_category_ps' => 3, 'category_code_mirakl' => 'TOYS', 'category_label_mirakl' => 'Toys'],
            ],
        ];

        $mapper = new CarrefourCategoryMapper(42);

        /* 300 lookups, 3 categories — without caching this would be 300 queries. */
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame('ELEC', $mapper->getMappingForPsCategory(1)['code']);
            $this->assertSame('HOME', $mapper->getMappingForPsCategory(2)['code']);
            $this->assertSame('TOYS', $mapper->getMappingForPsCategory(3)['code']);
        }

        $this->assertSame(1, Db::$queryCount, 'Only the initial bulk load should touch the DB');
    }

    public function test_unknown_category_returns_null_without_extra_db_hit()
    {
        Db::$nextResults = [
            [
                ['id_category_ps' => 1, 'category_code_mirakl' => 'ELEC', 'category_label_mirakl' => 'Electronics'],
            ],
        ];

        $mapper = new CarrefourCategoryMapper(1);

        $this->assertNotNull($mapper->getMappingForPsCategory(1));
        $this->assertNull($mapper->getMappingForPsCategory(999));
        $this->assertNull($mapper->getMappingForPsCategory(1000));

        $this->assertSame(1, Db::$queryCount, 'Unknown categories must not trigger extra DB lookups');
    }

    public function test_empty_mappings_still_caches_after_first_call()
    {
        Db::$nextResults = [[]];

        $mapper = new CarrefourCategoryMapper(1);

        for ($i = 0; $i < 10; $i++) {
            $this->assertNull($mapper->getMappingForPsCategory($i));
        }

        $this->assertSame(1, Db::$queryCount);
    }
}
