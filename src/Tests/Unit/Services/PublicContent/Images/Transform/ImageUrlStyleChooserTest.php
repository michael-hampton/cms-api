<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageUrlStyle;
use App\Services\PublicContent\Images\Transform\ImageUrlStyleChooser;
use PHPUnit\Framework\TestCase;

final class ImageUrlStyleChooserTest extends TestCase
{
    public function test_chooses_rich_when_crop_present(): void
    {
        self::assertSame(
            ImageUrlStyle::Rich,
            (new ImageUrlStyleChooser())->choose(true, false),
        );
    }

    public function test_chooses_rich_when_source_already_rich(): void
    {
        self::assertSame(
            ImageUrlStyle::Rich,
            (new ImageUrlStyleChooser())->choose(false, true),
        );
    }

    public function test_chooses_simple_otherwise(): void
    {
        self::assertSame(
            ImageUrlStyle::Simple,
            (new ImageUrlStyleChooser())->choose(false, false),
        );
    }

    public function test_quality_default_rules_differ_by_style(): void
    {
        // Documented contract: simple defaults quality only with width;
        // rich defaults quality even with no width. Proven via builders.
        $simple = (new \App\Services\PublicContent\Images\Transform\SimpleImageUrlBuilder())->build(
            'https://cdn.example.com/a.jpg',
            new \App\Services\PublicContent\Images\Transform\ImageTransformOptions(),
        );
        $rich = (new \App\Services\PublicContent\Images\Transform\RichImageUrlBuilder())->build(
            'https://cdn.example.com/a.jpg',
            new \App\Services\PublicContent\Images\Transform\ImageTransformOptions(
                crop: ['t' => 0, 'l' => 0, 'cw' => 1, 'ch' => 1],
            ),
        );

        self::assertSame('https://cdn.example.com/a.jpg', $simple);
        self::assertStringContainsString('q:80', $rich);
    }
}
