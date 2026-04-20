<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourListingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'carrefour_listing';
        $this->className = 'CarrefourListing';
        $this->identifier = 'id_carrefour_listing';
        $this->lang = false;
        $this->allow_export = true;

        parent::__construct();

        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected listings?'),
                'icon' => 'icon-trash',
            ],
        ];
        $this->meta_title = $this->l('Carrefour Marketplace — Listings');

        $this->fields_list = [
            'id_carrefour_listing' => ['title' => $this->l('ID'), 'align' => 'center', 'class' => 'fixed-width-xs'],
            'name' => ['title' => $this->l('Name'), 'filter_key' => 'a!name'],
            'status' => ['title' => $this->l('Status'), 'align' => 'center', 'filter_key' => 'a!status'],
            'price_mode' => ['title' => $this->l('Price mode'), 'align' => 'center'],
            'stock_mode' => ['title' => $this->l('Stock mode'), 'align' => 'center'],
            'date_upd' => ['title' => $this->l('Updated'), 'align' => 'center', 'type' => 'datetime'],
        ];

        /* Only show the current shop's listings. */
        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
            $this->_where = ' AND a.`id_shop` = ' . (int) $this->context->shop->id;
        }

        $this->fields_form = $this->buildFieldsForm();
    }

    public function initContent()
    {
        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
            $this->warnings[] = $this->l('Select a specific shop from the top shop selector to manage its listings.');
        }

        parent::initContent();
    }

    /**
     * Inject id_shop on the ObjectModel during add/update so the row is correctly scoped.
     */
    public function copyFromPost(&$object, $table)
    {
        parent::copyFromPost($object, $table);
        if (empty($object->id_shop)) {
            $object->id_shop = (int) $this->context->shop->id;
        }
    }

    private function buildFieldsForm()
    {
        return [
            'legend' => [
                'title' => $this->l('Listing'),
                'icon' => 'icon-list',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'required' => true,
                    'size' => 40,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Status'),
                    'name' => 'status',
                    'options' => [
                        'query' => [
                            ['id' => 'active', 'name' => $this->l('Active')],
                            ['id' => 'paused', 'name' => $this->l('Paused')],
                            ['id' => 'archived', 'name' => $this->l('Archived')],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Category mapping mode'),
                    'name' => 'category_mapping_mode',
                    'options' => [
                        'query' => [
                            ['id' => 'category_mapping', 'name' => $this->l('Use category mapping table')],
                            ['id' => 'single_category', 'name' => $this->l('All products to one Mirakl category')],
                            ['id' => 'custom_attribute', 'name' => $this->l('From product custom attribute (advanced)')],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Category mapping value'),
                    'name' => 'category_mapping_value',
                    'desc' => $this->l('Mirakl category code (single_category) or PS feature name (custom_attribute). Leave empty when using the mapping table.'),
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Price mode'),
                    'name' => 'price_mode',
                    'options' => [
                        'query' => [
                            ['id' => 'product', 'name' => $this->l('Use PrestaShop product price')],
                            ['id' => 'custom', 'name' => $this->l('Apply variation (below)')],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Price variation operator'),
                    'name' => 'price_variation_operator',
                    'options' => [
                        'query' => [
                            ['id' => 'none', 'name' => $this->l('None')],
                            ['id' => '%_up', 'name' => $this->l('% up')],
                            ['id' => '%_down', 'name' => $this->l('% down')],
                            ['id' => 'fixed_up', 'name' => $this->l('Fixed amount up')],
                            ['id' => 'fixed_down', 'name' => $this->l('Fixed amount down')],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Price variation value'),
                    'name' => 'price_variation_value',
                    'desc' => $this->l('Percentage (e.g. 10 for 10%) or fixed amount depending on the operator.'),
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Stock mode'),
                    'name' => 'stock_mode',
                    'options' => [
                        'query' => [
                            ['id' => 'product', 'name' => $this->l('Use PrestaShop stock')],
                            ['id' => 'custom', 'name' => $this->l('Use custom fixed stock')],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Custom stock value'),
                    'name' => 'stock_custom_value',
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ];
    }
}
