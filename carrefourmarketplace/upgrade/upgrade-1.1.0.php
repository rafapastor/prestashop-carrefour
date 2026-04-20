<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Upgrade script for carrefourmarketplace 1.0.x → 1.1.0.
 *
 * PrestaShop auto-runs any `upgrade_module_<version>()` function found in this folder
 * when the module is updated and the new version number is reached for the first time.
 *
 * This file is a placeholder for the v1.1.0 release and is currently a no-op.
 * When v1.1.0 ships, fill in:
 *   - SQL ALTER TABLE statements for any new columns or tables.
 *   - Tab registrations for any new admin screens.
 *   - Default Configuration::updateValue() calls for new settings.
 *   - Data migrations (e.g. backfill a new column from existing rows).
 *
 * Return true on success, false on failure. PS rolls back to the prior version on false.
 *
 * IMPORTANT: upgrade scripts run exactly once per merchant install. Keep them idempotent —
 * wrap column additions in `SHOW COLUMNS` checks so re-runs are safe.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    /* Example idempotent column addition pattern — uncomment and adapt:
     *
     * $exists = Db::getInstance()->getValue(
     *     'SELECT COUNT(*) FROM information_schema.COLUMNS
     *      WHERE TABLE_SCHEMA = DATABASE()
     *      AND TABLE_NAME = "' . _DB_PREFIX_ . 'carrefour_offer"
     *      AND COLUMN_NAME = "new_field"'
     * );
     * if (!$exists) {
     *     Db::getInstance()->execute(
     *         'ALTER TABLE `' . _DB_PREFIX_ . 'carrefour_offer`
     *          ADD COLUMN `new_field` VARCHAR(50) DEFAULT NULL'
     *     );
     * }
     */

    return true;
}
