<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * CLI entry point for the Carrefour job worker.
 *
 * Usage (from PrestaShop root):
 *   php modules/carrefourmarketplace/cron/worker.php [--max-jobs=50] [--max-seconds=55]
 *
 * Recommended cron: every minute.
 *   * * * * * php /path/to/prestashop/modules/carrefourmarketplace/cron/worker.php --max-jobs=50 --max-seconds=55
 */

/* Prevent web access. */
if (PHP_SAPI !== 'cli' && (!defined('_CARREFOUR_CRON_ALLOWED_') || !_CARREFOUR_CRON_ALLOWED_)) {
    http_response_code(403);
    exit('CLI only');
}

/* Bootstrap PrestaShop. */
$psRoot = realpath(dirname(__FILE__) . '/../../..');
if ($psRoot === false || !is_file($psRoot . '/config/config.inc.php')) {
    fwrite(STDERR, "Could not locate PrestaShop config.inc.php — ensure this script lives at /modules/carrefourmarketplace/cron/.\n");
    exit(1);
}
require_once $psRoot . '/config/config.inc.php';
require_once $psRoot . '/modules/carrefourmarketplace/carrefourmarketplace.php';

/* Parse arguments. */
$options = getopt('', ['max-jobs::', 'max-seconds::', 'verbose::']);
$maxJobs = isset($options['max-jobs']) ? (int) $options['max-jobs'] : 50;
$maxSeconds = isset($options['max-seconds']) ? (int) $options['max-seconds'] : 55;
$verbose = array_key_exists('verbose', $options);

$started = time();
if ($verbose) {
    fwrite(STDOUT, sprintf("[carrefour-worker] starting — maxJobs=%d maxSeconds=%d\n", $maxJobs, $maxSeconds));
}

$worker = new CarrefourJobWorker($maxJobs, $maxSeconds);
$processed = $worker->run();

$elapsed = time() - $started;
if ($verbose) {
    fwrite(STDOUT, sprintf("[carrefour-worker] done — processed=%d elapsed=%ds\n", $processed, $elapsed));
}
exit(0);
