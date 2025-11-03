<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductMerchant;
use App\Models\ProductVariant;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductMerchantModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_merchant_belongs_to_product()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 99.99,
            'is_available' => true,
            'url' => 'https://example.com/product.jpg',
        ]);

        $this->assertNotNull($productMerchant->product);
        $this->assertEquals($product->id, $productMerchant->product->id);
    }

    public function test_merchant_belongs_to_variant()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $merchant->id,
            'price' => 89.99,
            'is_available' => true,
            'url' => 'https://example.com/variant.jpg',
        ]);

        $this->assertNotNull($productMerchant->variant);
        $this->assertEquals($variant->id, $productMerchant->variant->id);
    }

    public function test_merchant_belongs_to_merchant()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 99.99,
            'is_available' => true,
            'url' => 'https://example.com/product.jpg',
        ]);

        $this->assertNotNull($productMerchant->merchant);
        $this->assertEquals($merchant->id, $productMerchant->merchant->id);
    }

    public function test_effective_price_returns_override_price_when_set()
    {
        $product = $this->createProduct(['price' => 100]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 90,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $merchant->id,
            'price' => 85,
            'override_price' => true,
            'is_available' => true,
            'url' => 'https://example.com/variant.jpg',
        ]);

        $this->assertEquals(85, $productMerchant->effective_price);
    }

    public function test_effective_price_returns_variant_price_when_no_override()
    {
        $product = $this->createProduct(['price' => 100]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 90,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $merchant->id,
            'price' => 85,
            'override_price' => false,
            'is_available' => true,
            'url' => 'https://example.com/variant.jpg',
        ]);

        $this->assertEquals(90, $productMerchant->effective_price);
    }

    public function test_effective_sale_price_returns_override_sale_price_when_set()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 90,
            'sale_price' => 80,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $merchant->id,
            'price' => 85,
            'sale_price' => 75,
            'override_sale_price' => true,
            'is_available' => true,
            'url' => 'https://example.com/variant.jpg',
        ]);

        $this->assertEquals(75, $productMerchant->effective_sale_price);
    }

    public function test_effective_sale_price_returns_variant_sale_price_when_no_override()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 90,
            'sale_price' => 80,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $merchant->id,
            'price' => 85,
            'sale_price' => 75,
            'override_sale_price' => false,
            'is_available' => true,
            'url' => 'https://example.com/variant.jpg',
        ]);

        $this->assertEquals(80, $productMerchant->effective_sale_price);
    }

    public function test_effective_sku_returns_variant_sku_when_set()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 90,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $merchant->id,
            'price' => 85,
            'variant_sku' => 'MERCH-VAR-001',
            'is_available' => true,
            'url' => 'https://example.com/variant.jpg',
        ]);

        $this->assertEquals('MERCH-VAR-001', $productMerchant->effective_sku);
    }

    public function test_discount_percentage_calculates_correctly()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 100,
            'sale_price' => 80,
            'override_price' => true,
            'override_sale_price' => true,
            'is_available' => true,
            'url' => 'https://example.com/product.jpg',
        ]);

        $this->assertEquals(20, $productMerchant->discount_percentage);
    }

    public function test_discount_percentage_returns_zero_when_no_discount()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 100,
            'sale_price' => null,
            'override_price' => true,
            'is_available' => true,
            'url' => 'https://example.com/product.jpg',
        ]);

        $this->assertEquals(0, $productMerchant->discount_percentage);
    }

    public function test_has_discount_returns_true_when_discount_exists()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 100,
            'sale_price' => 80,
            'override_price' => true,
            'override_sale_price' => true,
            'is_available' => true,
            'url' => 'https://example.com/product.jpg',
        ]);

        $this->assertTrue($productMerchant->has_discount);
    }

    public function test_has_discount_returns_false_when_no_discount()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 100,
            'override_price' => true,
            'is_available' => true,
            'url' => 'https://example.com/product.jpg',
        ]);

        $this->assertFalse($productMerchant->has_discount);
    }

    public function test_final_price_returns_sale_price_when_available()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 100,
            'sale_price' => 80,
            'override_price' => true,
            'override_sale_price' => true,
            'is_available' => true,
            'url' => 'https://example.com/product.jpg',
        ]);

        $this->assertEquals(80, $productMerchant->final_price);
    }
}