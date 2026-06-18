<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Services\PublicContent\Images\PublicContentImageUrlResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;
use PHPUnit\Framework\TestCase;

final class PublicContentImageUrlTransformerTest extends TestCase
{
    public function test_rewrites_image_sources_in_rendered_html(): void
    {
        $html = $this->transformer()->transformHtml(
            '<img src="/storage/uploads/images/hero.jpg" alt="Hero"><img src="https://cdn.example.com/remote.jpg" alt="Remote">',
            99,
        );

        self::assertStringContainsString('/api/v1/99/content-images/', $html);
        self::assertStringContainsString('https://cdn.example.com/remote.jpg', $html);
    }

    public function test_rewrites_structured_block_image_fields_recursively(): void
    {
        $blocks = $this->transformer()->transformBlocks([
            [
                'id' => 1,
                'type' => 'image',
                'order' => 1,
                'data' => [
                    'src' => '/storage/uploads/images/hero.jpg',
                    'endorsements' => [
                        'top-left' => [
                            'url' => '/storage/uploads/images/badge.png',
                        ],
                    ],
                    'linkUrl' => 'https://example.com/page',
                ],
            ],
        ], 12);

        self::assertStringStartsWith('/api/v1/12/content-images/', $blocks[0]['data']['src']);
        self::assertStringStartsWith('/api/v1/12/content-images/', $blocks[0]['data']['endorsements']['top-left']['url']);
        self::assertSame('https://example.com/page', $blocks[0]['data']['linkUrl']);
    }

    private function transformer(): PublicContentImageUrlTransformer
    {
        return new PublicContentImageUrlTransformer(
            new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()),
        );
    }
}
