<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourJobsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'carrefour_job';
        $this->className = 'CarrefourJob';
        $this->identifier = 'id_carrefour_job';
        $this->lang = false;
        $this->list_no_link = true;

        parent::__construct();

        $this->addRowAction('delete');
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected jobs?'),
                'icon' => 'icon-trash',
            ],
        ];
        $this->meta_title = $this->l('Carrefour Marketplace — Jobs');

        $this->fields_list = [
            'id_carrefour_job' => ['title' => $this->l('ID'), 'align' => 'center', 'class' => 'fixed-width-xs'],
            'type' => ['title' => $this->l('Type')],
            'status' => ['title' => $this->l('Status'), 'align' => 'center'],
            'priority' => ['title' => $this->l('Priority'), 'align' => 'center'],
            'attempts' => ['title' => $this->l('Attempts'), 'align' => 'center'],
            'mirakl_import_id' => ['title' => $this->l('Import ID')],
            'scheduled_at' => ['title' => $this->l('Scheduled'), 'align' => 'center', 'type' => 'datetime'],
            'completed_at' => ['title' => $this->l('Completed'), 'align' => 'center', 'type' => 'datetime'],
            'last_error_code' => ['title' => $this->l('Error'), 'align' => 'center'],
        ];

        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
            $this->_where = ' AND a.`id_shop` = ' . (int) $this->context->shop->id;
        }
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCarrefourRetryJob')) {
            $this->retryJob((int) Tools::getValue('id_carrefour_job'));
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();
        $this->content = $this->renderRetryPanel() . $this->content;
        $this->context->smarty->assign('content', $this->content);
    }

    private function retryJob($idJob)
    {
        if ($idJob <= 0) {
            return;
        }
        $job = new CarrefourJob($idJob);
        if (!Validate::isLoadedObject($job)) {
            $this->errors[] = $this->l('Job not found.');

            return;
        }
        if ((int) $job->id_shop !== (int) $this->context->shop->id) {
            $this->errors[] = $this->l('Job belongs to another shop.');

            return;
        }
        $job->status = CarrefourJob::STATUS_PENDING;
        $job->attempts = 0;
        $job->scheduled_at = date('Y-m-d H:i:s');
        $job->last_error_code = null;
        $job->last_error_message = null;
        $job->update();
        $this->confirmations[] = $this->l('Job re-queued for immediate retry.');
    }

    private function renderRetryPanel()
    {
        $action = $this->context->link->getAdminLink('AdminCarrefourJobs');
        $token = Tools::getAdminTokenLite('AdminCarrefourJobs');

        return '
<div class="panel">
    <div class="panel-heading"><i class="icon-refresh"></i> ' . $this->l('Retry a failed job') . '</div>
    <form method="post" action="' . $action . '&token=' . $token . '" class="form-inline">
        <label for="carrefour-retry-job-id">' . $this->l('Job ID') . '</label>
        <input type="number" id="carrefour-retry-job-id" name="id_carrefour_job" class="form-control" min="1" style="width: 100px;">
        <button type="submit" name="submitCarrefourRetryJob" class="btn btn-default">
            <i class="icon-refresh"></i> ' . $this->l('Retry now') . '
        </button>
    </form>
</div>';
    }
}
