<?php

namespace App\Framework\Support;

use RuntimeException;

/**
 * Simple reversible encryption for secrets stored at rest (e.g. vendor
 * SFTP passwords on PrintVendorConnection).
 *
 * AES-256-CBC via openssl, with an HMAC-SHA256 MAC to detect tampering —
 * deliberately mirrors the shape of Laravel's encrypter (iv + mac + value,
 * base64-wrapped) without pulling in illuminate/encryption as a dependency.
 *
 * Key source: env('APP_ENCRYPTION_KEY'), a base64-encoded 32-byte key.
 * Generate one with: base64_encode(random_bytes(32))
 *
 * In non-production environments, if the key is missing, a fixed
 * development-only key is used so local setup doesn't require extra
 * config — this is logged loudly and MUST NOT happen in production.
 */
class Crypt
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * Fixed key used only when APP_ENCRYPTION_KEY is not set outside
     * production. Never used to protect real secrets.
     */
    private const DEV_FALLBACK_KEY = 'ZGV2LW9ubHktaW5zZWN1cmUtZmFsbGJhY2sta2V5LTMyYg==';

    public static function encrypt(string $value): string
    {
        $key = self::key();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $encrypted = openssl_encrypt($value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('Crypt: encryption failed.');
        }

        $mac = hash_hmac('sha256', $iv . $encrypted, $key, true);

        return base64_encode($iv . $mac . $encrypted);
    }

    public static function decrypt(string $payload): string
    {
        $key = self::key();
        $raw = base64_decode($payload, true);

        if ($raw === false) {
            throw new RuntimeException('Crypt: payload is not valid base64.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $ivLength);
        $mac = substr($raw, $ivLength, 32);
        $encrypted = substr($raw, $ivLength + 32);

        $expectedMac = hash_hmac('sha256', $iv . $encrypted, $key, true);

        if (!hash_equals($expectedMac, $mac)) {
            throw new RuntimeException('Crypt: payload failed authentication (tampered or wrong key).');
        }

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new RuntimeException('Crypt: decryption failed.');
        }

        return $decrypted;
    }

    private static function key(): string
    {
        $encoded = Env::get('APP_ENCRYPTION_KEY');

        if (empty($encoded)) {
            $env = (string)Env::get('APP_ENV', '');

            if ($env === 'production') {
                throw new RuntimeException(
                    'Crypt: APP_ENCRYPTION_KEY is not set. Refusing to use the development '
                    . 'fallback key in production. Generate one with: '
                    . "php -r \"echo base64_encode(random_bytes(32));\""
                );
            }

            Logger::warning(
                'Crypt: APP_ENCRYPTION_KEY not set — using an insecure development fallback key. '
                . 'Set APP_ENCRYPTION_KEY before storing real secrets.'
            );

            $encoded = self::DEV_FALLBACK_KEY;
        }

        $key = base64_decode((string)$encoded, true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('Crypt: APP_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        }

        return $key;
    }
}