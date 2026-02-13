<?php

namespace App\Tests\Unit\Services\Billing\Preorder\FeedMappers;

use App\Services\Product\FeedMappers\AmazonFeedMapper;
use App\Services\Product\FeedMappers\DefaultFeedMapper;
use App\Services\Product\FeedMappers\EbayFeedMapper;
use App\Services\Product\FeedMappers\FeedMapperRegistry;
use PHPUnit\Framework\TestCase;

class FeedMapperRegistryTest extends TestCase
{
    private FeedMapperRegistry $registry;

    public function testRegisterAddsMapper()
    {
        $mapper = new AmazonFeedMapper();
        $result = $this->registry->register($mapper);

        $this->assertSame($this->registry, $result);
    }

    public function testGetMapperReturnsDefaultWhenNoMatchFound()
    {
        $mapper = $this->registry->getMapper('https://example.com', 1);

        $this->assertInstanceOf(DefaultFeedMapper::class, $mapper);
    }

    public function testGetMapperReturnsSpecificMapper()
    {
        $this->registry->register(new AmazonFeedMapper());

        $mapper = $this->registry->getMapper('https://amazon.com/feed', 1);

        $this->assertInstanceOf(AmazonFeedMapper::class, $mapper);
    }

    public function testGetMapperRespectsRegistrationOrder()
    {
        $this->registry->register(new AmazonFeedMapper());
        $this->registry->register(new EbayFeedMapper());

        // Amazon should match first
        $mapper = $this->registry->getMapper('https://amazon.com/feed', 1);
        $this->assertInstanceOf(AmazonFeedMapper::class, $mapper);

        // eBay should match
        $mapper = $this->registry->getMapper('https://ebay.com/feed', 1);
        $this->assertInstanceOf(EbayFeedMapper::class, $mapper);

        // Unknown should return default
        $mapper = $this->registry->getMapper('https://unknown.com/feed', 1);
        $this->assertInstanceOf(DefaultFeedMapper::class, $mapper);
    }

    public function testSetDefaultMapperChangesDefault()
    {
        $customDefault = new AmazonFeedMapper();
        $this->registry->setDefaultMapper($customDefault);

        $mapper = $this->registry->getMapper('https://unknown.com', 1);

        $this->assertSame($customDefault, $mapper);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FeedMapperRegistry();
    }
}