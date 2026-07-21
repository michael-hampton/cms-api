<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\ImageUrlParameterReader;
use App\Services\PublicContent\Images\Transform\ImageUrlStyleChooser;
use App\Services\PublicContent\Images\Transform\RecognisedImageHostTransformer;
use App\Services\PublicContent\Images\Transform\RichImageUrlBuilder;
use App\Services\PublicContent\Images\Transform\SimpleImageUrlBuilder;
use PHPUnit\Framework\TestCase;

final class RecognisedImageHostTransformerTest extends TestCase
{
    public function test_applies_simple_transform_for_recognised_host(): void
    {
        $url = $this->transformer()->transform(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(width: 500, quality: 60),
        );

        self::assertSame('https://cdn.example.com/folder/photo-w500-q60.jpg', $url);
    }

    public function test_never_upscales_above_original_width(): void
    {
        $url = $this->transformer()->transform(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(width: 2000, originalWidth: 800),
        );

        self::assertSame('https://cdn.example.com/folder/photo-w800-q80.jpg', $url);
    }

    public function test_respects_existing_params_and_chooses_rich_for_crop(): void
    {
        $url = $this->transformer()->transform(
            'https://cdn.example.com/folder/photo-w400-q50.jpg',
            new ImageTransformOptions(crop: ['t' => 1, 'l' => 2, 'cw' => 3, 'ch' => 4]),
        );

        self::assertStringContainsString('/v2/t:1,l:2,cw:3,ch:4,q:50,w:400/', $url);
    }

    private function transformer(): RecognisedImageHostTransformer
    {
        return new RecognisedImageHostTransformer(
            ['cdn.example.com'],
            new ImageUrlParameterReader(),
            new ImageUrlStyleChooser(),
            new SimpleImageUrlBuilder(),
            new RichImageUrlBuilder(),
        );
    }
}
