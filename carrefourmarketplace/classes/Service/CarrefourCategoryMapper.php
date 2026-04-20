<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Data-layer helper for the carrefour_category_mapping table.
 * Phase 3a: CRUD + lookups by PS category. Phase 3b will add Mirakl H11 fetch and tree UI.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourCategoryMapper
{
    /** @var int */
    private $idShop;

    /** @var array<int, array{code:string, label:string}>|null In-memory cache loaded lazily on first lookup. */
    private $cache = null;

    public function __construct($idShop)
    {
        $this->idShop = (int) $idShop;
    }

    /**
     * Returns ['code' => ..., 'label' => ...] or null.
     *
     * Uses an in-memory cache populated on first call with a single SELECT so bulk calls
     * (e.g. CarrefourOfferService::buildBatchPayload over hundreds of products) don't trigger
     * N+1 queries.
     */
    public function getMappingForPsCategory($idCategoryPs)
    {
        if ($this->cache === null) {
            $this->loadCache();
        }
        $idCategoryPs = (int) $idCategoryPs;

        return isset($this->cache[$idCategoryPs]) ? $this->cache[$idCategoryPs] : null;
    }

    /**
     * Drop the in-memory cache. Call after setMapping/removeMapping so subsequent reads see fresh data.
     */
    public function invalidateCache()
    {
        $this->cache = null;
    }

    private function loadCache()
    {
        $this->cache = [];
        foreach ($this->getAllMappings() as $m) {
            $this->cache[(int) $m['id_category_ps']] = [
                'code' => $m['code'],
                'label' => $m['label'],
            ];
        }
    }

    public function setMapping($idCategoryPs, $miraklCode, $miraklLabel = null)
    {
        $idCategoryPs = (int) $idCategoryPs;
        $id = (int) Db::getInstance()->getValue(sprintf(
            'SELECT `id_carrefour_category_mapping` FROM `%scarrefour_category_mapping`
             WHERE `id_shop` = %d AND `id_category_ps` = %d',
            _DB_PREFIX_,
            $this->idShop,
            $idCategoryPs
        ));
        $mapping = $id > 0 ? new CarrefourCategoryMapping($id) : new CarrefourCategoryMapping();
        $mapping->id_shop = $this->idShop;
        $mapping->id_category_ps = $idCategoryPs;
        $mapping->category_code_mirakl = (string) $miraklCode;
        $mapping->category_label_mirakl = $miraklLabel !== null ? (string) $miraklLabel : '';

        $ok = $id > 0 ? $mapping->update() : $mapping->add();
        $this->invalidateCache();

        return $ok;
    }

    public function removeMapping($idCategoryPs)
    {
        $ok = Db::getInstance()->execute(sprintf(
            'DELETE FROM `%scarrefour_category_mapping` WHERE `id_shop` = %d AND `id_category_ps` = %d',
            _DB_PREFIX_,
            $this->idShop,
            (int) $idCategoryPs
        ));
        $this->invalidateCache();

        return $ok;
    }

    /**
     * @return array<int, array{id_category_ps:int,code:string,label:string}>
     */
    public function getAllMappings()
    {
        $rows = Db::getInstance()->executeS(sprintf(
            'SELECT `id_category_ps`, `category_code_mirakl`, `category_label_mirakl`
             FROM `%scarrefour_category_mapping` WHERE `id_shop` = %d',
            _DB_PREFIX_,
            $this->idShop
        ));
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id_category_ps' => (int) $row['id_category_ps'],
                'code' => (string) $row['category_code_mirakl'],
                'label' => (string) $row['category_label_mirakl'],
            ];
        }

        return $out;
    }
}
