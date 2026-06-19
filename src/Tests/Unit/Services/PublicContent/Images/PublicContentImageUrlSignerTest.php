<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use PHPUnit\Framework\TestCase;

final class PublicContentImageUrlSignerTest extends TestCase
{
    public function test_signed_token_round_trips_local_path(): void
    {
        $signer = new PublicContentImageUrlSigner();

        $token = $signer->sign('/storage/uploads/images/2026-06-18/example.jpg');

        $this->assertSame('/storage/uploads/images/2026-06-18/example.jpg', $signer->verify($token));
    }

    public function test_tampered_token_is_rejected(): void
    {
        $signer = new PublicContentImageUrlSigner();
        $token = $signer->sign('/storage/uploads/images/example.jpg');

        $this->assertNull($signer->verify($token . 'tampered'));
    }

    public function test_url_query_string_is_not_part_of_signed_path(): void
    {
        $signer = new PublicContentImageUrlSigner();

        $token = $signer->sign('https://cms.test/storage/uploads/images/example.jpg?cache=bust');

        $this->assertSame('/storage/uploads/images/example.jpg', $signer->verify($token));
    }
}
