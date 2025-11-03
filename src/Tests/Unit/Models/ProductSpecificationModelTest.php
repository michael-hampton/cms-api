<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductSpecification;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductSpecificationModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_specification_belongs_to_product()
    {
        $product = $this->createProduct();
        $spec = ProductSpecification::create([
            'product_id' => $product->id,
            'category' => 'General',
            'key' => 'Weight',
            'value' => '1kg'
        ]);

        $this->assertNotNull($spec->product);
        $this->assertEquals($product->id, $spec->product->id);
    }

    public function test_sort_order_cast_to_integer()
    {
        $product = $this->createProduct();
        $spec = ProductSpecification::create([
            'product_id' => $product->id,
            'category' => 'General',
            'key' => 'Weight',
            'value' => '1kg',
            'sort_order' => '10'
        ]);

        $this->assertIsInt($spec->sort_order);
        $this->assertEquals(10, $spec->sort_order);
    }

    public function test_can_create_multiple_specifications_for_product()
    {
        $product = $this->createProduct();

        ProductSpecification::create([
            'product_id' => $product->id,
            'category' => 'General',
            'key' => 'Weight',
            'value' => '1kg'
        ]);

        ProductSpecification::create([
            'product_id' => $product->id,
            'category' => 'Dimensions',
            'key' => 'Height',
            'value' => '30cm'
        ]);

        $this->assertCount(2, $product->specifications);
    }
}