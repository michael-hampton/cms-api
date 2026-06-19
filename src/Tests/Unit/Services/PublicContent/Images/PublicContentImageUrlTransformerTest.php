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

        $this->assertStringContainsString('/public/images/', $html);
        $this->assertStringContainsString('https://cdn.example.com/remote.jpg', $html);
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

        $this->assertStringStartsWith('/public/images/', $blocks[0]['data']['src']);
        $this->assertStringStartsWith('/public/images/', $blocks[0]['data']['endorsements']['top-left']['url']);
        $this->assertSame('https://example.com/page', $blocks[0]['data']['linkUrl']);
    }

    public function test_rewrites_product_deal_and_gallery_image_fields(): void
    {
        $blocks = $this->transformer()->transformBlocks([
            [
                'id' => 1,
                'type' => 'product',
                'order' => 1,
                'data' => [
                    'image_url' => '/storage/uploads/products/product.jpg',
                    'thumbnailUrl' => '/storage/uploads/products/thumb.jpg',
                ],
            ],
            [
                'id' => 2,
                'type' => 'deal',
                'order' => 2,
                'data' => [
                    'deal_image' => '/storage/uploads/deals/deal.webp',
                    'mainImage' => '/storage/uploads/deals/main.webp',
                ],
            ],
            [
                'id' => 3,
                'type' => 'gallery',
                'order' => 3,
                'data' => [
                    'images' => [
                        ['src' => '/storage/uploads/gallery/one.jpg'],
                        ['galleryImage' => '/storage/uploads/gallery/two.jpg'],
                    ],
                ],
            ],
        ], 'site-a');

        $this->assertStringStartsWith('/public/images/', $blocks[0]['data']['image_url']);
        $this->assertStringStartsWith('/public/images/', $blocks[0]['data']['thumbnailUrl']);
        $this->assertStringStartsWith('/public/images/', $blocks[1]['data']['deal_image']);
        $this->assertStringStartsWith('/public/images/', $blocks[1]['data']['mainImage']);
        $this->assertStringStartsWith('/public/images/', $blocks[2]['data']['images'][0]['src']);
        $this->assertStringStartsWith('/public/images/', $blocks[2]['data']['images'][1]['galleryImage']);
    }

    private function transformer(): PublicContentImageUrlTransformer
    {
        return new PublicContentImageUrlTransformer(
            new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()),
        );
    }
}
