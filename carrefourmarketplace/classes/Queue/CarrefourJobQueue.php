<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Lightweight, DB-backed job queue. Shop-scoped. Not a distributed queue —
 * concurrency is limited to one worker at a time per shop.
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class CarrefourJobQueue
{
    /** @var int */
    private $idShop;

    public function __construct($idShop)
    {
        $this->idShop = (int) $idShop;
    }

    /**
     * Enqueue a job. Returns the new job ID.
     *
     * @throws \RuntimeException on persistence failure
     */
    public function enqueue($type, array $payload, $scheduledAt = null, $priority = 5, $maxAttempts = 5)
    {
        $job = new CarrefourJob();
        $job->id_shop = $this->idShop;
        $job->type = (string) $type;
        $job->status = CarrefourJob::STATUS_PENDING;
        $job->priority = (int) $priority;
        $job->max_attempts = (int) $maxAttempts;
        $job->setPayloadArray($payload);
        $job->scheduled_at = $scheduledAt ?: date('Y-m-d H:i:s');

        if (!$job->add()) {
            throw new \RuntimeException('Failed to enqueue carrefour job of type ' . (string) $type);
        }

        return (int) $job->id;
    }

    /**
     * Pick the next due job and mark it as running. Returns null if the queue is empty.
     */
    public function claim()
    {
        $row = Db::getInstance()->getRow(sprintf(
            'SELECT `id_carrefour_job` FROM `%scarrefour_job`
             WHERE `id_shop` = %d
             AND `status` IN ("%s","%s")
             AND `scheduled_at` <= NOW()
             ORDER BY `priority` ASC, `scheduled_at` ASC
             LIMIT 1',
            _DB_PREFIX_,
            $this->idShop,
            pSQL(CarrefourJob::STATUS_PENDING),
            pSQL(CarrefourJob::STATUS_RETRYING)
        ));
        if (!$row) {
            return null;
        }

        $job = new CarrefourJob((int) $row['id_carrefour_job']);
        if (!Validate::isLoadedObject($job)) {
            return null;
        }
        $job->status = CarrefourJob::STATUS_RUNNING;
        $job->started_at = date('Y-m-d H:i:s');
        $job->attempts = (int) $job->attempts + 1;
        $job->update();

        return $job;
    }

    public function markCompleted(CarrefourJob $job, array $result = [])
    {
        $job->status = CarrefourJob::STATUS_COMPLETED;
        $job->completed_at = date('Y-m-d H:i:s');
        $job->setResultArray($result);
        $job->update();
    }

    public function markFailed(CarrefourJob $job, $errorCode, $errorMessage, $retryable = true)
    {
        if ($retryable && (int) $job->attempts < (int) $job->max_attempts) {
            $job->status = CarrefourJob::STATUS_RETRYING;
            $backoff = (int) min(3600, 30 * pow(2, max(0, (int) $job->attempts - 1)));
            $job->scheduled_at = date('Y-m-d H:i:s', time() + $backoff);
        } else {
            $job->status = CarrefourJob::STATUS_FAILED;
        }
        $job->last_error_code = (string) $errorCode;
        $job->last_error_message = mb_substr((string) $errorMessage, 0, 5000);
        $job->update();
    }

    /**
     * Re-queue the job for another polling cycle (e.g. Mirakl import still processing).
     */
    public function rescheduleForPolling(CarrefourJob $job, $delaySeconds = 30, array $mergeResult = [])
    {
        $job->status = CarrefourJob::STATUS_PENDING;
        $job->scheduled_at = date('Y-m-d H:i:s', time() + max(5, (int) $delaySeconds));
        if (!empty($mergeResult)) {
            $existing = $job->getResultArray();
            $job->setResultArray(array_merge($existing, $mergeResult));
        }
        $job->update();
    }

    public function countByStatus($status)
    {
        return (int) Db::getInstance()->getValue(sprintf(
            'SELECT COUNT(*) FROM `%scarrefour_job` WHERE `id_shop` = %d AND `status` = "%s"',
            _DB_PREFIX_,
            $this->idShop,
            pSQL((string) $status)
        ));
    }
}
