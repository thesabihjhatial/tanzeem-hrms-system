<?php

namespace App\Tests\Cryptography;

use App\Utilities\CryptographyManager;
use PHPUnit\Framework\TestCase;

class CryptographyManagerTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = CryptographyManager::generateKey();
    }

    public function test_decrypt_reverses_encrypt(): void
    {
        $ciphertext = CryptographyManager::encrypt('CNIC: 35202-1234567-1', $this->key);

        $this->assertNotSame('CNIC: 35202-1234567-1', $ciphertext);
        $this->assertSame('CNIC: 35202-1234567-1', CryptographyManager::decrypt($ciphertext, $this->key));
    }

    public function test_encrypting_the_same_plaintext_twice_yields_different_ciphertext(): void
    {
        $first = CryptographyManager::encrypt('same value', $this->key);
        $second = CryptographyManager::encrypt('same value', $this->key);

        $this->assertNotSame($first, $second, 'each encryption must use a fresh random IV');
    }

    public function test_decrypt_fails_with_the_wrong_key(): void
    {
        $ciphertext = CryptographyManager::encrypt('secret', $this->key);
        $wrongKey = CryptographyManager::generateKey();

        $this->expectException(\RuntimeException::class);

        CryptographyManager::decrypt($ciphertext, $wrongKey);
    }

    public function test_decrypt_fails_on_tampered_ciphertext(): void
    {
        $ciphertext = CryptographyManager::encrypt('secret', $this->key);
        $raw = base64_decode($ciphertext, true);
        $tampered = base64_encode(substr($raw, 0, -1) . chr(ord(substr($raw, -1)) ^ 0xFF));

        $this->expectException(\RuntimeException::class);

        CryptographyManager::decrypt($tampered, $this->key);
    }

    public function test_generate_key_produces_a_32_byte_key(): void
    {
        $decoded = base64_decode(CryptographyManager::generateKey(), true);

        $this->assertSame(32, strlen($decoded));
    }

    public function test_rejects_an_invalid_key(): void
    {
        $this->expectException(\RuntimeException::class);

        CryptographyManager::encrypt('hello', 'not-a-valid-key');
    }
}
