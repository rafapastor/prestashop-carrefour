<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Ship a Mirakl order (OR23 set tracking + OR24 confirm shipment).
 * Payload: { id_carrefour_order: int, tracking_number: string, carrier_name: string }
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourOrderShipJob extends CarrefourAbstractJob
{
    public function execute()
    {
        $payload = $this->job->getPayloadArray();
        $idCarrefourOrder = (int) ($payload['id_carrefour_order'] ?? 0);
        $tracking = (string) ($payload['tracking_number'] ?? '');
        $carrier = (string) ($payload['carrier_name'] ?? '');

        $carrefourOrder = new CarrefourOrder($idCarrefourOrder);
        if (!Validate::isLoadedObject($carrefourOrder)) {
            throw new \RuntimeException('carrefour_order not found: ' . $idCarrefourOrder);
        }

        $service = new CarrefourOrderService($this->idShop, $this->client, $this->config, $this->logger);
        $service->shipOrder((string) $carrefourOrder->order_id_mirakl, $tracking, $carrier);

        Db::getInstance()->execute(sprintf(
            'UPDATE `%scarrefour_order_line` SET `shipped_at` = NOW(),
             `tracking_number` = "%s", `carrier_name` = "%s"
             WHERE `id_carrefour_order` = %d',
            _DB_PREFIX_,
            pSQL($tracking),
            pSQL($carrier),
            $idCarrefourOrder
        ));

        return [
            'action' => 'shipped',
            'order_id_mirakl' => (string) $carrefourOrder->order_id_mirakl,
            'tracking' => $tracking,
            'carrier' => $carrier,
        ];
    }
}
