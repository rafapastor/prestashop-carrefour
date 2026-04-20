<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Two surfaces in one screen:
 *  - Button to refresh the Mirakl H11 tree cache.
 *  - Panel listing PS root categories, each with an input to bind to a Mirakl category code.
 *  - The cached tree is shown below as reference (collapsible) for easy code lookup.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourCategoriesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->lang = false;

        parent::__construct();

        $this->meta_title = $this->l('Carrefour Marketplace — Category mapping');
        /* Categories controller has no table/className because the standard CRUD grid is not used.
           Initialize empty fields_list/bulk_actions to avoid PS-8 list-rendering hiccups. */
        $this->table = 'carrefour_category_mapping';
        $this->className = 'CarrefourCategoryMapping';
        $this->identifier = 'id_carrefour_category_mapping';
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCarrefourRefreshHierarchies')) {
            $this->refreshHierarchies();
        } elseif (Tools::isSubmit('submitCarrefourMappings')) {
            $this->saveMappings();
        }
    }

    public function initContent()
    {
        parent::initContent();
        $content = '';

        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
            $content .= '<div class="alert alert-warning">' . $this->l('Select a specific shop to edit mappings.') . '</div>';
            $this->content .= $content;
            $this->context->smarty->assign('content', $this->content);

            return;
        }

        $idShop = (int) $this->context->shop->id;
        $mapper = new CarrefourCategoryMapper($idShop);
        $hierarchyService = $this->buildHierarchyService($idShop);
        $tree = $hierarchyService ? $hierarchyService->getCached() : [];
        $lastRefresh = $hierarchyService ? $hierarchyService->getLastRefreshTime() : 0;

        $content .= $this->renderRefreshPanel($lastRefresh, count($tree));
        $content .= $this->renderMappingForm($mapper, $idShop);
        $content .= $this->renderTreeReference($tree);

        $this->content .= $content;
        $this->context->smarty->assign('content', $this->content);
    }

    private function refreshHierarchies()
    {
        $idShop = (int) $this->context->shop->id;
        $service = $this->buildHierarchyService($idShop);
        if ($service === null) {
            $this->errors[] = $this->l('Save your API configuration before refreshing the Mirakl tree.');

            return;
        }
        try {
            $nodes = $service->refresh();
            $this->confirmations[] = sprintf('%s %d %s', $this->l('Mirakl hierarchy fetched:'), count($nodes), $this->l('nodes cached.'));
        } catch (MiraklException $e) {
            $this->errors[] = $this->l('Failed to fetch hierarchy:') . ' ' . $e->getMessage() . ' (HTTP ' . $e->getStatusCode() . ')';
        } catch (\Exception $e) {
            $this->errors[] = $this->l('Unexpected error:') . ' ' . $e->getMessage();
        }
    }

    private function saveMappings()
    {
        $idShop = (int) $this->context->shop->id;
        $mapper = new CarrefourCategoryMapper($idShop);
        $submitted = Tools::getValue('mapping', []);
        if (!is_array($submitted)) {
            $this->errors[] = $this->l('Invalid submission.');

            return;
        }
        $saved = 0;
        foreach ($submitted as $idCategoryPs => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            $code = isset($fields['code']) ? trim((string) $fields['code']) : '';
            $label = isset($fields['label']) ? trim((string) $fields['label']) : '';
            if ($code === '') {
                $mapper->removeMapping((int) $idCategoryPs);

                continue;
            }
            if ($mapper->setMapping((int) $idCategoryPs, $code, $label)) {
                $saved++;
            }
        }
        $this->confirmations[] = sprintf('%s %d %s', $this->l('Saved'), $saved, $this->l('mappings.'));
    }

    private function buildHierarchyService($idShop)
    {
        $config = CarrefourShopConfig::findForShop($idShop);
        if ($config === null) {
            return null;
        }
        $apiKey = $config->getApiKey();
        if ($apiKey === '') {
            return null;
        }
        $client = new MiraklClient(
            (string) $config->api_endpoint,
            $apiKey,
            $config->shop_id_mirakl,
            new CarrefourLogger($idShop, 'categories', (string) $config->log_level)
        );

        return new CarrefourHierarchyService($idShop, $client);
    }

    private function renderRefreshPanel($lastRefresh, $nodeCount)
    {
        $action = $this->context->link->getAdminLink('AdminCarrefourCategories');
        $token = Tools::getAdminTokenLite('AdminCarrefourCategories');
        $when = $lastRefresh > 0 ? date('Y-m-d H:i', $lastRefresh) : $this->l('never');
        $label = $this->l('Refresh Mirakl hierarchy (H11)');
        $info = sprintf('%s: %s. %s: %d.', $this->l('Last refresh'), $when, $this->l('Cached nodes'), $nodeCount);

        return '
<div class="panel">
    <div class="panel-heading"><i class="icon-sitemap"></i> ' . $this->l('Mirakl category tree') . '</div>
    <p>' . $info . '</p>
    <form method="post" action="' . $action . '&token=' . $token . '">
        <button type="submit" name="submitCarrefourRefreshHierarchies" class="btn btn-default">
            <i class="icon-refresh"></i> ' . $label . '
        </button>
    </form>
</div>';
    }

    private function renderMappingForm(CarrefourCategoryMapper $mapper, $idShop)
    {
        $existing = [];
        foreach ($mapper->getAllMappings() as $m) {
            $existing[$m['id_category_ps']] = $m;
        }
        $idLang = (int) $this->context->language->id;
        $categories = Category::getCategories($idLang, true, false);
        if (!is_array($categories)) {
            $categories = [];
        }

        $action = $this->context->link->getAdminLink('AdminCarrefourCategories');
        $token = Tools::getAdminTokenLite('AdminCarrefourCategories');

        $rows = '';
        foreach ($categories as $cat) {
            $idCat = (int) $cat['id_category'];
            $name = isset($cat['name']) ? (string) $cat['name'] : ('#' . $idCat);
            $code = isset($existing[$idCat]) ? $existing[$idCat]['code'] : '';
            $label = isset($existing[$idCat]) ? $existing[$idCat]['label'] : '';
            $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $rows .= '<tr>
                <td>' . (int) $idCat . '</td>
                <td>' . $nameEsc . '</td>
                <td><input type="text" id="mapping-' . (int) $idCat . '-code" name="mapping[' . (int) $idCat . '][code]" value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" class="form-control" style="width: 280px;" aria-label="' . sprintf($this->l('Mirakl code for %s'), $nameEsc) . '"></td>
                <td><input type="text" id="mapping-' . (int) $idCat . '-label" name="mapping[' . (int) $idCat . '][label]" value="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" class="form-control" aria-label="' . sprintf($this->l('Mirakl label for %s'), $nameEsc) . '"></td>
            </tr>';
        }

        return '
<div class="panel">
    <div class="panel-heading"><i class="icon-link"></i> ' . $this->l('PS category → Mirakl code mapping') . '</div>
    <p>' . $this->l('Leave "Mirakl code" empty to unbind a category.') . '</p>
    <form method="post" action="' . $action . '&token=' . $token . '">
        <table class="table">
            <thead><tr>
                <th>' . $this->l('PS ID') . '</th>
                <th>' . $this->l('PS category') . '</th>
                <th>' . $this->l('Mirakl code') . '</th>
                <th>' . $this->l('Mirakl label (optional)') . '</th>
            </tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>
        <button type="submit" name="submitCarrefourMappings" class="btn btn-default pull-right">
            <i class="icon-save"></i> ' . $this->l('Save mappings') . '
        </button>
        <div class="clearfix"></div>
    </form>
</div>';
    }

    private function renderTreeReference(array $tree)
    {
        if (empty($tree)) {
            return '<div class="alert alert-info">' . $this->l('No Mirakl hierarchy cached yet — hit the refresh button above to fetch it.') . '</div>';
        }
        $rows = '';
        foreach (array_slice($tree, 0, 500) as $node) {
            $indent = str_repeat('&nbsp;&nbsp;', max(0, (int) $node['level']));
            $rows .= '<tr>
                <td><code>' . htmlspecialchars((string) $node['code'], ENT_QUOTES, 'UTF-8') . '</code></td>
                <td>' . $indent . htmlspecialchars((string) $node['label'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>' . htmlspecialchars((string) ($node['parent_code'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>
            </tr>';
        }
        $warn = count($tree) > 500 ? '<p class="help-block">' . $this->l('Tree truncated to first 500 nodes for display.') . '</p>' : '';

        return '
<div class="panel">
    <div class="panel-heading"><i class="icon-list"></i> ' . $this->l('Mirakl hierarchy (reference)') . '</div>
    <table class="table table-condensed">
        <thead><tr><th>' . $this->l('Code') . '</th><th>' . $this->l('Label') . '</th><th>' . $this->l('Parent') . '</th></tr></thead>
        <tbody>' . $rows . '</tbody>
    </table>' . $warn . '
</div>';
    }
}
