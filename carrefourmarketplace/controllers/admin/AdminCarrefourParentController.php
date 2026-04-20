<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourParentController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminCarrefourConfig')
        );
    }
}
