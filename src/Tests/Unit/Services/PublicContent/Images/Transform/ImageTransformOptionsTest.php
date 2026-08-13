<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use PHPUnit\Framework\TestCase;

final class ImageTransformOptionsTest extends TestCase
{
    public function test_has_crop_is_false_without_a_crop_box(): void
    {
        $options = new ImageTransformOptions();

        self::assertFalse($options->hasCrop());
    }

    public function test_has_crop_is_true_with_a_crop_box(): void
    {
        $options = new ImageTransformOptions(crop: ['t' => 0, 'l' => 0, 'cw' => 100, 'ch' => 100]);

        self::assertTrue($options->hasCrop());
    }

    public function test_with_width_returns_a_new_instance_with_only_width_changed(): void
    {
        $options = new ImageTransformOptions(
            width: 200,
            quality: 80,
            format: 'webp',
            crop: ['t' => 1, 'l' => 2, 'cw' => 3, 'ch' => 4],
            originalWidth: 1000,
        );

        $resized = $options->withWidth(400);

        self::assertNotSame($options, $resized);
        self::assertSame(400, $resized->width);
        self::assertSame(80, $resized->quality);
        self::assertSame('webp', $resized->format);
        self::assertSame(['t' => 1, 'l' => 2, 'cw' => 3, 'ch' => 4], $resized->crop);
        self::assertSame(1000, $resized->originalWidth);

        // original is unchanged
        self::assertSame(200, $options->width);
    }

    public function test_with_width_accepts_null_to_clear_the_width(): void
    {
        $options = (new ImageTransformOptions(width: 200))->withWidth(null);

        self::assertNull($options->width);
    }
}