<?php

namespace App\Tests\Unit\Services\PublicContent\Media;

use App\Services\PublicContent\Media\PublicContentMediaUrlTransformer;
use App\Services\PublicContent\Media\PublicMediaPathResolver;
use App\Services\PublicContent\Media\PublicMediaUrlSigner;
use PHPUnit\Framework\TestCase;

final class PublicContentMediaUrlTransformerTest extends TestCase
{
    public function testTransformsImageSrcInRenderedHtml(): void
    {
        putenv('PUBLIC_MEDIA_SIGNING_KEY=test-secret');

        $transformer = $this->transformer();
        $html = '<figure><img src="/storage/cms/hero.jpg" alt="Hero"><img src="https://cdn.example.com/logo.png"></figure>';

        $transformed = $transformer->transformHtml($html, 'demo-site');

        self::assertStringContainsString('/api/v1/demo-site/media/', $transformed);
        self::assertStringNotContainsString('src="/storage/cms/hero.jpg"', $transformed);
        self::assertStringContainsString('src="https://cdn.example.com/logo.png"', $transformed);
    }

    public function testTransformsSrcsetEntries(): void
    {
        putenv('PUBLIC_MEDIA_SIGNING_KEY=test-secret');

        $transformed = $this->transformer()->transformHtml(
            '<img srcset="/storage/cms/small.jpg 480w, /storage/cms/large.jpg 960w">',
            'demo-site',
        );

        self::assertSame(2, substr_count($transformed, '/api/v1/demo-site/media/'));
        self::assertStringContainsString('480w', $transformed);
        self::assertStringContainsString('960w', $transformed);
    }

    public function testTransformsStructuredBlockMediaFieldsRecursively(): void
    {
        putenv('PUBLIC_MEDIA_SIGNING_KEY=test-secret');

        $data = [
            'src' => '/storage/cms/main.webp',
            'alt' => 'Main image',
            'endorsements' => [
                'top-left' => ['url' => '/uploads/badges/editor-pick.png'],
            ],
            'external' => 'https://cdn.example.com/noop.jpg',
        ];

        $transformed = $this->transformer()->transformStructuredData($data, 'demo-site');

        self::assertStringStartsWith('/api/v1/demo-site/media/', $transformed['src']);
        self::assertStringStartsWith('/api/v1/demo-site/media/', $transformed['endorsements']['top-left']['url']);
        self::assertSame('https://cdn.example.com/noop.jpg', $transformed['external']);
    }

    private function transformer(): PublicContentMediaUrlTransformer
    {
        $resolver = new PublicMediaPathResolver();

        return new PublicContentMediaUrlTransformer(
            new PublicMediaUrlSigner($resolver),
            $resolver,
        );
    }
}
