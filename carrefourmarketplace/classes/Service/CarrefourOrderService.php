<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Pulls Mirakl orders (OR11 paginated) and mirrors them into carrefour_order / _order_line.
 * Optionally creates a matching PrestaShop order (via PaymentModule::validateOrder) when
 * the operation succeeds. Mirror is always written, PS order creation is best-effort.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourOrderService
{
    /** @var int */
    private $idShop;

    /** @var CarrefourShopConfig */
    private $config;

    /** @var MiraklClient */
    private $client;

    /** @var CarrefourLogger */
    private $logger;

    public function __construct($idShop, MiraklClient $client, CarrefourShopConfig $config, ?CarrefourLogger $logger = null)
    {
        $this->idShop = (int) $idShop;
        $this->config = $config;
        $this->client = $client;
        $this->logger = $logger ?: new CarrefourLogger($this->idShop, 'orders', (string) $config->log_level);
    }

    /**
     * Pull orders updated since a given datetime and mirror them.
     * Returns ['pulled' => N, 'created_ps_orders' => M, 'errors' => E].
     */
    public function pullRecentOrders($sinceIso)
    {
        $offset = 0;
        $limit = 50;
        $pulled = 0;
        $createdPsOrders = 0;
        $errors = 0;

        while (true) {
            $query = [
                'start_update_date' => $sinceIso,
                'offset' => $offset,
                'max' => $limit,
            ];
            if (!empty($this->config->shop_id_mirakl)) {
                $query['shop_ids'] = (string) $this->config->shop_id_mirakl;
            }

            $response = $this->client->get('/orders', $query);
            $decoded = is_array($response['decoded']) ? $response['decoded'] : [];
            $orders = isset($decoded['orders']) && is_array($decoded['orders']) ? $decoded['orders'] : [];
            if (empty($orders)) {
                break;
            }

            foreach ($orders as $miraklOrder) {
                try {
                    $carrefourOrderId = $this->upsertCarrefourOrder($miraklOrder);
                    ++$pulled;
                    if ($carrefourOrderId > 0 && $this->shouldCreatePsOrder($miraklOrder)) {
                        if ($this->maybeCreatePsOrder($carrefourOrderId, $miraklOrder)) {
                            ++$createdPsOrders;
                        }
                    }
                } catch (\Exception $e) {
                    ++$errors;
                    $this->logger->error('order.import_failed', [
                        'order_id_mirakl' => $miraklOrder['order_id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (count($orders) < $limit) {
                break;
            }
            $offset += $limit;
        }

        return ['pulled' => $pulled, 'created_ps_orders' => $createdPsOrders, 'errors' => $errors];
    }

    /**
     * Accept or refuse order lines on Mirakl (OR21).
     */
    public function acceptOrder($orderIdMirakl, array $lineAcceptances)
    {
        $body = ['order_lines' => []];
        foreach ($lineAcceptances as $lineId => $accepted) {
            $body['order_lines'][] = [
                'id' => (string) $lineId,
                'accepted' => (bool) $accepted,
            ];
        }
        $query = [];
        if (!empty($this->config->shop_id_mirakl)) {
            $query['shop_id'] = (string) $this->config->shop_id_mirakl;
        }
        $this->client->put('/orders/' . rawurlencode((string) $orderIdMirakl) . '/accept', $body, $query);
    }

    /**
     * Ship order and push tracking (OR23 + OR24 combined).
     */
    public function shipOrder($orderIdMirakl, $trackingNumber, $carrierName)
    {
        $query = [];
        if (!empty($this->config->shop_id_mirakl)) {
            $query['shop_id'] = (string) $this->config->shop_id_mirakl;
        }
        /* OR23 — update tracking */
        if ($trackingNumber !== '' || $carrierName !== '') {
            $this->client->put(
                '/orders/' . rawurlencode((string) $orderIdMirakl) . '/tracking',
                [
                    'tracking_number' => (string) $trackingNumber,
                    'carrier_name' => (string) $carrierName,
                ],
                $query
            );
        }
        /* OR24 — confirm shipment */
        $this->client->put('/orders/' . rawurlencode((string) $orderIdMirakl) . '/ship', [], $query);
    }

    /* ---------------- internals ---------------- */

    private function upsertCarrefourOrder(array $m)
    {
        $orderIdMirakl = (string) ($m['order_id'] ?? '');
        if ($orderIdMirakl === '') {
            throw new \RuntimeException('Mirakl payload missing order_id');
        }

        $existingId = (int) Db::getInstance()->getValue(sprintf(
            'SELECT `id_carrefour_order` FROM `%scarrefour_order`
             WHERE `id_shop` = %d AND `order_id_mirakl` = "%s"',
            _DB_PREFIX_,
            $this->idShop,
            pSQL($orderIdMirakl)
        ));

        $order = $existingId > 0 ? new CarrefourOrder($existingId) : new CarrefourOrder();
        $order->id_shop = $this->idShop;
        $order->order_id_mirakl = $orderIdMirakl;
        $order->commercial_id = (string) ($m['commercial_id'] ?? '');
        $order->state = (string) ($m['order_state'] ?? 'UNKNOWN');
        $order->payment_type = (string) ($m['payment_type'] ?? '');
        $order->total_price = (float) ($m['price'] ?? 0);
        $order->currency_iso_code = (string) ($m['currency_iso_code'] ?? 'EUR');
        $order->customer_email = (string) ($m['customer']['email'] ?? '');
        $order->raw_payload = json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $order->shipping_deadline = isset($m['shipping_deadline']) ? $this->miraklDate($m['shipping_deadline']) : null;
        $order->created_date_mirakl = isset($m['created_date']) ? $this->miraklDate($m['created_date']) : null;
        $order->last_synced_at = date('Y-m-d H:i:s');

        $ok = $existingId > 0 ? $order->update() : $order->add();
        if (!$ok) {
            throw new \RuntimeException('Failed to upsert carrefour_order');
        }
        $carrefourOrderId = (int) $order->id;

        /* Mirror lines */
        if (isset($m['order_lines']) && is_array($m['order_lines'])) {
            foreach ($m['order_lines'] as $line) {
                $this->upsertOrderLine($carrefourOrderId, $line);
            }
        }

        return $carrefourOrderId;
    }

    private function upsertOrderLine($idCarrefourOrder, array $line)
    {
        $lineId = (string) ($line['order_line_id'] ?? '');
        if ($lineId === '') {
            return;
        }
        $existingId = (int) Db::getInstance()->getValue(sprintf(
            'SELECT `id_carrefour_order_line` FROM `%scarrefour_order_line`
             WHERE `id_carrefour_order` = %d AND `order_line_id_mirakl` = "%s"',
            _DB_PREFIX_,
            (int) $idCarrefourOrder,
            pSQL($lineId)
        ));
        $orderLine = $existingId > 0 ? new CarrefourOrderLine($existingId) : new CarrefourOrderLine();
        $orderLine->id_carrefour_order = (int) $idCarrefourOrder;
        $orderLine->order_line_id_mirakl = $lineId;
        $orderLine->offer_sku = (string) ($line['offer_sku'] ?? '');
        $orderLine->product_sku_mirakl = (string) ($line['product_sku'] ?? '');
        $orderLine->product_title = (string) ($line['product_title'] ?? '');
        $orderLine->quantity = (int) ($line['quantity'] ?? 0);
        $orderLine->unit_price = (float) ($line['price_unit'] ?? ($line['price'] ?? 0));
        $orderLine->total_price = (float) ($line['total_price'] ?? ($line['price'] ?? 0));
        $orderLine->shipping_price = (float) ($line['shipping_price'] ?? 0);
        $orderLine->commission_amount = (float) ($line['total_commission'] ?? 0);
        $orderLine->line_state = (string) ($line['order_line_state'] ?? '');
        $existingId > 0 ? $orderLine->update() : $orderLine->add();
    }

    private function shouldCreatePsOrder(array $m)
    {
        /* We create a PS order only once we have the data we need (customer, shipping address). */
        if (!isset($m['customer']) || !isset($m['customer']['email']) || !isset($m['customer']['shipping_address'])) {
            return false;
        }

        return true;
    }

    private function maybeCreatePsOrder($idCarrefourOrder, array $m)
    {
        $order = new CarrefourOrder($idCarrefourOrder);
        if (!Validate::isLoadedObject($order) || (int) $order->id_order > 0) {
            return false; /* already has a PS order */
        }

        try {
            $customer = $this->getOrCreateGuestCustomer($m['customer']);
            $shipping = $this->createAddress($customer, $m['customer']['shipping_address'], 'Mirakl shipping');
            $billing = $this->createAddress(
                $customer,
                $m['customer']['billing_address'] ?? $m['customer']['shipping_address'],
                'Mirakl billing'
            );

            $cart = new Cart();
            $cart->id_shop = $this->idShop;
            $cart->id_shop_group = (int) Shop::getShop($this->idShop)['id_shop_group'];
            $cart->id_customer = (int) $customer->id;
            $cart->id_address_delivery = (int) $shipping->id;
            $cart->id_address_invoice = (int) $billing->id;
            $cart->id_currency = $this->resolveCurrencyId((string) $order->currency_iso_code);
            $cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            $cart->id_carrier = $this->resolveDefaultCarrierId();
            $cart->secure_key = md5(uniqid((string) mt_rand(), true));
            $cart->add();

            $this->addLinesToCart($cart, $m['order_lines'] ?? []);

            $module = Module::getInstanceByName('carrefourmarketplace');
            if (!$module instanceof PaymentModule) {
                throw new \RuntimeException('carrefourmarketplace main module is not a PaymentModule instance');
            }

            $orderStateId = (int) $this->config->default_order_state_id ?: (int) Configuration::get('PS_OS_PAYMENT');

            $module->validateOrder(
                (int) $cart->id,
                $orderStateId,
                (float) $order->total_price,
                $this->payMethodLabel(),
                null,
                ['transaction_id' => (string) $order->order_id_mirakl],
                $cart->id_currency,
                false,
                $cart->secure_key
            );

            $idOrder = (int) $module->currentOrder;
            if ($idOrder > 0) {
                $order->id_order = $idOrder;
                $order->update();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('order.ps_create_failed', [
                'order_id_mirakl' => (string) $order->order_id_mirakl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getOrCreateGuestCustomer(array $customer)
    {
        $email = (string) $customer['email'];
        $existingId = (int) Db::getInstance()->getValue(sprintf(
            'SELECT `id_customer` FROM `%scustomer`
             WHERE `email` = "%s" AND `is_guest` = 1 LIMIT 1',
            _DB_PREFIX_,
            pSQL($email)
        ));
        if ($existingId > 0) {
            $c = new Customer($existingId);
            if (Validate::isLoadedObject($c)) {
                return $c;
            }
        }
        $c = new Customer();
        $c->firstname = mb_substr((string) ($customer['firstname'] ?? 'Mirakl'), 0, 32);
        $c->lastname = mb_substr((string) ($customer['lastname'] ?? 'Customer'), 0, 32);
        $c->email = $email;
        $c->is_guest = 1;
        $c->active = 1;
        $c->passwd = Tools::passwdGen(12);
        $c->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $c->id_shop = $this->idShop;
        $shop = Shop::getShop($this->idShop);
        $c->id_shop_group = isset($shop['id_shop_group']) ? (int) $shop['id_shop_group'] : 1;
        $c->add();

        return $c;
    }

    private function createAddress(Customer $customer, array $addr, $alias)
    {
        $a = new Address();
        $a->id_customer = (int) $customer->id;
        $a->firstname = mb_substr((string) ($addr['firstname'] ?? $customer->firstname), 0, 32);
        $a->lastname = mb_substr((string) ($addr['lastname'] ?? $customer->lastname), 0, 32);
        $a->address1 = mb_substr((string) ($addr['street_1'] ?? ''), 0, 128);
        $a->address2 = mb_substr((string) ($addr['street_2'] ?? ''), 0, 128);
        $a->postcode = mb_substr((string) ($addr['zip_code'] ?? ''), 0, 12);
        $a->city = mb_substr((string) ($addr['city'] ?? ''), 0, 64);
        $a->phone = mb_substr((string) ($addr['phone'] ?? ''), 0, 32);

        $iso = strtoupper((string) ($addr['country_iso_code'] ?? 'ES'));
        if (strlen($iso) === 3) {
            /* Mirakl sends ISO 3166-1 alpha-3; PS Country::getByIso expects alpha-2. */
            $iso = $this->iso3ToIso2($iso);
        }
        $a->id_country = (int) Country::getByIso($iso ?: 'ES');
        if ((int) $a->id_country === 0) {
            $a->id_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        }
        $a->alias = mb_substr((string) $alias, 0, 32);
        $a->add();

        return $a;
    }

    private function iso3ToIso2($iso3)
    {
        static $map = [
            'ESP' => 'ES', 'FRA' => 'FR', 'ITA' => 'IT', 'PRT' => 'PT', 'DEU' => 'DE',
            'GBR' => 'GB', 'NLD' => 'NL', 'BEL' => 'BE', 'USA' => 'US', 'IRL' => 'IE',
            'AUT' => 'AT', 'POL' => 'PL', 'GRC' => 'GR', 'SWE' => 'SE', 'DNK' => 'DK',
        ];

        return $map[strtoupper($iso3)] ?? null;
    }

    private function resolveCurrencyId($isoCode)
    {
        $id = (int) Currency::getIdByIsoCode((string) $isoCode, $this->idShop);
        if ($id > 0) {
            return $id;
        }

        return (int) Configuration::get('PS_CURRENCY_DEFAULT');
    }

    private function resolveDefaultCarrierId()
    {
        $carriers = Carrier::getCarriers((int) Configuration::get('PS_LANG_DEFAULT'), true, false, false, null, Carrier::ALL_CARRIERS);
        if (is_array($carriers) && !empty($carriers)) {
            return (int) $carriers[0]['id_carrier'];
        }

        return 0;
    }

    private function addLinesToCart(Cart $cart, array $miraklLines)
    {
        foreach ($miraklLines as $line) {
            $sku = (string) ($line['offer_sku'] ?? '');
            $qty = (int) ($line['quantity'] ?? 1);
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $match = $this->findPsProductBySku($sku);
            if ($match === null) {
                continue; /* Unknown product — mirror exists in carrefour_order_line but not in PS cart */
            }
            $cart->updateQty($qty, (int) $match['id_product'], (int) $match['id_product_attribute'] ?: null);
        }
    }

    private function findPsProductBySku($sku)
    {
        $row = Db::getInstance()->getRow(sprintf(
            'SELECT `id_product`, `id_product_attribute` FROM `%scarrefour_offer`
             WHERE `id_shop` = %d AND `sku` = "%s" LIMIT 1',
            _DB_PREFIX_,
            $this->idShop,
            pSQL($sku)
        ));

        return is_array($row) && !empty($row['id_product']) ? $row : null;
    }

    private function payMethodLabel()
    {
        return 'Carrefour Marketplace';
    }

    private function miraklDate($val)
    {
        if (!is_string($val) || $val === '') {
            return null;
        }
        $ts = strtotime($val);

        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
