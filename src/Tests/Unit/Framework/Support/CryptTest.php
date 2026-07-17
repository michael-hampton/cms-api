<?php

declare(strict_types=1);

namespace App\Tests\Unit\Framework\Support;

use App\Framework\Support\Crypt;
use App\Framework\Support\Env;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CryptTest extends TestCase
{
    protected function tearDown(): void
    {
        Env::set('APP_ENCRYPTION_KEY', '');
        parent::tearDown();
    }

    public function test_encrypt_then_decrypt_round_trips_the_original_value(): void
    {
        Env::set('APP_ENCRYPTION_KEY', base64_encode(random_bytes(32)));

        $plaintext = 'super-secret-sftp-password';

        $ciphertext = Crypt::encrypt($plaintext);

        $this->assertNotSame($plaintext, $ciphertext);
        $this->assertSame($plaintext, Crypt::decrypt($ciphertext));
    }

    public function test_encrypting_the_same_value_twice_produces_different_ciphertext(): void
    {
        Env::set('APP_ENCRYPTION_KEY', base64_encode(random_bytes(32)));

        $plaintext = 'super-secret-sftp-password';

        // Random IV per call -> ciphertext must differ even for identical input.
        $this->assertNotSame(Crypt::encrypt($plaintext), Crypt::encrypt($plaintext));
    }

    public function test_decrypt_rejects_tampered_payloads(): void
    {
        Env::set('APP_ENCRYPTION_KEY', base64_encode(random_bytes(32)));

        $ciphertext = Crypt::encrypt('super-secret-sftp-password');
        $tampered = substr($ciphertext, 0, -4) . 'AAAA';

        $this->expectException(RuntimeException::class);

        Crypt::decrypt($tampered);
    }

    public function test_decrypt_rejects_payload_encrypted_with_a_different_key(): void
    {
        Env::set('APP_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        $ciphertext = Crypt::encrypt('super-secret-sftp-password');

        Env::set('APP_ENCRYPTION_KEY', base64_encode(random_bytes(32)));

        $this->expectException(RuntimeException::class);

        Crypt::decrypt($ciphertext);
    }
}