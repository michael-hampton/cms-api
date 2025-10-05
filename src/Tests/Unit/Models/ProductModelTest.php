<?php

namespace App\Tests\Unit\Models;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ProductModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->createBrand();
        $this->createCategory();
    }

    public function testCreateProduct()
    {
        $product = Product::create([
            'name' => 'Laptop',
            'description' => 'High-performance laptop',
            'price' => 999.99,
            'sale_price' => 899.99,
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Laptop', $product->name);
        $this->assertEquals(999.99, $product->price);
    }

    public function testGetDiscountPercentageAttribute()
    {
        $product = Product::create([
            'name' => 'Laptop',
            'description' => 'High-performance laptop',
            'price' => 1000.00,
            'sale_price' => 800.00,
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $this->assertEquals(20, $product->getDiscountPercentageAttribute());
    }

    public function testGetDiscountPercentageWithZeroPrice()
    {
        $product = Product::create([
            'name' => 'Free Item',
            'description' => 'Free product',
            'price' => 0,
            'sale_price' => 0,
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $this->assertEquals(0, $product->getDiscountPercentageAttribute());
    }

    public function testGetDiscountPercentageRounding()
    {
        $product = Product::create([
            'name' => 'Item',
            'description' => 'Test item',
            'price' => 99.99,
            'sale_price' => 66.66,
        ]);

        // (99.99 - 66.66) / 99.99 * 100 = 33.336... rounds to 33
        $this->assertEquals(33, $product->getDiscountPercentageAttribute());
    }

    public function testScopeByCategory()
    {
        $this->createCategory(2);
        $this->createBrand(2);

        Product::create(['name' => 'Product1', 'price' => 100, 'sale_price' => 90, 'category_id' => 1, 'brand_id' => 1]);
        Product::create(['name' => 'Product2', 'price' => 200, 'sale_price' => 180, 'category_id' => 2, 'brand_id' => 1]);
        Product::create(['name' => 'Product3', 'price' => 150, 'sale_price' => 140, 'category_id' => 1, 'brand_id' => 2]);

        $products = Product::byCategory('1')->get();
        $this->assertCount(2, $products);
    }

    public function testScopeByBrand()
    {
        $this->createBrand(2);

        Product::create(['name' => 'Product1', 'price' => 100, 'sale_price' => 90, 'category_id' => 1, 'brand_id' => 1]);
        Product::create(['name' => 'Product2', 'price' => 200, 'sale_price' => 180, 'category_id' => 1, 'brand_id' => 2]);
        Product::create(['name' => 'Product3', 'price' => 150, 'sale_price' => 140, 'category_id' => 1, 'brand_id' => 1]);

        $products = Product::byBrand(1)->get();

        $this->assertCount(2, $products);
    }

    public function testScopeOnSale()
    {
        Product::create(['name' => 'OnSale', 'price' => 100, 'sale_price' => 80, 'category_id' => 1, 'brand_id' => 1]);
        Product::create(['name' => 'FullPrice', 'price' => 100, 'sale_price' => 90, 'category_id' => 1, 'brand_id' => 1]);
        Product::create(['name' => 'OnSale2', 'price' => 200, 'sale_price' => 150, 'category_id' => 1, 'brand_id' => 1]);

        $products = Product::onSale()->toSql();

        $this->assertCount(2, $products);
    }

    public function testScopeSearch()
    {
        $this->createCategory(3);

        Product::create([
            'name' => 'Gaming Laptop',
            'description' => 'High-performance gaming laptop',
            'price' => 1500,
            'sale_price' => 1400,
            'category_id' => 1,
            'brand_id' => 1
        ]);

        Product::create([
            'name' => 'Office Mouse',
            'description' => 'Wireless mouse',
            'price' => 50,
            'sale_price' => 45,
            'category_id' => 2,
            'brand_id' => 1
        ]);

        Product::create([
            'name' => 'Mechanical Keyboard',
            'description' => 'RGB gaming keyboard',
            'price' => 120,
            'sale_price' => 100,
            'category_id' => 3,
            'brand_id' => 1
        ]);

        // Search by name
        $results = Product::search('Laptop')->get();
        $this->assertEquals(1, $results->count());
        $this->assertEquals('Gaming Laptop', $results->first()->name);

        // Search by description
        $results = Product::search('wireless')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Office Mouse', $results->first()->name);

    }

    public function testPriceCast()
    {
        $product = Product::create([
            'name' => 'Item',
            'price' => '99.99',
            'sale_price' => '89.99',
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $this->assertIsFloat($product->price);
        $this->assertIsFloat($product->sale_price);
    }

    public function testMetaAttributes()
    {
        $product = Product::create([
            'name' => 'Laptop',
            'description' => 'Gaming laptop',
            'price' => 1500,
            'sale_price' => 1400,
            'category_id' => 1,
            'brand_id' => 1,
            'meta_title' => 'Best Gaming Laptop 2024',
            'meta_description' => 'High-performance gaming laptop with RTX graphics',
            'meta_keywords' => 'gaming, laptop, rtx, high-performance',
        ]);

        $this->assertEquals('Best Gaming Laptop 2024', $product->meta_title);
        $this->assertEquals('High-performance gaming laptop with RTX graphics', $product->meta_description);
        $this->assertEquals('gaming, laptop, rtx, high-performance', $product->meta_keywords);
    }

    public function testImageAttribute()
    {
        $product = Product::create([
            'name' => 'Laptop',
            'price' => 1500,
            'sale_price' => 1400,
            'category_id' => 1,
            'brand_id' => 1,
            'image' => '/uploads/products/laptop.jpg',
        ]);

        $this->assertEquals('/uploads/products/laptop.jpg', $product->image);
    }

    public function testUpdateProduct()
    {
        $product = Product::create([
            'name' => 'Original Name',
            'description' => 'Original description',
            'price' => 100,
            'sale_price' => 90,
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $product->update([
            'name' => 'Updated Name',
            'price' => 120,
        ]);

        $fresh = Product::find($product->id);
        $this->assertEquals('Updated Name', $fresh->name);
        $this->assertEquals(120.0, $fresh->price);
    }

    public function testDeleteProduct()
    {
        $product = Product::create([
            'name' => 'To Delete',
            'price' => 100,
            'sale_price' => 90,
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $id = $product->id;
        $product->delete();

        $deleted = Product::find($id);
        $this->assertNull($deleted);
    }

    public function testSoftDelete()
    {
        $product = Product::create([
            'name' => 'Soft Delete Test',
            'price' => 100,
            'sale_price' => 90,
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $id = $product->id;
        $product->delete();

        // If soft deletes are enabled, should still exist with deleted_at
        $withTrashed = Product::withTrashed()->find($id);

        if ($product->usesSoftDeletes()) {
            $this->assertNotNull($withTrashed);
            $this->assertNotNull($withTrashed->deleted_at);
        } else {
            $this->assertNull($withTrashed);
        }
    }

    public function testTimestamps()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'sale_price' => 90,
            'category_id' => 1,
            'brand_id' => 1,
        ]);

        $this->assertNotNull($product->created_at);
        $this->assertNotNull($product->updated_at);
    }

    private function createCategory($times = 1) {
        for ($i = 0; $i < $times; $i++) {
            Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        }
    }

    private function createBrand($times = 1)
    {
        for ($i = 0; $i < $times; $i++) {
            Brand::create(['name' => 1, 'slug' => 'techcorp']);
        }

    }
}