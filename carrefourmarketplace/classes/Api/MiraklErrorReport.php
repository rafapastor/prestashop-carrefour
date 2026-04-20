<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * Parse a Mirakl error_report CSV into a structured array.
 * Columns vary per endpoint but typically include sku, error_code, error_message (or variations).
 */
if (!defined('_PS_VERSION_') && !defined('CARREFOUR_TEST_MODE')) {
    exit;
}

class MiraklErrorReport
{
    /**
     * Parse a CSV payload returned by Mirakl's error_report endpoints.
     *
     * @param string $csv    raw CSV contents (headers in first row)
     * @param string $delim  column delimiter — Mirakl uses ';' most often, sometimes ','
     * @param string $enclos enclosure character
     * @return array<int, array<string, string>>  list of rows as header=>value maps
     */
    public static function parse($csv, $delim = ';', $enclos = '"')
    {
        if (!is_string($csv) || trim($csv) === '') {
            return [];
        }

        /* Normalize line endings */
        $csv = str_replace(["\r\n", "\r"], "\n", $csv);

        /* Auto-detect delimiter on the first non-empty line if caller passed ';' but file uses ',' */
        if ($delim === ';' && strpos($csv, ';') === false && strpos($csv, ',') !== false) {
            $delim = ',';
        }

        $rows = [];
        $headers = null;
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        while (($fields = fgetcsv($stream, 0, $delim, $enclos, '\\')) !== false) {
            if ($fields === [null] || $fields === false) {
                continue;
            }
            if ($headers === null) {
                $headers = array_map(function ($h) {
                    return trim((string) $h);
                }, $fields);

                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($fields[$i]) ? (string) $fields[$i] : '';
            }
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Count rows in an error report. Zero means import succeeded fully.
     */
    public static function countErrors($csv, $delim = ';')
    {
        return count(self::parse($csv, $delim));
    }
}
