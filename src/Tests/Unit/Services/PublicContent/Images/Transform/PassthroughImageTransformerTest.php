<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\PassthroughImageTransformer;
use PHPUnit\Framework\TestCase;

final class PassthroughImageTransformerTest extends TestCase
{
    public function test_it_never_claims_to_support_any_url(): void
    {
        $transformer = new PassthroughImageTransformer();

        self::assertFalse($transformer->supports('https://example.test/image.jpg'));
        self::assertFalse($transformer->supports(''));
    }

    public function test_it_returns_the_url_unchanged(): void
    {
        $transformer = new PassthroughImageTransformer();

        self::assertSame(
            'https://example.test/image.jpg',
            $transformer->transform('https://example.test/image.jpg', new ImageTransformOptions(width: 400)),
        );
    }
}