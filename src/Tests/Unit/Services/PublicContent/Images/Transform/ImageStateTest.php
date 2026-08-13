<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageState;
use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\ImageUrlParameterReader;
use PHPUnit\Framework\TestCase;

final class ImageStateTest extends TestCase
{
    public function test_mutable_state_carries_width_quality_crop_and_format(): void
    {
        $state = new ImageState(width: 800, quality: 75, crop: null, format: 'webp');
        $state->width = 640;
        $state->crop = ['t' => 1, 'l' => 2, 'cw' => 3, 'ch' => 4];

        $options = $state->toOptions(1000);

        self::assertSame(640, $options->width);
        self::assertSame(75, $options->quality);
        self::assertSame('webp', $options->format);
        self::assertSame(['t' => 1, 'l' => 2, 'cw' => 3, 'ch' => 4], $options->crop);
        self::assertSame(1000, $options->originalWidth);
    }

    public function test_reads_crop_geometry_back_from_existing_url_parameters(): void
    {
        $params = (new ImageUrlParameterReader())->read(
            'https://cdn.example.com/v2/t:10,l:20,cw:300,ch:200,q:75,w:800/folder/photo.jpg',
        );
        $state = ImageState::fromParameters($params);

        self::assertSame(800, $state->width);
        self::assertSame(75, $state->quality);
        self::assertSame(['t' => 10, 'l' => 20, 'cw' => 300, 'ch' => 200], $state->crop);
    }

    public function test_round_trips_transform_options(): void
    {
        $options = new ImageTransformOptions(width: 400, quality: 80, format: 'jpg');
        $state = ImageState::fromOptions($options);

        self::assertEquals($options->width, $state->toOptions()->width);
        self::assertEquals($options->quality, $state->toOptions()->quality);
        self::assertEquals($options->format, $state->toOptions()->format);
    }
}
