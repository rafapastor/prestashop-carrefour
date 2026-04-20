<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Mirakl webhook receiver. URL shape (set in Mirakl backoffice):
 *   https://yourshop.com/module/carrefourmarketplace/webhook?secret=XXXX&shop=1
 *
 * Secret is validated against carrefour_shop_config.webhook_secret for the given shop.
 * Responds 200 as fast as possible; heavy lifting is queued.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourmarketplaceWebhookModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    /** @var bool */
    public $ajax = true;

    public function initContent()
    {
        /* No template rendering — this is a pure POST endpoint. */
    }

    public function postProcess()
    {
        $this->respond();
    }

    public function display()
    {
        $this->respond();
    }

    private function respond()
    {
        $idShop = (int) Tools::getValue('shop');
        if ($idShop === 0) {
            $idShop = (int) $this->context->shop->id;
        }
        $secret = (string) Tools::getValue('secret');

        $config = CarrefourShopConfig::findForShop($idShop);
        if ($config === null || !$config->webhook_enabled) {
            $this->jsonOut(['ok' => false, 'error' => 'webhook_disabled'], 403);

            return;
        }
        if ($secret === '' || !hash_equals((string) $config->webhook_secret, $secret)) {
            $this->jsonOut(['ok' => false, 'error' => 'bad_secret'], 403);

            return;
        }

        $raw = Tools::file_get_contents('php://input');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        try {
            $handler = new CarrefourWebhookHandler($idShop);
            $handler->handle($decoded);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[carrefourmarketplace] webhook error: ' . $e->getMessage(), 2);
            $this->jsonOut(['ok' => false, 'error' => 'handler_failed'], 500);

            return;
        }

        $this->jsonOut(['ok' => true]);
    }

    private function jsonOut(array $payload, $status = 200)
    {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
