<?php

namespace App\Tests\Unit\Services\Product\FeedMappers;

use App\Services\Product\FeedMappers\DefaultFeedMapper;
use PHPUnit\Framework\TestCase;

class DefaultFeedMapperTest extends TestCase
{
    private DefaultFeedMapper $mapper;

    public function testMapWithStandardFields()
    {
        $data = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 99.99,
            'sale_price' => 79.99,
            'sku' => 'TEST123',
            'url' => 'https://example.com/product',
            'image' => 'https://example.com/image.jpg',
            'category' => 'Electronics',
            'brand' => 'Test Brand',
            'in_stock' => true
        ];

        $result = $this->mapper->map($data);

        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals('Test Description', $result['description']);
        $this->assertEquals(99.99, $result['price']);
        $this->assertEquals(79.99, $result['sale_price']);
        $this->assertEquals('TEST123', $result['sku']);
        $this->assertEquals('https://example.com/product', $result['url']);
        $this->assertEquals('https://example.com/image.jpg', $result['image']);
        $this->assertEquals('Electronics', $result['category']);
        $this->assertEquals('Test Brand', $result['brand']);
        $this->assertTrue($result['in_stock']);
    }

    public function testMapWithAlternativeFieldNames()
    {
        $data = [
            'title' => 'Test Product',
            'desc' => 'Test Description',
            'price' => 99.99,
            'salePrice' => 79.99,
            'id' => 'TEST123',
            'link' => 'https://example.com/product',
            'imageUrl' => 'https://example.com/image.jpg',
            'categories' => 'Electronics',
            'manufacturer' => 'Test Brand',
            'available' => 'yes'
        ];

        $result = $this->mapper->map($data);

        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals('Test Description', $result['description']);
        $this->assertEquals(79.99, $result['sale_price']);
        $this->assertEquals('TEST123', $result['sku']);
        $this->assertEquals('https://example.com/product', $result['url']);
        $this->assertEquals('https://example.com/image.jpg', $result['image']);
        $this->assertTrue($result['in_stock']);
    }

    public function testMapWithMissingFields()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99
        ];

        $result = $this->mapper->map($data);

        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals(99.99, $result['price']);
        $this->assertEquals('', $result['description']);
        $this->assertNull($result['sale_price']);
        $this->assertEquals('', $result['sku']);
    }

    public function testMapHandlesBooleanStrings()
    {
        $data = [
            'name' => 'Test',
            'price' => 10,
            'in_stock' => 'true'
        ];

        $result = $this->mapper->map($data);
        $this->assertTrue($result['in_stock']);

        $data['in_stock'] = 'false';
        $result = $this->mapper->map($data);
        $this->assertFalse($result['in_stock']);

        $data['in_stock'] = 'available';
        $result = $this->mapper->map($data);
        $this->assertTrue($result['in_stock']);
    }

    public function testSupportsReturnsTrue()
    {
        $this->assertTrue($this->mapper->supports('https://example.com', 1));
        $this->assertTrue($this->mapper->supports('https://another.com', 999));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new DefaultFeedMapper();
    }
}