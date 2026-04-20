<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourOrdersController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'carrefour_order';
        $this->className = 'CarrefourOrder';
        $this->identifier = 'id_carrefour_order';
        $this->lang = false;
        $this->list_no_link = true;

        parent::__construct();
        $this->meta_title = $this->l('Carrefour Marketplace — Orders');

        $this->fields_list = [
            'id_carrefour_order' => ['title' => $this->l('ID'), 'align' => 'center', 'class' => 'fixed-width-xs'],
            'order_id_mirakl' => ['title' => $this->l('Mirakl order ID')],
            'commercial_id' => ['title' => $this->l('Commercial ID')],
            'state' => ['title' => $this->l('State'), 'align' => 'center'],
            'total_price' => ['title' => $this->l('Total'), 'align' => 'right', 'type' => 'price'],
            'currency_iso_code' => ['title' => $this->l('Currency'), 'align' => 'center'],
            'customer_email' => ['title' => $this->l('Customer')],
            'id_order' => ['title' => $this->l('PS order'), 'align' => 'center'],
            'shipping_deadline' => ['title' => $this->l('Ship by'), 'align' => 'center', 'type' => 'datetime'],
            'last_synced_at' => ['title' => $this->l('Synced'), 'align' => 'center', 'type' => 'datetime'],
        ];

        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
            $this->_where = ' AND a.`id_shop` = ' . (int) $this->context->shop->id;
        }
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCarrefourPullNow')) {
            $this->schedulePullNow();
        } elseif (Tools::isSubmit('submitCarrefourAccept')) {
            $this->scheduleAccept((int) Tools::getValue('id_carrefour_order'));
        } elseif (Tools::isSubmit('submitCarrefourShip')) {
            $this->scheduleShip((int) Tools::getValue('id_carrefour_order'));
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();
        $this->content = $this->renderActionsPanel() . $this->content;
        $this->context->smarty->assign('content', $this->content);
    }

    private function schedulePullNow()
    {
        $idShop = (int) $this->context->shop->id;

        try {
            $queue = new CarrefourJobQueue($idShop);
            $jobId = $queue->enqueue('order_sync', ['since' => null]);
            $this->confirmations[] = sprintf('%s #%d', $this->l('Order sync enqueued'), $jobId);
        } catch (\Exception $e) {
            $this->errors[] = $this->l('Failed to enqueue:') . ' ' . $e->getMessage();
        }
    }

    private function scheduleAccept($idCarrefourOrder)
    {
        if ($idCarrefourOrder <= 0) {
            return;
        }
        $idShop = (int) $this->context->shop->id;

        try {
            $queue = new CarrefourJobQueue($idShop);
            $queue->enqueue('order_accept', ['id_carrefour_order' => $idCarrefourOrder, 'line_acceptances' => []]);
            $this->confirmations[] = $this->l('Accept job enqueued for order #') . $idCarrefourOrder;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    private function scheduleShip($idCarrefourOrder)
    {
        if ($idCarrefourOrder <= 0) {
            return;
        }
        $idShop = (int) $this->context->shop->id;

        try {
            $queue = new CarrefourJobQueue($idShop);
            $queue->enqueue('order_ship', [
                'id_carrefour_order' => $idCarrefourOrder,
                'tracking_number' => (string) Tools::getValue('tracking_number'),
                'carrier_name' => (string) Tools::getValue('carrier_name'),
            ]);
            $this->confirmations[] = $this->l('Ship job enqueued for order #') . $idCarrefourOrder;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    private function renderActionsPanel()
    {
        $action = $this->context->link->getAdminLink('AdminCarrefourOrders');
        $token = Tools::getAdminTokenLite('AdminCarrefourOrders');

        $pullForm = '
<div class="panel">
    <div class="panel-heading"><i class="icon-download"></i> ' . $this->l('Order sync') . '</div>
    <p>' . $this->l('Pull recent orders from Mirakl. Normally runs on a schedule; this forces an immediate pull.') . '</p>
    <form method="post" action="' . $action . '&token=' . $token . '">
        <button type="submit" name="submitCarrefourPullNow" class="btn btn-primary">
            <i class="icon-download"></i> ' . $this->l('Pull orders now') . '
        </button>
    </form>
</div>';

        $actionsForm = '
<div class="panel">
    <div class="panel-heading"><i class="icon-check"></i> ' . $this->l('Per-order actions') . '</div>
    <form method="post" action="' . $action . '&token=' . $token . '" class="form-horizontal">
        <div class="form-group">
            <label class="control-label col-lg-3" for="carrefour-order-id">' . $this->l('Carrefour order ID') . '</label>
            <div class="col-lg-4"><input type="number" id="carrefour-order-id" name="id_carrefour_order" class="form-control" min="1"></div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3" for="carrefour-tracking">' . $this->l('Tracking number (for ship)') . '</label>
            <div class="col-lg-4"><input type="text" id="carrefour-tracking" name="tracking_number" class="form-control"></div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3" for="carrefour-carrier">' . $this->l('Carrier name (for ship)') . '</label>
            <div class="col-lg-4"><input type="text" id="carrefour-carrier" name="carrier_name" class="form-control"></div>
        </div>
        <div class="form-group">
            <div class="col-lg-9 col-lg-offset-3">
                <button type="submit" name="submitCarrefourAccept" class="btn btn-default">
                    <i class="icon-check"></i> ' . $this->l('Accept all lines') . '
                </button>
                <button type="submit" name="submitCarrefourShip" class="btn btn-default">
                    <i class="icon-truck"></i> ' . $this->l('Mark shipped') . '
                </button>
            </div>
        </div>
    </form>
</div>';

        return $pullForm . $actionsForm;
    }
}
