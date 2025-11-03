<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductImageModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_image_belongs_to_product()
    {
        $product = $this->createProduct();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'https://example.com/image.jpg',
            'is_primary' => true
        ]);

        $this->assertNotNull($image->product);
        $this->assertEquals($product->id, $image->product->id);
    }

    public function test_image_belongs_to_variant()
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

        $this->assertNotNull($image->variant);
        $this->assertEquals($variant->id, $image->variant->id);
    }

    public function test_is_primary_cast_to_boolean()
    {
        $product = $this->createProduct();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'https://example.com/image.jpg',
            'is_primary' => 1
        ]);

        $this->assertIsBool($image->is_primary);
        $this->assertTrue($image->is_primary);
    }

    public function test_sort_order_cast_to_integer()
    {
        $product = $this->createProduct();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'https://example.com/image.jpg',
            'sort_order' => '5'
        ]);

        $this->assertIsInt($image->sort_order);
        $this->assertEquals(5, $image->sort_order);
    }
}