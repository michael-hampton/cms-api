<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageSrcSetBuilder;
use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\ImageTransformerInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

final class ImageSrcSetBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_builds_width_tagged_urls_largest_first_with_no_upscale(): void
    {
        $transformer = Mockery::mock(ImageTransformerInterface::class);
        $transformer->shouldReceive('transform')
            ->times(3)
            ->andReturnUsing(function (string $url, ImageTransformOptions $options): string {
                return $url . '?w=' . $options->width;
            });

        $srcset = (new ImageSrcSetBuilder($transformer))->buildSrcSet(
            'https://cdn.example.com/photo.jpg',
            [320, 1600, 800],
            new ImageTransformOptions(originalWidth: 1000),
        );

        self::assertSame(
            'https://cdn.example.com/photo.jpg?w=1000 1000w, https://cdn.example.com/photo.jpg?w=800 800w, https://cdn.example.com/photo.jpg?w=320 320w',
            $srcset,
        );
    }
}
