<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductVariant;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductVariantModelTest extends FunctionalTestCase
{
    use CreatesTestData;
    public function test_variant_belongs_to_product()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);
        $this->assertNotNull($variant->product);
        $this->assertEquals($product->id, $variant->product->id);
    }

    public function test_variant_has_images_relationship()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $image = ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'url' => 'https://example.com/variant.jpg',
            'is_primary' => true
        ]);

        $this->assertCount(1, $variant->images);
        $this->assertEquals($image->id, $variant->images->first()->id);
    }

    public function test_variant_has_merchants_relationship()
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
            'url' => 'https://example.com/variant.jpg',
            'price' => 89.99,
            'is_available' => true
        ]);

        $this->assertCount(1, $variant->merchants);
        $this->assertEquals($productMerchant->id, $variant->merchants->first()->id);
    }

    public function test_final_price_returns_variant_price()
    {
        $product = $this->createProduct(['price' => 100]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(99.99, $variant->final_price);
    }

    public function test_discount_percentage_calculates_correctly()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'sale_price' => 80,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(20, $variant->discount_percentage);
    }

    public function test_discount_percentage_returns_zero_when_no_sale_price()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'sale_price' => null,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(0, $variant->discount_percentage);
    }

    public function test_discount_percentage_returns_zero_when_sale_price_higher()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'sale_price' => 120,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(0, $variant->discount_percentage);
    }

    public function test_attributes_cast_to_array()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'attributes' => ['color' => 'red', 'size' => 'large'],
            'is_active' => true
        ]);

        $this->assertIsArray($variant->attributes);
        $this->assertEquals('red', $variant->attributes['color']);
        $this->assertEquals('large', $variant->attributes['size']);
    }

}