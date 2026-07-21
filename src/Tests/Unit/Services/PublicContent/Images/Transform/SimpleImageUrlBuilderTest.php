<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\SimpleImageUrlBuilder;
use PHPUnit\Framework\TestCase;

final class SimpleImageUrlBuilderTest extends TestCase
{
    public function test_builds_width_and_default_quality(): void
    {
        $url = (new SimpleImageUrlBuilder())->build(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(width: 800),
        );

        self::assertSame('https://cdn.example.com/folder/photo-w800-q80.jpg', $url);
    }

    public function test_omits_quality_when_no_width(): void
    {
        $url = (new SimpleImageUrlBuilder())->build(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(quality: 50),
        );

        self::assertSame('https://cdn.example.com/folder/photo.jpg', $url);
    }

    public function test_swaps_format_to_webp(): void
    {
        $url = (new SimpleImageUrlBuilder())->build(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(width: 400, format: 'webp'),
        );

        self::assertSame('https://cdn.example.com/folder/photo-w400-q80.webp', $url);
    }

    public function test_replaces_existing_width_cleanly_via_base_url(): void
    {
        $url = (new SimpleImageUrlBuilder())->build(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(width: 320, quality: 60),
        );

        self::assertSame('https://cdn.example.com/folder/photo-w320-q60.jpg', $url);
        self::assertStringNotContainsString('w800', $url);
    }
}
