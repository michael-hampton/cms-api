<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Services\PublicContent\Images\PublicContentImageUrlResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use PHPUnit\Framework\TestCase;

final class PublicContentImageUrlResolverTest extends TestCase
{
    public function test_local_storage_upload_url_is_rewritten_to_signed_public_image_url(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        $url = $resolver->resolve('/storage/uploads/images/example.jpg', 42);

        $this->assertStringStartsWith('/public/images/', $url);
        $this->assertStringNotContainsString('/storage/uploads/images/example.jpg', $url);
    }

    public function test_public_image_url_is_first_party_and_does_not_expose_external_cdn_host(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        $url = $resolver->resolve('/storage/uploads/images/example.jpg', 'my-site');

        $this->assertStringStartsWith('/public/images/', $url);
        $this->assertFalse(str_starts_with($url, 'http://'));
        $this->assertFalse(str_starts_with($url, 'https://'));
        $this->assertStringNotContainsString('cdn', strtolower($url));
    }

    public function test_absolute_local_storage_upload_url_is_rewritten(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        $url = $resolver->resolve('https://cms.test/storage/uploads/images/example.jpg', 7);

        $this->assertStringStartsWith('/public/images/', $url);
    }

    public function test_external_url_is_left_unchanged(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        $this->assertSame(
            'https://cdn.example.com/image.jpg',
            $resolver->resolve('https://cdn.example.com/image.jpg', 42),
        );
    }

    public function test_traversal_path_is_not_rewritten(): void
    {
        $resolver = new PublicContentImageUrlResolver(new PublicContentImageUrlSigner());

        $this->assertSame(
            '/storage/uploads/../secrets.env',
            $resolver->resolve('/storage/uploads/../secrets.env', 42),
        );
    }
}
