<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageUrlParameters;
use App\Services\PublicContent\Images\Transform\ImageUrlStyle;
use PHPUnit\Framework\TestCase;

final class ImageUrlParametersTest extends TestCase
{
    public function test_has_crop_is_false_without_a_crop_box(): void
    {
        $params = new ImageUrlParameters(style: ImageUrlStyle::Simple, width: 100, quality: 80, crop: null, baseUrl: '/img.jpg');

        self::assertFalse($params->hasCrop());
    }

    public function test_has_crop_is_true_with_a_crop_box(): void
    {
        $params = new ImageUrlParameters(
            style: ImageUrlStyle::Rich,
            width: 100,
            quality: 80,
            crop: ['t' => 0, 'l' => 0, 'cw' => 10, 'ch' => 10],
            baseUrl: '/img.jpg',
        );

        self::assertTrue($params->hasCrop());
    }

    public function test_is_rich_is_true_only_for_the_rich_style(): void
    {
        $rich = new ImageUrlParameters(style: ImageUrlStyle::Rich, width: null, quality: null, crop: null, baseUrl: '/img.jpg');
        $simple = new ImageUrlParameters(style: ImageUrlStyle::Simple, width: null, quality: null, crop: null, baseUrl: '/img.jpg');
        $none = new ImageUrlParameters(style: null, width: null, quality: null, crop: null, baseUrl: '/img.jpg');

        self::assertTrue($rich->isRich());
        self::assertFalse($simple->isRich());
        self::assertFalse($none->isRich());
    }
}