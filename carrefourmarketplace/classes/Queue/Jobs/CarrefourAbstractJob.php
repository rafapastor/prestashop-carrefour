<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Base class for all Carrefour queue jobs. Handles shop context loading,
 * Mirakl client instantiation and logger wiring so subclasses focus on business logic.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

abstract class CarrefourAbstractJob
{
    /** @var CarrefourJob */
    protected $job;

    /** @var int */
    protected $idShop;

    /** @var CarrefourShopConfig */
    protected $config;

    /** @var MiraklClient */
    protected $client;

    /** @var CarrefourLogger */
    protected $logger;

    public function __construct(CarrefourJob $job, ?MiraklClient $clientOverride = null)
    {
        $this->job = $job;
        $this->idShop = (int) $job->id_shop;
        $config = CarrefourShopConfig::findForShop($this->idShop);
        if ($config === null) {
            throw new \RuntimeException('Shop config missing for shop ' . $this->idShop);
        }
        $this->config = $config;
        $this->logger = new CarrefourLogger($this->idShop, 'job', (string) $config->log_level);

        if ($clientOverride !== null) {
            $this->client = $clientOverride;
        } else {
            $this->client = new MiraklClient(
                (string) $config->api_endpoint,
                $config->getApiKey(),
                $config->shop_id_mirakl,
                $this->logger
            );
        }
    }

    /**
     * Execute the job. Return an associative array with result data on success.
     * Throw on unrecoverable failure. For "still in progress" polling scenarios,
     * return an array with 'poll_again' => true and the runner will re-schedule.
     */
    abstract public function execute();
}
