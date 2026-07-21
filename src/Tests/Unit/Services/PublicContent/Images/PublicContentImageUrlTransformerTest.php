<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Services\PublicContent\Images\PublicContentImageUrlResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;
use App\Services\PublicContent\Images\Transform\ImageTransformerInterface;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicContentImageUrlTransformerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rewrites_image_sources_in_rendered_html(): void
    {
        $html = $this->transformer()->transformHtml(
            '<img src="/storage/uploads/images/hero.jpg" alt="Hero"><img src="https://cdn.example.com/remote.jpg" alt="Remote">',
            99,
        );

        $this->assertStringContainsString('/public/images/', $html);
        $this->assertStringContainsString('https://cdn.example.com/remote.jpg', $html);
        $this->assertStringContainsString('onerror=', $html);
        $this->assertStringContainsString('/public/images/fallback', $html);
    }

    public function test_adds_missing_image_fallback_onerror_once(): void
    {
        $html = $this->transformer()->transformHtml(
            '<img src="https://cdn.example.com/gone.jpg" alt="Gone">',
            1,
        );

        $this->assertSame(1, preg_match_all('/\sonerror="/', $html));
        $this->assertStringContainsString("/public/images/fallback", $html);
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

    public function test_unrecognised_remote_host_is_left_alone(): void
    {
        $cdn = Mockery::mock(ImageTransformerInterface::class);
        $cdn->shouldReceive('transform')->once()->andReturn('https://unknown.example.com/a.jpg');

        $html = $this->transformer($cdn)->transformHtml(
            '<img src="https://unknown.example.com/a.jpg">',
            1,
        );

        $this->assertStringContainsString('https://unknown.example.com/a.jpg', $html);
        $this->assertStringNotContainsString('/public/images/', preg_replace('#/public/images/fallback#', '', $html) ?? $html);
        $this->assertStringContainsString('/public/images/fallback', $html);
    }

    public function test_transform_failure_keeps_original_and_does_not_break_page(): void
    {
        $cdn = Mockery::mock(ImageTransformerInterface::class);
        $cdn->shouldReceive('transform')->once()->andThrow(new RuntimeException('fail'));

        $html = $this->transformer($cdn)->transformHtml(
            '<img src="https://cdn.example.com/photo.jpg"><p>ok</p>',
            1,
        );

        $this->assertStringContainsString('https://cdn.example.com/photo.jpg', $html);
        $this->assertStringContainsString('<p>ok</p>', $html);
    }

    public function test_malformed_image_markup_does_not_fail_page(): void
    {
        $html = $this->transformer()->transformHtml(
            '<img src="/storage/uploads/images/hero.jpg"<p>still here</p>',
            1,
        );

        $this->assertStringContainsString('still here', $html);
    }

    private function transformer(?ImageTransformerInterface $cdn = null): PublicContentImageUrlTransformer
    {
        return new PublicContentImageUrlTransformer(
            new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()),
            $cdn,
        );
    }
}
