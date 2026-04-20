<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourConfigController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->className = 'CarrefourShopConfig';
        $this->table = 'carrefour_shop_config';
        $this->lang = false;
        parent::__construct();
        $this->meta_title = $this->l('Carrefour Marketplace — Configuration');
    }

    public function initContent()
    {
        parent::initContent();

        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_ALL) {
            $this->content .= $this->buildMultiShopWarning();
        } else {
            $this->content .= $this->buildConfigForm();
        }

        $this->context->smarty->assign('content', $this->content);
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCarrefourConfig')) {
            $this->saveConfig();
        } elseif (Tools::isSubmit('submitCarrefourTest')) {
            $this->testConnection();
        }
    }

    private function buildMultiShopWarning()
    {
        $message = $this->l('Please select a specific shop from the top shop selector to configure the Carrefour connection for that shop.');

        return '<div class="alert alert-warning">' . $message . '</div>';
    }

    private function buildConfigForm()
    {
        $idShop = (int) $this->context->shop->id;
        $config = CarrefourShopConfig::findForShop($idShop);

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = 'AdminCarrefourConfig';
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->submit_action = 'submitCarrefourConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminCarrefourConfig');
        $helper->token = Tools::getAdminTokenLite('AdminCarrefourConfig');
        $helper->tpl_vars = ['fields_value' => $this->getFormFieldValues($config)];

        $header = '';
        if ($config === null) {
            $notice = $this->l('No configuration yet for this shop. Defaults are pre-filled — fill the API key and save to activate.');
            $header = '<div class="alert alert-info">' . $notice . '</div>';
        }

        return $header
            . $helper->generateForm([$this->getFormDefinition()])
            . $this->buildTestPanel()
            . $this->buildAutofillKiller();
    }

    /**
     * Renders the "Test connection" panel below the main config form.
     * The button POSTs a tiny separate form so the main form values are NOT re-submitted.
     */
    private function buildTestPanel()
    {
        $action = $this->context->link->getAdminLink('AdminCarrefourConfig');
        $token = Tools::getAdminTokenLite('AdminCarrefourConfig');
        $title = $this->l('Test connection');
        $help = $this->l('Calls the Mirakl A01 endpoint with the currently saved credentials to verify they work. Save the form first if you just changed values.');
        $button = $this->l('Test connection');

        return '
<div class="panel" style="margin-top: 20px;">
    <div class="panel-heading"><i class="icon-flash"></i> ' . $title . '</div>
    <p>' . $help . '</p>
    <form method="post" action="' . $action . '&token=' . $token . '" class="form-inline">
        <button type="submit" name="submitCarrefourTest" class="btn btn-primary">
            <i class="icon-flash"></i> ' . $button . '
        </button>
    </form>
</div>';
    }

    /**
     * Prevent browser/password manager from autofilling the API key and shop ID fields
     * when it mistakes this config form for a login form (password input + nearby text input).
     */
    private function buildAutofillKiller()
    {
        return "<script>
(function(){
    function harden() {
        document.querySelectorAll('form').forEach(function(f){ f.setAttribute('autocomplete','off'); });
        var apiKey = document.getElementById('api_key');
        if (apiKey) {
            apiKey.setAttribute('autocomplete','new-password');
            apiKey.setAttribute('data-lpignore','true');
            apiKey.setAttribute('data-form-type','other');
            if (apiKey.value && apiKey.value.length < 40) { apiKey.value=''; }
        }
        var shopId = document.getElementById('shop_id_mirakl');
        if (shopId) {
            shopId.setAttribute('autocomplete','off');
            shopId.setAttribute('data-lpignore','true');
            if (shopId.value && shopId.value.indexOf('@') !== -1) { shopId.value=''; }
        }
    }
    if (document.readyState !== 'loading') { harden(); } else { document.addEventListener('DOMContentLoaded', harden); }
    setTimeout(harden, 100); setTimeout(harden, 500); setTimeout(harden, 1500);
})();
</script>";
    }

    private function getFormDefinition()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Carrefour connection'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('API endpoint'),
                        'name' => 'api_endpoint',
                        'required' => true,
                        'desc' => $this->l('Mirakl API base URL. Default: https://carrefoures-prod.mirakl.net/api'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Sandbox mode'),
                        'name' => 'sandbox_mode',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'sandbox_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'sandbox_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Point to Carrefour preprod environment (carrefoures-preprod.mirakl.net/api) — remember to use your sandbox API key.'),
                    ],
                    [
                        'type' => 'password',
                        'label' => $this->l('API key'),
                        'name' => 'api_key',
                        'desc' => $this->l('Generate it in your Mirakl seller backoffice. Leave empty to keep the currently saved value.'),
                        'required' => false,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Mirakl shop ID'),
                        'name' => 'shop_id_mirakl',
                        'desc' => $this->l('Your shop_id inside the Mirakl marketplace (shown in the seller backoffice).'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Auto-accept incoming orders'),
                        'name' => 'auto_accept_orders',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'autoa_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'autoa_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Default PrestaShop order state'),
                        'name' => 'default_order_state_id',
                        'options' => [
                            'query' => $this->getOrderStateOptions(),
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Stock sync enabled'),
                        'name' => 'stock_sync_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'stock_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'stock_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Stock sync debounce (seconds)'),
                        'name' => 'stock_sync_debounce_seconds',
                        'desc' => $this->l('Rapid stock changes are coalesced within this window.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Price sync enabled'),
                        'name' => 'price_sync_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'price_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'price_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Order sync interval (minutes)'),
                        'name' => 'order_sync_interval_minutes',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('SKU strategy'),
                        'name' => 'sku_strategy',
                        'options' => [
                            'query' => [
                                ['id' => 'attribute_ref_fallback_product', 'name' => $this->l('Attribute reference, fallback to product reference')],
                                ['id' => 'product_ref', 'name' => $this->l('Product reference only')],
                                ['id' => 'ean13', 'name' => $this->l('Product EAN13')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Log level'),
                        'name' => 'log_level',
                        'options' => [
                            'query' => [
                                ['id' => 'debug', 'name' => 'debug'],
                                ['id' => 'info', 'name' => 'info'],
                                ['id' => 'warn', 'name' => 'warn'],
                                ['id' => 'error', 'name' => 'error'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Log retention (days)'),
                        'name' => 'log_retention_days',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
    }

    private function getFormFieldValues(?CarrefourShopConfig $config = null)
    {
        if ($config !== null && (int) $config->id > 0) {
            return [
                'api_endpoint' => (string) $config->api_endpoint,
                'sandbox_mode' => (int) $config->sandbox_mode,
                'api_key' => '',
                'shop_id_mirakl' => (string) $config->shop_id_mirakl,
                'auto_accept_orders' => (int) $config->auto_accept_orders,
                'default_order_state_id' => (int) $config->default_order_state_id,
                'stock_sync_enabled' => (int) $config->stock_sync_enabled,
                'stock_sync_debounce_seconds' => (int) $config->stock_sync_debounce_seconds,
                'price_sync_enabled' => (int) $config->price_sync_enabled,
                'order_sync_interval_minutes' => (int) $config->order_sync_interval_minutes,
                'sku_strategy' => (string) $config->sku_strategy,
                'log_level' => (string) $config->log_level,
                'log_retention_days' => (int) $config->log_retention_days,
            ];
        }

        return [
            'api_endpoint' => 'https://carrefoures-prod.mirakl.net/api',
            'sandbox_mode' => 1,
            'api_key' => '',
            'shop_id_mirakl' => '',
            'auto_accept_orders' => 0,
            'default_order_state_id' => 0,
            'stock_sync_enabled' => 1,
            'stock_sync_debounce_seconds' => 30,
            'price_sync_enabled' => 1,
            'order_sync_interval_minutes' => 15,
            'sku_strategy' => 'attribute_ref_fallback_product',
            'log_level' => 'info',
            'log_retention_days' => 30,
        ];
    }

    private function getOrderStateOptions()
    {
        $options = [['id_order_state' => 0, 'name' => $this->l('(Use first unpaid state at order time)')]];
        $states = OrderState::getOrderStates((int) $this->context->language->id);
        if (is_array($states)) {
            foreach ($states as $state) {
                $options[] = [
                    'id_order_state' => (int) $state['id_order_state'],
                    'name' => (string) $state['name'],
                ];
            }
        }

        return $options;
    }

    private function saveConfig()
    {
        $idShop = (int) $this->context->shop->id;
        if ($idShop === 0) {
            $this->errors[] = $this->l('Please select a specific shop first.');

            return;
        }

        $config = CarrefourShopConfig::findForShop($idShop);
        if ($config === null) {
            $config = new CarrefourShopConfig();
            $config->id_shop = $idShop;
        }

        $config->api_endpoint = trim((string) Tools::getValue('api_endpoint'));
        $config->sandbox_mode = (bool) Tools::getValue('sandbox_mode');

        $submittedShopId = trim((string) Tools::getValue('shop_id_mirakl'));
        if (strpos($submittedShopId, '@') !== false) {
            $this->errors[] = $this->l('Mirakl shop ID cannot contain "@". Looks like your browser autofilled the field with an email — clear it and re-enter the shop ID from your Mirakl backoffice.');

            return;
        }
        $config->shop_id_mirakl = $submittedShopId;
        $config->auto_accept_orders = (bool) Tools::getValue('auto_accept_orders');

        $orderStateId = (int) Tools::getValue('default_order_state_id');
        $config->default_order_state_id = $orderStateId > 0 ? $orderStateId : null;

        $config->stock_sync_enabled = (bool) Tools::getValue('stock_sync_enabled');
        $config->stock_sync_debounce_seconds = max(1, (int) Tools::getValue('stock_sync_debounce_seconds'));
        $config->price_sync_enabled = (bool) Tools::getValue('price_sync_enabled');
        $config->order_sync_interval_minutes = max(1, (int) Tools::getValue('order_sync_interval_minutes'));
        $config->sku_strategy = (string) Tools::getValue('sku_strategy', 'attribute_ref_fallback_product');
        $config->log_level = (string) Tools::getValue('log_level', 'info');
        $config->log_retention_days = max(1, (int) Tools::getValue('log_retention_days'));

        $newApiKey = (string) Tools::getValue('api_key');
        if ($newApiKey !== '') {
            $config->setApiKey($newApiKey);
        }

        $ok = $config->id ? $config->update() : $config->add();

        if ($ok) {
            $this->confirmations[] = $this->l('Configuration saved.');
        } else {
            $this->errors[] = $this->l('Could not save configuration.');
        }
    }

    /**
     * Runs the Mirakl A01 (GET /account) call against the currently saved config.
     */
    private function testConnection()
    {
        $idShop = (int) $this->context->shop->id;
        $config = CarrefourShopConfig::findForShop($idShop);
        if ($config === null || (int) $config->id === 0) {
            $this->errors[] = $this->l('Save your configuration first, then run the test.');

            return;
        }

        $apiKey = $config->getApiKey();
        if ($apiKey === '' || $apiKey === null) {
            $this->errors[] = $this->l('API key is empty. Save a key first, then run the test.');

            return;
        }

        try {
            $logger = new CarrefourLogger($idShop, 'api', (string) $config->log_level);
            $client = new MiraklClient(
                (string) $config->api_endpoint,
                $apiKey,
                $config->shop_id_mirakl,
                $logger
            );
            $result = $client->testConnection();
            $summary = $this->formatTestSummary(is_array($result) ? $result : []);
            $this->confirmations[] = $this->l('Mirakl connection OK.') . ' ' . $summary;
        } catch (MiraklAuthException $e) {
            $this->errors[] = sprintf(
                '%s (HTTP %d). %s',
                $this->l('Authentication failed — check your API key.'),
                $e->getStatusCode(),
                $e->getMessage()
            );
        } catch (MiraklNotFoundException $e) {
            $this->errors[] = sprintf(
                '%s (HTTP %d). %s',
                $this->l('Endpoint not found — check the API URL.'),
                $e->getStatusCode(),
                $e->getMessage()
            );
        } catch (MiraklNetworkException $e) {
            $this->errors[] = $this->l('Network error:') . ' ' . $e->getMessage();
        } catch (MiraklException $e) {
            $this->errors[] = sprintf(
                '%s %s (HTTP %d)',
                $this->l('Mirakl error:'),
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            $this->errors[] = $this->l('Unexpected error:') . ' ' . $e->getMessage();
        }
    }

    private function formatTestSummary(array $result)
    {
        if (empty($result)) {
            return '';
        }
        $interesting = ['shop_id', 'shop_name', 'shop_login', 'currency_iso_code', 'is_professional', 'state'];
        $parts = [];
        foreach ($interesting as $key) {
            if (array_key_exists($key, $result) && is_scalar($result[$key])) {
                $parts[] = $key . '=' . (string) $result[$key];
            }
        }
        if (!empty($parts)) {
            return '[' . implode(', ', $parts) . ']';
        }

        return '(response received but unrecognized shape)';
    }
}
