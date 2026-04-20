<?php
/**
 * SPDX-License-Identifier: AGPL-3.0-only
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrefourCrypto
{
    const CIPHER = 'AES-256-CBC';

    public static function encrypt($plain)
    {
        if ($plain === null || $plain === '') {
            return '';
        }
        $key = self::getKey();
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $cipher = openssl_encrypt((string) $plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('carrefourmarketplace: encryption failed');
        }

        return base64_encode($iv . $cipher);
    }

    public static function decrypt($encoded)
    {
        if ($encoded === null || $encoded === '') {
            return '';
        }
        $key = self::getKey();
        $data = base64_decode($encoded, true);
        if ($data === false) {
            throw new \RuntimeException('carrefourmarketplace: invalid base64 payload');
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($data) < $ivLen) {
            throw new \RuntimeException('carrefourmarketplace: ciphertext truncated');
        }
        $iv = substr($data, 0, $ivLen);
        $cipher = substr($data, $ivLen);
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('carrefourmarketplace: decryption failed (key mismatch?)');
        }

        return $plain;
    }

    private static function getKey()
    {
        if (!defined('_COOKIE_KEY_') || _COOKIE_KEY_ === '') {
            throw new \RuntimeException('carrefourmarketplace: _COOKIE_KEY_ is not defined');
        }

        return hash('sha256', _COOKIE_KEY_, true);
    }
}
