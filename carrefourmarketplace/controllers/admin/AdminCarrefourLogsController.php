<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourLogsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'carrefour_log';
        $this->className = 'CarrefourLog';
        $this->identifier = 'id_carrefour_log';
        $this->lang = false;
        $this->list_no_link = true;
        $this->_orderBy = 'id_carrefour_log';
        $this->_orderWay = 'DESC';

        parent::__construct();

        $this->addRowAction('delete');
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected log entries?'),
                'icon' => 'icon-trash',
            ],
        ];
        $this->meta_title = $this->l('Carrefour Marketplace — Logs');

        $this->fields_list = [
            'id_carrefour_log' => ['title' => $this->l('ID'), 'align' => 'center', 'class' => 'fixed-width-xs'],
            'date_add' => ['title' => $this->l('Time'), 'align' => 'center', 'type' => 'datetime'],
            'level' => ['title' => $this->l('Level'), 'align' => 'center'],
            'channel' => ['title' => $this->l('Channel'), 'align' => 'center'],
            'message' => ['title' => $this->l('Message')],
            'id_job' => ['title' => $this->l('Job'), 'align' => 'center'],
        ];

        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
            $this->_where = ' AND (a.`id_shop` = ' . (int) $this->context->shop->id . ' OR a.`id_shop` IS NULL)';
        }
    }
}
