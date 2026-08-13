<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageBaseUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Separate fail-closed guarantee from {@see SourceImageUrlTest}:
 * the build-time image base setting is validated when the library loads.
 */
final class ImageBaseUrlTest extends TestCase
{
    public function test_accepts_absolute_http_origin(): void
    {
        $base = new ImageBaseUrl('https://images.example.com/cdn/');

        self::assertSame('https://images.example.com/cdn', $base->value);
    }

    public function test_refuses_malformed_base_with_clear_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('absolute http(s) origin');

        new ImageBaseUrl('not-a-url');
    }

    public function test_empty_config_is_unused_without_error(): void
    {
        self::assertNull(ImageBaseUrl::tryFromConfig(''));
        self::assertNull(ImageBaseUrl::tryFromConfig(null));
    }

    public function test_malformed_config_refuses_at_load(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ImageBaseUrl::tryFromConfig('ftp://images.example.com');
    }
}
