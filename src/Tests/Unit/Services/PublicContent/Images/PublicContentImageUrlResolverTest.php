<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Services\PublicContent\Images\PublicContentImageUrlResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use PHPUnit\Framework\TestCase;

final class PublicContentImageUrlResolverTest extends TestCase
{
    public function test_local_storage_upload_url_is_rewritten_to_signed_public_api_url(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        $url = $resolver->resolve('/storage/uploads/images/example.jpg', 42);

        self::assertStringStartsWith('/api/v1/42/content-images/', $url);
        self::assertStringNotContainsString('/storage/uploads/images/example.jpg', $url);
    }

    public function test_absolute_local_storage_upload_url_is_rewritten(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        $url = $resolver->resolve('https://cms.test/storage/uploads/images/example.jpg', 7);

        self::assertStringStartsWith('/api/v1/7/content-images/', $url);
    }

    public function test_external_url_is_left_unchanged(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        self::assertSame(
            'https://cdn.example.com/image.jpg',
            $resolver->resolve('https://cdn.example.com/image.jpg', 42),
        );
    }

    public function test_traversal_path_is_not_rewritten(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        self::assertSame(
            '/storage/uploads/../secrets.env',
            $resolver->resolve('/storage/uploads/../secrets.env', 42),
        );
    }
}
