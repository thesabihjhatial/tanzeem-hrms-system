<?php

// Tanzeem HRMS System Cryptography Manager developed and maintained by Sabih

namespace App\Utilities;

class CryptographyManager
{

    private const CIPHER = 'aes-256-gcm';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public static function encrypt(string $plaintext, ?string $key = null): string
    {

        $key = self::resolveKey($key);
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($ciphertext === false) {

            throw new \RuntimeException('Encryption attempt failed.');

        }

        return base64_encode($iv . $tag . $ciphertext);

    }

    public static function decrypt(string $encoded, ?string $key = null): string
    {

        $key = self::resolveKey($key);
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {

            throw new \RuntimeException('Ciphertext is invalid.');

        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {

            throw new \RuntimeException('Decryption attempt failed: the data may be corrupted, tampered with, or key is wrong.');

        }

        return $plaintext;

    }

    public static function generateKey(): string
    {

        return base64_encode(random_bytes(32));

    }

    private static function resolveKey(?string $key): string
    {

        $key ??= $_ENV['CRYPTO_KEY'] ?? '';
        $decoded = base64_decode($key, true);

        if ($decoded === false || strlen($decoded) !== 32) {

            throw new \RuntimeException('CRYPTO_KEY must be base64-encoded/32-byte key.');

        }

        return $decoded;

    }

}
