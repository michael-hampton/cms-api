<?php

namespace App\Tests\Unit\Services\Shopping\Resolvers;

use App\DTO\Cart\StockAvailability;
use App\Exceptions\Cart\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Shopping\Resolvers\CartStockResolver;
use App\Tests\Unit\UnitTestCase;

class CartStockResolverTest extends UnitTestCase
{
    private CartStockResolver $resolver;

    public function testGetAvailabilityReturnsStockAvailabilityObject(): void
    {
        $product = new Product(['stock_quantity' => 100]);

        $result = $this->resolver->getAvailability($product, null);

        $this->assertInstanceOf(StockAvailability::class, $result);
        $this->assertEquals(100, $result->available);
    }

    public function testGetAvailabilityPrefersVariantStock(): void
    {
        $product = new Product(['stock_quantity' => 100]);
        $variant = new ProductVariant(['stock_quantity' => 50]);

        $result = $this->resolver->getAvailability($product, $variant);

        $this->assertEquals(50, $result->available);
    }

    public function testGetAvailabilityReturnsNullForUnlimitedStock(): void
    {
        $product = new Product(['stock_quantity' => null]);

        $result = $this->resolver->getAvailability($product, null);

        $this->assertNull($result->available);
        $this->assertTrue($result->isUnlimited());
    }

    public function testGetAvailableStockReturnsVariantStockWhenVariantExists(): void
    {
        $product = new Product(['stock_quantity' => 100]);
        $variant = new ProductVariant(['stock_quantity' => 50]);

        $result = $this->resolver->getAvailableStock($product, $variant);

        $this->assertEquals(50, $result);
    }

    public function testGetAvailableStockReturnsProductStockWhenNoVariant(): void
    {
        $product = new Product(['stock_quantity' => 100]);

        $result = $this->resolver->getAvailableStock($product, null);

        $this->assertEquals(100, $result);
    }

    public function testGetAvailableStockReturnsNullForUnlimitedStock(): void
    {
        $product = new Product(['stock_quantity' => null]);

        $result = $this->resolver->getAvailableStock($product, null);

        $this->assertNull($result);
    }

    public function testAssertCanAddPassesWhenStockIsUnlimited(): void
    {
        $product = new Product(['stock_quantity' => null]);

        // Should not throw
        $this->resolver->assertCanAdd($product, null, 999999);

        $this->assertTrue(true);
    }

    public function testAssertCanAddPassesWhenQuantityIsAvailable(): void
    {
        $product = new Product(['stock_quantity' => 10]);

        // Should not throw
        $this->resolver->assertCanAdd($product, null, 5);

        $this->assertTrue(true);
    }

    public function testAssertCanAddThrowsWhenInsufficientStock(): void
    {
        $this->expectException(InsufficientStockException::class);

        $product = new Product(['stock_quantity' => 5]);
        $product->id = 1;

        $this->resolver->assertCanAdd($product, null, 10);
    }

    public function testAssertCanAddThrowsWhenVariantStockInsufficient(): void
    {
        $this->expectException(InsufficientStockException::class);

        $product = new Product(['stock_quantity' => 100]);
        $product->id = 1;
        $variant = new ProductVariant(['stock_quantity' => 3]);
        $variant->id = 5;

        $this->resolver->assertCanAdd($product, $variant, 5);
    }

    public function testAssertCanUpdatePassesWhenStockIsUnlimited(): void
    {
        $product = new Product(['stock_quantity' => null]);

        // Should not throw
        $this->resolver->assertCanUpdate($product, null, 999999);

        $this->assertTrue(true);
    }

    public function testAssertCanUpdatePassesWhenQuantityIsAvailable(): void
    {
        $product = new Product(['stock_quantity' => 10]);

        // Should not throw
        $this->resolver->assertCanUpdate($product, null, 8);

        $this->assertTrue(true);
    }

    public function testAssertCanUpdateThrowsWhenInsufficientStock(): void
    {
        $this->expectException(InsufficientStockException::class);

        $product = new Product(['stock_quantity' => 5]);
        $product->id = 1;

        $this->resolver->assertCanUpdate($product, null, 10);
    }

    public function testAssertCanUpdateThrowsWhenVariantStockInsufficient(): void
    {
        $this->expectException(InsufficientStockException::class);

        $product = new Product(['stock_quantity' => 100]);
        $product->id = 1;
        $variant = new ProductVariant(['stock_quantity' => 2]);
        $variant->id = 5;

        $this->resolver->assertCanUpdate($product, $variant, 5);
    }

    public function testExceptionContainsStructuredData(): void
    {
        $product = new Product(['stock_quantity' => 5]);
        $product->id = 1;

        try {
            $this->resolver->assertCanAdd($product, null, 10);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertEquals(10, $e->requestedQuantity);
            $this->assertEquals(5, $e->availableQuantity);
            $this->assertEquals(1, $e->productId);
            $this->assertNull($e->variantId);
        }
    }

    protected function setUp(): void
    {
        $this->resolver = new CartStockResolver();
    }
}