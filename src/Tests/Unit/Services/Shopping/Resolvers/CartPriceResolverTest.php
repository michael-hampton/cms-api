<?php

namespace App\Tests\Unit\Services\Shopping\Resolvers;

use App\Models\Product;
use App\Services\Shopping\Resolvers\CartPriceResolver;
use App\Tests\Unit\UnitTestCase;

class CartPriceResolverTest extends UnitTestCase
{
    private CartPriceResolver $resolver;

    public function testResolveReturnsProductBasePrice(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 0
        ]);

        $result = $this->resolver->resolve($product, null);

        $this->assertEquals(99.99, $result);
    }

    public function testResolveReturnsProductSalePriceWhenValid(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 79.99
        ]);

        $result = $this->resolver->resolve($product, null);

        $this->assertEquals(79.99, $result);
    }

    public function testResolveIgnoresZeroSalePrice(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 0
        ]);

        $result = $this->resolver->resolve($product, null);

        $this->assertEquals(99.99, $result);
    }

    public function testResolveIgnoresNullSalePrice(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => null
        ]);

        $result = $this->resolver->resolve($product, null);

        $this->assertEquals(99.99, $result);
    }

    public function testResolveIgnoresSalePriceHigherThanBasePrice(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 150.00  // Invalid - higher than base
        ]);

        $result = $this->resolver->resolve($product, null);

        $this->assertEquals(99.99, $result);
    }

    public function testResolveIgnoresSalePriceEqualToBasePrice(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 99.99  // Invalid - no actual discount
        ]);

        $result = $this->resolver->resolve($product, null);

        $this->assertEquals(99.99, $result);
    }

    public function testGetBasePriceReturnsProductPrice(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 79.99
        ]);

        $result = $this->resolver->getBasePrice($product, null);

        $this->assertEquals(99.99, $result);
    }

    public function testGetDiscountReturnsZeroWhenNoSale(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 0
        ]);

        $result = $this->resolver->getDiscount($product, null);

        $this->assertEquals(0.0, $result);
    }

    public function testGetDiscountReturnsCorrectAmount(): void
    {
        $product = new Product([
            'price' => 100.00,
            'sale_price' => 75.00
        ]);

        $result = $this->resolver->getDiscount($product, null);

        $this->assertEquals(25.00, $result);
    }

    public function testGetDiscountPercentageReturnsZeroWhenNoSale(): void
    {
        $product = new Product([
            'price' => 99.99,
            'sale_price' => 0
        ]);

        $result = $this->resolver->getDiscountPercentage($product, null);

        $this->assertEquals(0.0, $result);
    }

    public function testGetDiscountPercentageReturnsCorrectPercentage(): void
    {
        $product = new Product([
            'price' => 100.00,
            'sale_price' => 75.00
        ]);

        $result = $this->resolver->getDiscountPercentage($product, null);

        $this->assertEquals(25.0, $result);
    }

    protected function setUp(): void
    {
        $this->resolver = new CartPriceResolver();
    }
}