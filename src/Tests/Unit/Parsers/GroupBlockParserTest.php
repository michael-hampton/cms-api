<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\GroupBlockParser;
use PHPUnit\Framework\TestCase;

class GroupBlockParserTest extends TestCase
{
    private GroupBlockParser $parser;

    public function test_it_identifies_spotlight_layout_correctly()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => [
                ['id' => 'img-1', 'type' => 'image', 'src' => '/test.jpg', 'alt' => 'Test']
            ]
        ];

        $parsed = $this->parser->parse($data);

        $this->assertTrue($parsed['is_spotlight']);
        $this->assertFalse($parsed['is_carousel']);
        $this->assertFalse($parsed['is_default']);
        $this->assertEquals('group-layout-spotlight', $parsed['css_layout_class']);
        $this->assertEquals('Spotlight Layout', $parsed['layout_display']);
    }

    public function test_it_generates_spotlight_html_with_correct_structure()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => [
                [
                    'id' => 'img-1',
                    'type' => 'image',
                    'src' => '/images/product-hero.jpg',
                    'alt' => 'Product hero image'
                ],
                [
                    'id' => 'prod-1',
                    'type' => 'product',
                    'name' => 'Buckled Heel Slingbacks'
                ],
                [
                    'id' => 'prod-2',
                    'type' => 'product',
                    'name' => 'Another Product'
                ]
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        // Check container structure
        $this->assertStringContainsString('group-spotlight-container', $html);
        $this->assertStringContainsString('data-layout="spotlight"', $html);

        // Check desktop layout elements
        $this->assertStringContainsString('spotlight-desktop', $html);
        $this->assertStringContainsString('spotlight-image-wrapper', $html);
        $this->assertStringContainsString('spotlight-content-wrapper', $html);

        // Check mobile layout elements
        $this->assertStringContainsString('spotlight-mobile', $html);
        $this->assertStringContainsString('spotlight-mobile-image', $html);
        $this->assertStringContainsString('spotlight-mobile-content', $html);
    }

    public function test_it_extracts_spotlight_data_with_first_image_only()
    {
        $blocks = [
            ['id' => 'img-1', 'type' => 'image', 'src' => '/first.jpg', 'alt' => 'First'],
            ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
            ['id' => 'img-2', 'type' => 'image', 'src' => '/second.jpg', 'alt' => 'Second'],
            ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2']
        ];

        $spotlightData = $this->parser->extractSpotlightData($blocks);

        // Should use only the first image
        $this->assertNotNull($spotlightData['image_block']);
        $this->assertEquals('img-1', $spotlightData['image_block']['id']);
        $this->assertEquals('image', $spotlightData['image_block']['type']);

        // Other blocks should be in content
        $this->assertCount(3, $spotlightData['content_blocks']);
        $this->assertTrue($spotlightData['has_image']);
        $this->assertEquals(3, $spotlightData['content_block_count']);
    }

    public function test_it_validates_spotlight_layout_requires_image()
    {
        $blocks = [
            ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
            ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2']
        ];

        $errors = $this->parser->validateSpotlightLayout($blocks);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('image block', $errors[0]);
    }

    public function test_it_validates_spotlight_layout_warns_no_products()
    {
        $blocks = [
            ['id' => 'img-1', 'type' => 'image', 'src' => '/test.jpg', 'alt' => 'Test'],
            ['id' => 'text-1', 'type' => 'text', 'content' => 'Some text']
        ];

        $errors = $this->parser->validateSpotlightLayout($blocks);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('product blocks', $errors[0]);
    }

    public function test_it_validates_spotlight_layout_warns_too_many_products()
    {
        $blocks = [
            ['id' => 'img-1', 'type' => 'image', 'src' => '/test.jpg', 'alt' => 'Test'],
            ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
            ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2'],
            ['id' => 'prod-3', 'type' => 'product', 'name' => 'Product 3'],
            ['id' => 'prod-4', 'type' => 'product', 'name' => 'Product 4'],
            ['id' => 'prod-5', 'type' => 'product', 'name' => 'Product 5'],
            ['id' => 'prod-6', 'type' => 'product', 'name' => 'Product 6']
        ];

        $errors = $this->parser->validateSpotlightLayout($blocks);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('maximum', $errors[0]);
        $this->assertStringContainsString('5', $errors[0]);
    }

    public function test_it_validates_spotlight_layout_passes_with_valid_structure()
    {
        $blocks = [
            ['id' => 'img-1', 'type' => 'image', 'src' => '/test.jpg', 'alt' => 'Test'],
            ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
            ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2'],
            ['id' => 'prod-3', 'type' => 'product', 'name' => 'Product 3']
        ];

        $errors = $this->parser->validateSpotlightLayout($blocks);

        $this->assertEmpty($errors);
    }

    public function test_it_falls_back_to_default_layout_when_spotlight_has_no_image()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => [
                ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
                ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2']
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        // Should fall back to default layout
        $this->assertStringContainsString('group-default-container', $html);
        $this->assertStringNotContainsString('spotlight-desktop', $html);
    }

    public function test_it_renders_image_with_proper_html_attributes()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => [
                [
                    'id' => 'img-1',
                    'type' => 'image',
                    'src' => '/images/hero.jpg',
                    'alt' => 'Hero image description',
                    'caption' => 'Photo credit: John Doe'
                ]
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('src="/images/hero.jpg"', $html);
        $this->assertStringContainsString('alt="Hero image description"', $html);
        $this->assertStringContainsString('Photo credit: John Doe', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('<figure class="spotlight-image">', $html);
        $this->assertStringContainsString('<figcaption>', $html);
    }

    public function test_it_escapes_html_in_image_attributes()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => [
                [
                    'id' => 'img-1',
                    'type' => 'image',
                    'src' => '/test.jpg',
                    'alt' => '<script>alert("xss")</script>',
                    'caption' => '<b>Bold</b> caption'
                ]
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function test_it_handles_image_without_caption()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => [
                [
                    'id' => 'img-1',
                    'type' => 'image',
                    'src' => '/test.jpg',
                    'alt' => 'Test image'
                ]
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringNotContainsString('<figcaption>', $html);
    }

    public function test_it_supports_title_for_spotlight_layout()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => []
        ];

        $parsedData = $this->parser->parse($data);

        $this->assertTrue($parsedData['supports_title']);
    }

    public function test_it_sets_correct_max_recommended_blocks_for_spotlight()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => []
        ];

        $parsedData = $this->parser->parse($data);

        $this->assertEquals(5, $parsedData['max_recommended_blocks']);
    }

    public function test_it_renders_carousel_layout_correctly()
    {
        $data = [
            'layout' => 'carousel',
            'carouselTitle' => 'Featured Products',
            'blocks' => [
                ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
                ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2']
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('group-carousel-container', $html);
        $this->assertStringContainsString('data-layout="carousel"', $html);
        $this->assertStringContainsString('carousel-title', $html);
        $this->assertStringContainsString('Featured Products', $html);
        $this->assertStringContainsString('carousel-wrapper', $html);
        $this->assertStringContainsString('carousel-track', $html);
        $this->assertStringContainsString('carousel-item', $html);
    }

    public function test_it_renders_default_layout_correctly()
    {
        $data = [
            'layout' => 'default',
            'blocks' => [
                ['id' => 'text-1', 'type' => 'text', 'content' => 'Some text'],
                ['id' => 'img-1', 'type' => 'image', 'src' => '/test.jpg', 'alt' => 'Test']
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('group-default-container', $html);
        $this->assertStringContainsString('data-layout="default"', $html);
    }

    public function test_it_renders_each_product_block_in_spotlight_content()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => [
                ['id' => 'img-1', 'type' => 'image', 'src' => '/test.jpg', 'alt' => 'Test'],
                ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
                ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2'],
                ['id' => 'prod-3', 'type' => 'product', 'name' => 'Product 3']
            ]
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        // Image should appear in both desktop and mobile wrappers
        $this->assertEquals(3, substr_count($html, 'spotlight-image'));

        // Products should be in content areas
        $this->assertEquals(1, substr_count($html, 'spotlight-content-wrapper'));
    }

    public function test_it_handles_empty_blocks_array()
    {
        $data = [
            'layout' => 'spotlight',
            'blocks' => []
        ];

        $parsedData = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsedData);

        $this->assertEquals('', $html);
    }

    public function test_it_parses_spotlight_with_mixed_block_types()
    {
        $blocks = [
            ['id' => 'img-1', 'type' => 'image', 'src' => '/test.jpg', 'alt' => 'Test'],
            ['id' => 'heading-1', 'type' => 'heading', 'text' => 'Section Title'],
            ['id' => 'prod-1', 'type' => 'product', 'name' => 'Product 1'],
            ['id' => 'text-1', 'type' => 'text', 'content' => 'Description'],
            ['id' => 'prod-2', 'type' => 'product', 'name' => 'Product 2']
        ];

        $spotlightData = $this->parser->extractSpotlightData($blocks);

        $this->assertCount(4, $spotlightData['content_blocks']);
        $this->assertTrue($spotlightData['has_image']);

        // Verify image is first block
        $this->assertEquals('image', $spotlightData['image_block']['type']);

        // Verify other blocks are preserved in order
        $this->assertEquals('heading', $spotlightData['content_blocks'][0]['type']);
        $this->assertEquals('product', $spotlightData['content_blocks'][1]['type']);
        $this->assertEquals('text', $spotlightData['content_blocks'][2]['type']);
        $this->assertEquals('product', $spotlightData['content_blocks'][3]['type']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GroupBlockParser();
    }
}