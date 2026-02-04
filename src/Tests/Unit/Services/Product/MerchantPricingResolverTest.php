<?php

namespace App\Tests\Unit\Services\Product;

use App\Models\ProductVariant;
use App\Services\Product\MerchantPricingResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MerchantPricingResolverTest extends FunctionalTestCase
{
    private MerchantPricingResolver $resolver;

    public function testResolveWithOverridePrice()
    {
        $merchantData = [
            'price' => 120.00,
            'sale_price' => 110.00,
            'override_price' => true,
            'override_sale_price' => true,
        ];

        $result = $this->resolver->resolve($merchantData);

        $this->assertEquals(120.00, $result['price']);
        $this->assertEquals(110.00, $result['sale_price']);
    }

    public function testResolveWithVariantPrice()
    {
        $variant = new ProductVariant([
            'id' => 1,
            'price' => 150.00,
            'sale_price' => 140.00
        ]);

        $merchantData = [
            'price' => 120.00,
            'sale_price' => 110.00,
            'override_price' => false,
            'override_sale_price' => false,
            'variant_id' => 1,
        ];

        $variants = collect([$variant]);

        $result = $this->resolver->resolve($merchantData, $variants);

        $this->assertEquals(150.00, $result['price']);
        $this->assertEquals(140.00, $result['sale_price']);
    }

    public function testResolveWithMixedOverrides()
    {
        $variant = new ProductVariant([
            'id' => 1,
            'price' => 150.00,
            'sale_price' => 140.00
        ]);

        $merchantData = [
            'price' => 120.00,
            'sale_price' => 110.00,
            'override_price' => true,
            'override_sale_price' => false,
            'variant_id' => 1,
        ];

        $variants = collect([$variant]);

        $result = $this->resolver->resolve($merchantData, $variants);

        $this->assertEquals(120.00, $result['price']); // Override
        $this->assertEquals(140.00, $result['sale_price']); // From variant
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MerchantPricingResolver();
    }
}