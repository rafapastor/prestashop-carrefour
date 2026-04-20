<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Dispatches a single job to its handler class based on the `type` column,
 * and translates the handler's return value (or exception) into JobQueue state transitions.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourJobRunner
{
    const TYPE_OFFER_UPSERT = 'offer_upsert';
    const TYPE_STOCK_UPDATE = 'stock_update';
    const TYPE_ORDER_SYNC = 'order_sync';
    const TYPE_ORDER_ACCEPT = 'order_accept';
    const TYPE_ORDER_SHIP = 'order_ship';

    /** @var array<string, string> */
    private static $registry = [
        self::TYPE_OFFER_UPSERT => 'CarrefourOfferUpsertJob',
        self::TYPE_STOCK_UPDATE => 'CarrefourStockUpdateJob',
        self::TYPE_ORDER_SYNC => 'CarrefourOrderSyncJob',
        self::TYPE_ORDER_ACCEPT => 'CarrefourOrderAcceptJob',
        self::TYPE_ORDER_SHIP => 'CarrefourOrderShipJob',
    ];

    /**
     * Runs the given job. Returns the result array produced by the handler,
     * or null on failure (failure is already logged + persisted via JobQueue).
     */
    public function run(CarrefourJob $job)
    {
        $queue = new CarrefourJobQueue((int) $job->id_shop);
        $type = (string) $job->type;

        if (!isset(self::$registry[$type])) {
            $queue->markFailed($job, 'UNKNOWN_TYPE', 'Unknown job type: ' . $type, false);

            return null;
        }

        $className = self::$registry[$type];
        if (!class_exists($className)) {
            $queue->markFailed($job, 'HANDLER_MISSING', 'Handler class missing: ' . $className, false);

            return null;
        }

        try {
            /** @var CarrefourAbstractJob $handler */
            $handler = new $className($job);
            $result = $handler->execute();
            if (!is_array($result)) {
                $result = ['action' => 'done'];
            }

            if (!empty($result['poll_again'])) {
                $delay = isset($result['poll_delay_seconds']) ? (int) $result['poll_delay_seconds'] : 30;
                $queue->rescheduleForPolling($job, $delay, $result);

                return $result;
            }

            $queue->markCompleted($job, $result);

            return $result;
        } catch (MiraklAuthException $e) {
            $queue->markFailed($job, $e->getErrorCode() ?: 'AUTH', $e->getMessage(), false);
        } catch (MiraklValidationException $e) {
            $queue->markFailed($job, $e->getErrorCode() ?: 'VALIDATION', $e->getMessage(), false);
        } catch (MiraklRateLimitException $e) {
            $queue->markFailed($job, 'RATE_LIMIT', $e->getMessage(), true);
        } catch (MiraklServerException $e) {
            $queue->markFailed($job, 'SERVER_ERROR', $e->getMessage(), true);
        } catch (MiraklNetworkException $e) {
            $queue->markFailed($job, 'NETWORK', $e->getMessage(), true);
        } catch (MiraklException $e) {
            $queue->markFailed($job, $e->getErrorCode() ?: 'MIRAKL', $e->getMessage(), false);
        } catch (\Exception $e) {
            $queue->markFailed($job, 'EXCEPTION', $e->getMessage(), true);
        }

        return null;
    }
}
