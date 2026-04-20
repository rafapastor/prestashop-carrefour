<?php
/**
 * Carrefour Marketplace Connector for PrestaShop.
 *
 * @author    Rafael Pastor
 * @copyright 2026 Rafael Pastor
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License v3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/* Simple PSR-4-ish autoloader for module classes under classes/ subfolders */
spl_autoload_register(function ($class) {
    if (strpos($class, 'Carrefour') !== 0 && strpos($class, 'Mirakl') !== 0) {
        return;
    }
    $base = __DIR__ . '/classes/';
    static $dirs = ['Model/', 'Logger/', 'Util/', 'Api/', 'Service/', 'Queue/', 'Queue/Jobs/'];
    foreach ($dirs as $dir) {
        $file = $base . $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;

            return;
        }
    }
});

class Carrefourmarketplace extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'carrefourmarketplace';
        $this->tab = 'market_place';
        $this->version = '0.1.0';
        $this->author = 'Rafael Pastor';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.6.0.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->getModuleTranslation('Carrefour Marketplace Connector');
        $this->description = $this->getModuleTranslation(
            'Open-source connector between PrestaShop and Carrefour Hub Spain (Mirakl). Upload your catalog, sync stock in real time, pull orders into PrestaShop.'
        );
        $this->confirmUninstall = $this->getModuleTranslation(
            'Uninstall this module? Your Carrefour configuration, job queue and sync logs will be removed. Imported orders stay in PrestaShop.'
        );
    }

    public function isUsingNewTranslationSystem()
    {
        return version_compare(_PS_VERSION_, '1.7.8.0', '>=');
    }

    /**
     * Hybrid translator: trans() on PS 1.7.8+, legacy l() otherwise.
     */
    public function getModuleTranslation($string, $params = [], $domain = 'Modules.Carrefourmarketplace.Admin')
    {
        if (version_compare(_PS_VERSION_, '1.7.8.0', '>=') && method_exists($this, 'trans')) {
            return $this->trans($string, $params, $domain);
        }

        return $this->l($string);
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (version_compare(_PS_VERSION_, '1.6.0.0', '<')) {
            $this->_errors[] = $this->getModuleTranslation('This module requires PrestaShop 1.6.0 or higher.');

            return false;
        }

        if (!parent::install()) {
            return false;
        }

        if (!$this->installSchema()) {
            $this->_errors[] = $this->getModuleTranslation('Failed to create database tables.');

            return false;
        }

        if (!$this->installHooks()) {
            $this->_errors[] = $this->getModuleTranslation('Failed to register hooks.');

            return false;
        }

        if (!$this->installTabs()) {
            $this->_errors[] = $this->getModuleTranslation('Failed to create admin tabs.');

            return false;
        }

        return true;
    }

    public function uninstall()
    {
        $this->uninstallTabs();
        $this->uninstallSchema();

        return parent::uninstall();
    }

    /* -------------------------------------------------------------
     * Install / uninstall helpers
     * ----------------------------------------------------------- */

    private function installSchema()
    {
        include dirname(__FILE__) . '/sql/install.php';

        return true;
    }

    private function uninstallSchema()
    {
        include dirname(__FILE__) . '/sql/uninstall.php';

        return true;
    }

    private function installHooks()
    {
        return $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionUpdateQuantity')
            && $this->registerHook('actionOrderStatusPostUpdate');
    }

    private function installTabs()
    {
        /* Parent tab (top-level menu entry) */
        $parent = new Tab();
        $parent->class_name = 'AdminCarrefourParent';
        $parent->module = $this->name;
        $parent->active = 1;
        $parent->id_parent = 0;
        if (property_exists($parent, 'icon')) {
            $parent->icon = 'shopping_cart';
        }
        $parent->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $parent->name[(int) $lang['id_lang']] = 'Carrefour Marketplace';
        }
        if (!$parent->add()) {
            return false;
        }

        $children = [
            'AdminCarrefourConfig' => 'Configuration',
            'AdminCarrefourListings' => 'Listings',
            'AdminCarrefourOffers' => 'Offers',
            'AdminCarrefourOrders' => 'Orders',
            'AdminCarrefourCategories' => 'Category mapping',
            'AdminCarrefourJobs' => 'Jobs',
            'AdminCarrefourLogs' => 'Logs',
        ];
        foreach ($children as $class => $label) {
            $tab = new Tab();
            $tab->class_name = $class;
            $tab->module = $this->name;
            $tab->active = 1;
            $tab->id_parent = (int) $parent->id;
            $tab->name = [];
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[(int) $lang['id_lang']] = $label;
            }
            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    private function uninstallTabs()
    {
        $classNames = [
            'AdminCarrefourConfig',
            'AdminCarrefourListings',
            'AdminCarrefourOffers',
            'AdminCarrefourOrders',
            'AdminCarrefourCategories',
            'AdminCarrefourJobs',
            'AdminCarrefourLogs',
            'AdminCarrefourParent',
        ];
        foreach ($classNames as $class) {
            $idTab = (int) Tab::getIdFromClassName($class);
            if ($idTab > 0) {
                $tab = new Tab($idTab);
                $tab->delete();
            }
        }

        return true;
    }

    /* -------------------------------------------------------------
     * Hook handlers
     * Phase 1 registers the hooks but handlers are stubs; logic lands in later phases.
     * ----------------------------------------------------------- */

    public function hookDisplayBackOfficeHeader($params)
    {
        /* Inject module CSS only on our own admin controllers. */
        $controller = Tools::getValue('controller');
        if (is_string($controller) && strpos($controller, 'AdminCarrefour') === 0) {
            $cssPath = _PS_MODULE_DIR_ . $this->name . '/views/css/admin.css';
            $jsPath = _PS_MODULE_DIR_ . $this->name . '/views/js/admin.js';
            if (is_file($cssPath) && method_exists($this->context->controller, 'addCSS')) {
                $this->context->controller->addCSS(_MODULE_DIR_ . $this->name . '/views/css/admin.css', 'all');
            }
            if (is_file($jsPath) && method_exists($this->context->controller, 'addJS')) {
                $this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/views/js/admin.js');
            }
        }
    }

    public function hookActionUpdateQuantity($params)
    {
        if (!isset($params['id_product'])) {
            return;
        }
        $idShop = (int) $this->context->shop->id;
        if ($idShop === 0) {
            return;
        }

        try {
            $service = new CarrefourStockService();
            $service->onStockChange(
                $idShop,
                (int) $params['id_product'],
                isset($params['id_product_attribute']) ? (int) $params['id_product_attribute'] : 0
            );
        } catch (\Exception $e) {
            /* Never let stock-sync errors break a PS stock update path. */
            PrestaShopLogger::addLog('[carrefourmarketplace] hookActionUpdateQuantity: ' . $e->getMessage(), 2);
        }
    }

    /**
     * Safety net: we extend PaymentModule so we can call validateOrder() when
     * creating PS orders from Mirakl orders, but we must never appear as a
     * checkout option for front-office customers. If PS ever calls this hook
     * (which would only happen if we had registered it, but belt-and-braces),
     * return an empty array so no PaymentOption is offered.
     */
    public function hookPaymentOptions($params)
    {
        return [];
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        if (!isset($params['id_order'])) {
            return;
        }
        $idOrder = (int) $params['id_order'];
        $newStatus = isset($params['newOrderStatus']) ? $params['newOrderStatus'] : null;
        if (!$newStatus || !isset($newStatus->id)) {
            return;
        }

        $idCarrefourOrder = (int) Db::getInstance()->getValue(sprintf(
            'SELECT `id_carrefour_order` FROM `%scarrefour_order` WHERE `id_order` = %d',
            _DB_PREFIX_,
            $idOrder
        ));
        if ($idCarrefourOrder === 0) {
            return; /* not a Mirakl-sourced order */
        }

        $carrefourOrder = new CarrefourOrder($idCarrefourOrder);
        if (!Validate::isLoadedObject($carrefourOrder)) {
            return;
        }

        try {
            $queue = new CarrefourJobQueue((int) $carrefourOrder->id_shop);
            if ((int) $newStatus->shipped === 1) {
                $order = new Order($idOrder);
                $tracking = '';
                $carrier = '';
                if (Validate::isLoadedObject($order)) {
                    $tracking = (string) $order->shipping_number;
                    if ((int) $order->id_carrier > 0) {
                        $carrierObj = new Carrier((int) $order->id_carrier);
                        if (Validate::isLoadedObject($carrierObj)) {
                            $carrier = (string) $carrierObj->name;
                        }
                    }
                }
                $queue->enqueue('order_ship', [
                    'id_carrefour_order' => $idCarrefourOrder,
                    'tracking_number' => $tracking,
                    'carrier_name' => $carrier,
                ]);
            }
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[carrefourmarketplace] hookActionOrderStatusPostUpdate: ' . $e->getMessage(), 2);
        }
    }

    /* -------------------------------------------------------------
     * Config entry point (clicking the module in "Module Manager")
     * ----------------------------------------------------------- */

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminCarrefourConfig'));
    }
}
