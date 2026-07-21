<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageTransformLogger;
use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\ImageTransformer;
use App\Services\PublicContent\Images\Transform\ImageTransformerInterface;
use App\Services\PublicContent\Images\Transform\ImageUrlParameterReader;
use App\Services\PublicContent\Images\Transform\ImageUrlStyleChooser;
use App\Services\PublicContent\Images\Transform\PassthroughImageTransformer;
use App\Services\PublicContent\Images\Transform\RecognisedImageHostTransformer;
use App\Services\PublicContent\Images\Transform\RichImageUrlBuilder;
use App\Services\PublicContent\Images\Transform\SimpleImageUrlBuilder;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ImageTransformerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unrecognised_host_passes_through_and_logs(): void
    {
        $logger = Mockery::mock(ImageTransformLogger::class);
        $logger->shouldReceive('warning')->once()->withArgs(function (string $message): bool {
            return str_contains($message, 'not recognised');
        });

        $transformer = $this->transformer(['cdn.example.com'], $logger);
        $original = 'https://unknown.example.com/photo.jpg';

        $result = $transformer->transform($original, new ImageTransformOptions(width: 400));

        self::assertSame($original, $result);
    }

    public function test_transform_failure_passes_through_and_logs_without_throwing(): void
    {
        $logger = Mockery::mock(ImageTransformLogger::class);
        $logger->shouldReceive('warning')->once()->withArgs(function (string $message): bool {
            return str_contains($message, 'could not be applied');
        });

        $recognised = Mockery::mock(ImageTransformerInterface::class);
        $recognised->shouldReceive('supports')->andReturn(true);
        $recognised->shouldReceive('transform')->andThrow(new RuntimeException('boom'));

        $transformer = new ImageTransformer(
            $recognised,
            new PassthroughImageTransformer(),
            $logger,
        );

        $original = 'https://cdn.example.com/photo.jpg';
        $result = $transformer->transform($original, new ImageTransformOptions(width: 400));

        self::assertSame($original, $result);
    }

    public function test_recognised_host_is_transformed(): void
    {
        $logger = Mockery::mock(ImageTransformLogger::class);
        $logger->shouldReceive('warning')->never();

        $result = $this->transformer(['cdn.example.com'], $logger)->transform(
            'https://cdn.example.com/folder/photo.jpg',
            new ImageTransformOptions(width: 400),
        );

        self::assertSame('https://cdn.example.com/folder/photo-w400-q80.jpg', $result);
    }

    private function transformer(array $hosts, ImageTransformLogger $logger): ImageTransformer
    {
        return new ImageTransformer(
            new RecognisedImageHostTransformer(
                $hosts,
                new ImageUrlParameterReader(),
                new ImageUrlStyleChooser(),
                new SimpleImageUrlBuilder(),
                new RichImageUrlBuilder(),
            ),
            new PassthroughImageTransformer(),
            $logger,
        );
    }
}
