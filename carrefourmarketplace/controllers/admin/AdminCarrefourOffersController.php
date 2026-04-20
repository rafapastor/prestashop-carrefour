<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Phase 3b version: lists offers per listing and lets the merchant:
 *   - Add PS products as offers (by comma-separated product IDs, with optional attribute).
 *   - Dispatch a sync job to Mirakl for a given listing (enqueues CarrefourOfferUpsertJob).
 *
 * Rich product picker UI is not included here — Phase 3b keeps it simple and testable.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCarrefourOffersController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'carrefour_offer';
        $this->className = 'CarrefourOffer';
        $this->identifier = 'id_carrefour_offer';
        $this->lang = false;

        parent::__construct();

        $this->addRowAction('delete');
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected offers?'),
                'icon' => 'icon-trash',
            ],
        ];
        $this->meta_title = $this->l('Carrefour Marketplace — Offers');

        $this->fields_list = [
            'id_carrefour_offer' => ['title' => $this->l('ID'), 'align' => 'center', 'class' => 'fixed-width-xs'],
            'id_carrefour_listing' => ['title' => $this->l('Listing')],
            'sku' => ['title' => $this->l('SKU')],
            'ean' => ['title' => $this->l('EAN')],
            'status' => ['title' => $this->l('Status'), 'align' => 'center'],
            'price_sent' => ['title' => $this->l('Price sent'), 'align' => 'right'],
            'stock_sent' => ['title' => $this->l('Stock sent'), 'align' => 'right'],
            'last_synced_at' => ['title' => $this->l('Last synced'), 'align' => 'center', 'type' => 'datetime'],
            'last_error_code' => ['title' => $this->l('Error'), 'align' => 'center'],
        ];

        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
            $this->_where = ' AND a.`id_shop` = ' . (int) $this->context->shop->id;
        }
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCarrefourAddOffers')) {
            $this->addOffers();
        } elseif (Tools::isSubmit('submitCarrefourDispatch')) {
            $this->dispatchListing();
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();
        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
            $warn = '<div class="alert alert-warning">' . $this->l('Select a specific shop from the top shop selector to manage offers.') . '</div>';
            $this->content = $warn . $this->content;
            $this->context->smarty->assign('content', $this->content);

            return;
        }
        $panel = $this->renderActionsPanel();
        $this->content = $panel . $this->content;
        $this->context->smarty->assign('content', $this->content);
    }

    private function addOffers()
    {
        $idShop = (int) $this->context->shop->id;
        $listingId = (int) Tools::getValue('target_listing_id');
        $idsRaw = (string) Tools::getValue('product_ids');
        if ($listingId <= 0) {
            $this->errors[] = $this->l('Choose a target listing.');

            return;
        }
        $listing = new CarrefourListing($listingId);
        if (!Validate::isLoadedObject($listing) || (int) $listing->id_shop !== $idShop) {
            $this->errors[] = $this->l('Listing not found for this shop.');

            return;
        }

        $parts = array_filter(array_map('trim', preg_split('/[\s,]+/', $idsRaw)));
        if (empty($parts)) {
            $this->errors[] = $this->l('No product IDs provided.');

            return;
        }

        $added = 0;
        $skipped = 0;
        foreach ($parts as $raw) {
            /* Accept "123" or "123:456" (product:attribute). */
            $pieces = explode(':', (string) $raw);
            $idProduct = (int) $pieces[0];
            $idAttribute = isset($pieces[1]) ? (int) $pieces[1] : 0;
            if ($idProduct <= 0) {
                $skipped++;

                continue;
            }
            $product = new Product($idProduct);
            if (!Validate::isLoadedObject($product)) {
                $skipped++;

                continue;
            }

            $reference = $idAttribute > 0
                ? (string) Db::getInstance()->getValue('SELECT `reference` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product_attribute` = ' . (int) $idAttribute)
                : (string) $product->reference;
            $ean = $idAttribute > 0
                ? (string) Db::getInstance()->getValue('SELECT `ean13` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product_attribute` = ' . (int) $idAttribute)
                : (string) $product->ean13;
            $sku = $reference !== '' ? $reference : ('PS-' . $idProduct . ($idAttribute > 0 ? '-' . $idAttribute : ''));

            $existingId = (int) Db::getInstance()->getValue(sprintf(
                'SELECT `id_carrefour_offer` FROM `%scarrefour_offer`
                 WHERE `id_shop` = %d AND `id_product` = %d AND `id_product_attribute` = %d',
                _DB_PREFIX_,
                $idShop,
                $idProduct,
                $idAttribute
            ));
            $offer = $existingId > 0 ? new CarrefourOffer($existingId) : new CarrefourOffer();
            $offer->id_shop = $idShop;
            $offer->id_carrefour_listing = $listingId;
            $offer->id_product = $idProduct;
            $offer->id_product_attribute = $idAttribute;
            $offer->sku = $sku;
            $offer->ean = $ean;
            if (!$existingId) {
                $offer->status = 'pending';
            }
            $ok = $existingId > 0 ? $offer->update() : $offer->add();
            if ($ok) {
                $added++;
            } else {
                $skipped++;
            }
        }
        $this->confirmations[] = sprintf(
            '%s %d, %s %d.',
            $this->l('Offers added/updated:'),
            $added,
            $this->l('skipped:'),
            $skipped
        );
    }

    private function dispatchListing()
    {
        $idShop = (int) $this->context->shop->id;
        $listingId = (int) Tools::getValue('target_listing_id');
        if ($listingId <= 0) {
            $this->errors[] = $this->l('Choose a listing to dispatch.');

            return;
        }

        $ids = Db::getInstance()->executeS(sprintf(
            'SELECT `id_carrefour_offer` FROM `%scarrefour_offer`
             WHERE `id_shop` = %d AND `id_carrefour_listing` = %d AND `status` IN ("pending","error")',
            _DB_PREFIX_,
            $idShop,
            $listingId
        ));
        $offerIds = [];
        if (is_array($ids)) {
            foreach ($ids as $row) {
                $offerIds[] = (int) $row['id_carrefour_offer'];
            }
        }
        if (empty($offerIds)) {
            $this->warnings[] = $this->l('No offers in "pending" or "error" state in that listing.');

            return;
        }

        try {
            $queue = new CarrefourJobQueue($idShop);
            $jobId = $queue->enqueue('offer_upsert', [
                'listing_id' => $listingId,
                'offer_ids' => $offerIds,
            ]);
            $this->confirmations[] = sprintf(
                '%s #%d — %d %s',
                $this->l('Dispatch job enqueued'),
                $jobId,
                count($offerIds),
                $this->l('offers queued for sync.')
            );
        } catch (\Exception $e) {
            $this->errors[] = $this->l('Failed to enqueue dispatch job:') . ' ' . $e->getMessage();
        }
    }

    private function renderActionsPanel()
    {
        $action = $this->context->link->getAdminLink('AdminCarrefourOffers');
        $token = Tools::getAdminTokenLite('AdminCarrefourOffers');

        $listings = Db::getInstance()->executeS(sprintf(
            'SELECT `id_carrefour_listing`, `name` FROM `%scarrefour_listing` WHERE `id_shop` = %d ORDER BY `name`',
            _DB_PREFIX_,
            (int) $this->context->shop->id
        ));
        $options = '<option value="0">— ' . $this->l('Select a listing') . ' —</option>';
        if (is_array($listings)) {
            foreach ($listings as $l) {
                $options .= '<option value="' . (int) $l['id_carrefour_listing'] . '">'
                    . htmlspecialchars((string) $l['name'], ENT_QUOTES, 'UTF-8')
                    . ' (#' . (int) $l['id_carrefour_listing'] . ')</option>';
            }
        }

        return '
<div class="panel">
    <div class="panel-heading"><i class="icon-plus"></i> ' . $this->l('Add PS products to a listing') . '</div>
    <form method="post" action="' . $action . '&token=' . $token . '" class="form-horizontal">
        <div class="form-group">
            <label class="control-label col-lg-3" for="carrefour-target-listing">' . $this->l('Listing') . '</label>
            <div class="col-lg-6"><select id="carrefour-target-listing" name="target_listing_id" class="form-control">' . $options . '</select></div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3" for="carrefour-product-ids">' . $this->l('PS product IDs') . '</label>
            <div class="col-lg-6">
                <textarea id="carrefour-product-ids" name="product_ids" class="form-control" rows="3" placeholder="123, 124:45, 125"></textarea>
                <p class="help-block">' . $this->l('Comma- or whitespace-separated. Use "product_id:attribute_id" for variants.') . '</p>
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-9 col-lg-offset-3">
                <button type="submit" name="submitCarrefourAddOffers" class="btn btn-default">
                    <i class="icon-plus"></i> ' . $this->l('Add to listing') . '
                </button>
                <button type="submit" name="submitCarrefourDispatch" class="btn btn-primary">
                    <i class="icon-cloud-upload"></i> ' . $this->l('Dispatch listing to Mirakl') . '
                </button>
            </div>
        </div>
    </form>
</div>';
    }
}
