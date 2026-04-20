<?php

use PHPUnit\Framework\TestCase;

/**
 * _COOKIE_KEY_ is normally set by PrestaShop. In tests we define it in bootstrap.php
 * via a per-test setUp to keep the crypto deterministic and isolated.
 */
class CarrefourCryptoTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('_COOKIE_KEY_')) {
            define('_COOKIE_KEY_', 'TEST-COOKIE-KEY-0123456789abcdef0123456789abcdef');
        }
    }

    public function test_encrypt_then_decrypt_roundtrip()
    {
        $plain = 'my-api-key-abc123';
        $cipher = CarrefourCrypto::encrypt($plain);
        $this->assertNotSame($plain, $cipher, 'Ciphertext must not equal plaintext');
        $this->assertSame($plain, CarrefourCrypto::decrypt($cipher));
    }

    public function test_ciphertext_is_base64()
    {
        $cipher = CarrefourCrypto::encrypt('anything');
        $this->assertMatchesRegularExpression('#^[A-Za-z0-9+/=]+$#', $cipher);
    }

    public function test_two_encryptions_of_same_plaintext_differ()
    {
        $a = CarrefourCrypto::encrypt('same-plain');
        $b = CarrefourCrypto::encrypt('same-plain');
        $this->assertNotSame($a, $b, 'Random IV should make ciphertexts differ');
        $this->assertSame('same-plain', CarrefourCrypto::decrypt($a));
        $this->assertSame('same-plain', CarrefourCrypto::decrypt($b));
    }

    public function test_empty_input_returns_empty_string()
    {
        $this->assertSame('', CarrefourCrypto::encrypt(''));
        $this->assertSame('', CarrefourCrypto::encrypt(null));
        $this->assertSame('', CarrefourCrypto::decrypt(''));
        $this->assertSame('', CarrefourCrypto::decrypt(null));
    }

    public function test_decrypt_garbage_throws()
    {
        $this->expectException(\RuntimeException::class);
        CarrefourCrypto::decrypt('not-a-valid-cipher!!!');
    }

    public function test_decrypt_with_wrong_length_ciphertext_throws()
    {
        /* A valid base64 but too short to include the IV prefix */
        $this->expectException(\RuntimeException::class);
        CarrefourCrypto::decrypt(base64_encode('x'));
    }

    public function test_unicode_roundtrip()
    {
        $plain = 'clé-secrète-€-中文-🎉';
        $cipher = CarrefourCrypto::encrypt($plain);
        $this->assertSame($plain, CarrefourCrypto::decrypt($cipher));
    }

    public function test_long_string_roundtrip()
    {
        $plain = str_repeat('abcdef0123456789', 200); /* 3200 chars */
        $cipher = CarrefourCrypto::encrypt($plain);
        $this->assertSame($plain, CarrefourCrypto::decrypt($cipher));
    }
}
