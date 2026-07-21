<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\RichImageUrlBuilder;
use PHPUnit\Framework\TestCase;

final class RichImageUrlBuilderTest extends TestCase
{
    public function test_builds_crop_width_and_quality(): void
    {
        $url = (new RichImageUrlBuilder())->build(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(
                width: 640,
                quality: 70,
                crop: ['t' => 10, 'l' => 20, 'cw' => 300, 'ch' => 200],
            ),
        );

        self::assertSame(
            'https://cdn.example.com/v2/t:10,l:20,cw:300,ch:200,q:70,w:640/folder/photo.jpg',
            $url,
        );
    }

    public function test_defaults_quality_even_without_width(): void
    {
        $url = (new RichImageUrlBuilder())->build(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(crop: ['t' => 0, 'l' => 0, 'cw' => 100, 'ch' => 100]),
        );

        self::assertStringContainsString('q:80', $url);
        self::assertDoesNotMatchRegularExpression('/(?<![c])w:/', $url);
    }

    public function test_appends_format_suffix(): void
    {
        $url = (new RichImageUrlBuilder())->build(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(width: 200, format: 'webp', crop: ['t' => 0, 'l' => 0, 'cw' => 10, 'ch' => 10]),
        );

        self::assertStringEndsWith('photo.jpg.webp', $url);
    }

    public function test_normalises_existing_v2_segment(): void
    {
        $url = (new RichImageUrlBuilder())->build(
            'https://cdn.example.com/v2/q:50,w:100/folder/photo.jpg',
            new ImageTransformOptions(width: 200, quality: 90),
        );

        self::assertSame('https://cdn.example.com/v2/q:90,w:200/folder/photo.jpg', $url);
        self::assertSame(1, substr_count($url, '/v2/'));
    }
}
