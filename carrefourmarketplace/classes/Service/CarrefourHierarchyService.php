<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Fetches the Mirakl H11 category hierarchy for display + mapping.
 * Caches the tree in the shop config row under a JSON field on disk (not a DB column —
 * kept simple on purpose; move to a dedicated table if trees grow huge).
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourHierarchyService
{
    const CACHE_FILE = 'hierarchies.json';

    /** @var int */
    private $idShop;

    /** @var MiraklClient */
    private $client;

    public function __construct($idShop, MiraklClient $client)
    {
        $this->idShop = (int) $idShop;
        $this->client = $client;
    }

    /**
     * Fetch H11 from Mirakl and persist to disk cache. Returns the flat list of nodes.
     *
     * @return array<int, array{code:string, label:string, parent_code:?string, level:int}>
     */
    public function refresh()
    {
        $response = $this->client->get('/hierarchies');
        $decoded = is_array($response['decoded']) ? $response['decoded'] : [];
        $raw = isset($decoded['hierarchies']) && is_array($decoded['hierarchies']) ? $decoded['hierarchies'] : [];

        $flat = [];
        foreach ($raw as $node) {
            $flat[] = [
                'code' => (string) ($node['code'] ?? ''),
                'label' => (string) ($node['label'] ?? ''),
                'parent_code' => isset($node['parent_code']) ? (string) $node['parent_code'] : null,
                'level' => (int) ($node['level'] ?? 0),
            ];
        }

        $this->writeCache($flat);

        return $flat;
    }

    /**
     * Returns the cached tree, or empty array if never fetched.
     */
    public function getCached()
    {
        $path = $this->cachePath();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getLastRefreshTime()
    {
        $path = $this->cachePath();

        return is_file($path) ? (int) filemtime($path) : 0;
    }

    private function writeCache(array $flat)
    {
        $dir = $this->cacheDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents($this->cachePath(), json_encode($flat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function cacheDir()
    {
        return _PS_MODULE_DIR_ . 'carrefourmarketplace/data/shop_' . $this->idShop;
    }

    private function cachePath()
    {
        return $this->cacheDir() . '/' . self::CACHE_FILE;
    }
}
