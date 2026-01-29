<?php

namespace App\Tests\Unit\Services\Product\FeedMappers;

use App\Services\Product\FeedMappers\AmazonFeedMapper;
use PHPUnit\Framework\TestCase;

class AmazonFeedMapperTest extends TestCase
{
    private AmazonFeedMapper $mapper;

    public function testMapWithAmazonFields()
    {
        $data = [
            'item_name' => 'Amazon Product',
            'item_description' => 'Amazon Description',
            'your_price' => 149.99,
            'sale_price' => 129.99,
            'asin' => 'B08N5WRWNW',
            'product_url' => 'https://amazon.com/product',
            'main_image_url' => 'https://amazon.com/image.jpg',
            'product_category' => 'Books',
            'brand_name' => 'Amazon Basics',
            'availability' => 'in stock'
        ];

        $result = $this->mapper->map($data);

        $this->assertEquals('Amazon Product', $result['name']);
        $this->assertEquals('Amazon Description', $result['description']);
        $this->assertEquals(149.99, $result['price']);
        $this->assertEquals(129.99, $result['sale_price']);
        $this->assertEquals('B08N5WRWNW', $result['sku']);
        $this->assertEquals('https://amazon.com/product', $result['url']);
        $this->assertEquals('https://amazon.com/image.jpg', $result['image']);
        $this->assertEquals('Books', $result['category']);
        $this->assertEquals('Amazon Basics', $result['brand']);
        $this->assertTrue($result['in_stock']);
    }

    public function testMapFallsBackToStandardFields()
    {
        $data = [
            'name' => 'Product',
            'price' => 99.99,
            'sku' => 'TEST123'
        ];

        $result = $this->mapper->map($data);

        $this->assertEquals('Product', $result['name']);
        $this->assertEquals(99.99, $result['price']);
        $this->assertEquals('TEST123', $result['sku']);
    }

    public function testSupportsAmazonUrls()
    {
        $this->assertTrue($this->mapper->supports('https://amazon.com/feed.xml', 1));
        $this->assertTrue($this->mapper->supports('https://www.amazon.co.uk/feed.xml', 1));
        $this->assertTrue($this->mapper->supports('https://Amazon.de/feed.xml', 1));
    }

    public function testDoesNotSupportNonAmazonUrls()
    {
        $this->assertFalse($this->mapper->supports('https://ebay.com/feed.xml', 1));
        $this->assertFalse($this->mapper->supports('https://example.com/feed.xml', 1));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new AmazonFeedMapper();
    }
}