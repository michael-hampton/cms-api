<?php

namespace App\Tests\Unit\Services\PublicContent\Media;

use App\Services\PublicContent\Media\PublicMediaPathResolver;
use App\Services\PublicContent\Media\PublicMediaUrlSigner;
use PHPUnit\Framework\TestCase;

final class PublicMediaUrlSignerTest extends TestCase
{
    public function testSignsEligibleStoragePath(): void
    {
        putenv('PUBLIC_MEDIA_SIGNING_KEY=test-secret');

        $signer = new PublicMediaUrlSigner(new PublicMediaPathResolver());
        $url = $signer->signedUrl('demo-site', '/storage/images/photo.jpg', 2_000_000_000);

        self::assertStringStartsWith('/api/v1/demo-site/media/', $url);
        self::assertStringContainsString('expires=2000000000', $url);
        self::assertStringContainsString('signature=', $url);
        self::assertStringNotContainsString('/storage/images/photo.jpg', $url);
    }

    public function testLeavesExternalUrlsUntouched(): void
    {
        $signer = new PublicMediaUrlSigner(new PublicMediaPathResolver());

        self::assertSame(
            'https://cdn.example.com/photo.jpg',
            $signer->signedUrl('demo-site', 'https://cdn.example.com/photo.jpg', 2_000_000_000),
        );
    }

    public function testVerifiesSignedToken(): void
    {
        putenv('PUBLIC_MEDIA_SIGNING_KEY=test-secret');

        $signer = new PublicMediaUrlSigner(new PublicMediaPathResolver());
        $url = $signer->signedUrl('demo-site', '/storage/images/photo.webp', 2_000_000_000);
        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $token = basename($parts['path']);

        self::assertSame(
            '/storage/images/photo.webp',
            $signer->verify('demo-site', $token, $query['expires'], $query['signature']),
        );
    }

    public function testRejectsExpiredSignature(): void
    {
        putenv('PUBLIC_MEDIA_SIGNING_KEY=test-secret');

        $signer = new PublicMediaUrlSigner(new PublicMediaPathResolver());
        $url = $signer->signedUrl('demo-site', '/storage/images/photo.jpg', time() - 10);
        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        self::assertNull($signer->verify('demo-site', basename($parts['path']), $query['expires'], $query['signature']));
    }

    public function testRejectsTraversalPath(): void
    {
        $resolver = new PublicMediaPathResolver();

        self::assertFalse($resolver->isEligible('/storage/../.env'));
    }
}
