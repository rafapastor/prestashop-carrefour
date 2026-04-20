<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Accept/refuse Mirakl order lines (OR21).
 * Payload: { id_carrefour_order: int, line_acceptances: { mirakl_line_id: bool } }
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourOrderAcceptJob extends CarrefourAbstractJob
{
    public function execute()
    {
        $payload = $this->job->getPayloadArray();
        $idCarrefourOrder = (int) ($payload['id_carrefour_order'] ?? 0);
        $acceptances = isset($payload['line_acceptances']) && is_array($payload['line_acceptances'])
            ? $payload['line_acceptances']
            : [];

        $carrefourOrder = new CarrefourOrder($idCarrefourOrder);
        if (!Validate::isLoadedObject($carrefourOrder)) {
            throw new \RuntimeException('carrefour_order not found: ' . $idCarrefourOrder);
        }

        if (empty($acceptances)) {
            $rows = Db::getInstance()->executeS(sprintf(
                'SELECT `order_line_id_mirakl` FROM `%scarrefour_order_line` WHERE `id_carrefour_order` = %d',
                _DB_PREFIX_,
                $idCarrefourOrder
            ));
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $acceptances[(string) $r['order_line_id_mirakl']] = true;
                }
            }
        }

        $service = new CarrefourOrderService($this->idShop, $this->client, $this->config, $this->logger);
        $service->acceptOrder((string) $carrefourOrder->order_id_mirakl, $acceptances);

        Db::getInstance()->execute(sprintf(
            'UPDATE `%scarrefour_order_line` SET `accepted_at` = NOW()
             WHERE `id_carrefour_order` = %d',
            _DB_PREFIX_,
            $idCarrefourOrder
        ));

        return [
            'action' => 'accepted',
            'order_id_mirakl' => (string) $carrefourOrder->order_id_mirakl,
            'lines' => count($acceptances),
        ];
    }
}
