<?php

namespace App\Tests\Unit\Services\Billing\Preorder\FeedMappers;

use App\Services\Product\FeedMappers\EbayFeedMapper;
use PHPUnit\Framework\TestCase;

class EbayFeedMapperTest extends TestCase
{
    private EbayFeedMapper $mapper;

    public function testMapWithEbayFields()
    {
        $data = [
            'Title' => 'eBay Item',
            'Description' => 'eBay Description',
            'CurrentPrice' => 199.99,
            'SalePrice' => 179.99,
            'ItemID' => 'EBAY12345',
            'ViewItemURL' => 'https://ebay.com/item/12345',
            'PictureURL' => 'https://ebay.com/pic.jpg',
            'PrimaryCategory' => 'Collectibles',
            'Brand' => 'eBay Brand',
            'QuantityAvailable' => '5'
        ];

        $result = $this->mapper->map($data);

        $this->assertEquals('eBay Item', $result['name']);
        $this->assertEquals('eBay Description', $result['description']);
        $this->assertEquals(199.99, $result['price']);
        $this->assertEquals(179.99, $result['sale_price']);
        $this->assertEquals('EBAY12345', $result['sku']);
        $this->assertEquals('https://ebay.com/item/12345', $result['url']);
        $this->assertEquals('https://ebay.com/pic.jpg', $result['image']);
        $this->assertEquals('Collectibles', $result['category']);
        $this->assertEquals('eBay Brand', $result['brand']);
        $this->assertTrue($result['in_stock']);
    }

    public function testMapFallsBackToStandardFields()
    {
        $data = [
            'name' => 'Standard Item',
            'price' => 49.99,
            'sku' => 'STD123'
        ];

        $result = $this->mapper->map($data);

        $this->assertEquals('Standard Item', $result['name']);
        $this->assertEquals(49.99, $result['price']);
        $this->assertEquals('STD123', $result['sku']);
    }

    public function testSupportsEbayUrls()
    {
        $this->assertTrue($this->mapper->supports('https://ebay.com/feed.xml', 1));
        $this->assertTrue($this->mapper->supports('https://www.ebay.co.uk/feed.xml', 1));
        $this->assertTrue($this->mapper->supports('https://eBay.de/feed.json', 1));
    }

    public function testDoesNotSupportNonEbayUrls()
    {
        $this->assertFalse($this->mapper->supports('https://amazon.com/feed.xml', 1));
        $this->assertFalse($this->mapper->supports('https://example.com/feed.xml', 1));
    }

    public function testHandlesZeroQuantityAsOutOfStock()
    {
        $data = [
            'Title' => 'Out of Stock Item',
            'CurrentPrice' => 99.99,
            'ItemID' => 'OOS123',
            'QuantityAvailable' => 0
        ];

        $result = $this->mapper->map($data);
        $this->assertFalse($result['in_stock']);
    }

    public function testHandlesStringBooleanValues()
    {
        $data = [
            'Title' => 'Test',
            'price' => 10,
            'sku' => 'TEST',
            'QuantityAvailable' => 'true'
        ];

        $result = $this->mapper->map($data);
        $this->assertTrue($result['in_stock']);

        $data['QuantityAvailable'] = 'false';
        $result = $this->mapper->map($data);
        $this->assertFalse($result['in_stock']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new EbayFeedMapper();
    }
}