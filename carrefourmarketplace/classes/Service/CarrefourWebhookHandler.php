<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Parses a Mirakl webhook payload and enqueues follow-up jobs.
 * Called from WebhookController. Returns quickly — no synchronous Mirakl calls.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourWebhookHandler
{
    /** @var int */
    private $idShop;

    /** @var CarrefourLogger */
    private $logger;

    public function __construct($idShop, ?CarrefourLogger $logger = null)
    {
        $this->idShop = (int) $idShop;
        $this->logger = $logger ?: new CarrefourLogger($this->idShop, 'webhook');
    }

    public function handle($decodedBody)
    {
        if (!is_array($decodedBody) || !isset($decodedBody['event_type'])) {
            $this->logger->warn('webhook.ignored', ['reason' => 'no_event_type']);

            return;
        }

        $type = (string) $decodedBody['event_type'];
        $payloads = isset($decodedBody['payload']) && is_array($decodedBody['payload']) ? $decodedBody['payload'] : [];

        switch ($type) {
            case 'ORDER':
                $this->handleOrderEvents($payloads);

                break;
            case 'OFFER':
                $this->logger->info('webhook.offer_event', ['count' => count($payloads)]);

                break;
            default:
                $this->logger->warn('webhook.unknown_event', ['type' => $type]);
        }
    }

    private function handleOrderEvents(array $payloads)
    {
        /* For MVP, a single order_sync job covers any new ORDER event —
           the periodic poll also catches missed webhooks. */
        try {
            $queue = new CarrefourJobQueue($this->idShop);
            $queue->enqueue('order_sync', ['trigger' => 'webhook'], null, 2);
            $this->logger->info('webhook.order_sync_enqueued', ['count' => count($payloads)]);
        } catch (\Exception $e) {
            $this->logger->error('webhook.enqueue_failed', ['error' => $e->getMessage()]);
        }
    }
}
