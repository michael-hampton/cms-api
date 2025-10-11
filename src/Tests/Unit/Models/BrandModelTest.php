<?php

namespace App\Tests\Unit\Models;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class BrandModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
    }

    public function testCreateBrand()
    {
        $brand = Brand::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'description' => 'Leading technology brand',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertEquals('TechCorp', $brand->name);
        $this->assertEquals('techcorp', $brand->slug);
    }

    public function testBrandHasManyProducts()
    {
        $brand = Brand::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Product 1',
            'price' => 100,
            'sale_price' => 90,
            'category_id' => 1,
            'brand_id' => $brand->id,
            'site_id' => 1,
        ]);

        Product::create([
            'name' => 'Product 2',
            'price' => 200,
            'sale_price' => 180,
            'category_id' => 1,
            'brand_id' => $brand->id,
            'site_id' => 1,
        ]);

        $products = $brand->products();

        $this->assertEquals(2, $products->count());
    }

    public function testScopeActive()
    {
        Brand::create(['name' => 'Active Brand', 'slug' => 'active', 'is_active' => true]);
        Brand::create(['name' => 'Inactive Brand', 'slug' => 'inactive', 'is_active' => false]);

        $active = Brand::active()->get();
        $this->assertCount(1, $active);
        $this->assertEquals('Active Brand', $active->first()->name);
    }

    public function testScopeBySlug()
    {
        Brand::create(['name' => 'Brand 1', 'slug' => 'brand-1', 'is_active' => true]);
        Brand::create(['name' => 'Brand 2', 'slug' => 'brand-2', 'is_active' => true]);

        $brand = Brand::bySlug('brand-1')->first();
        $this->assertEquals('Brand 1', $brand->name);
    }

    public function testIsActiveCast()
    {
        $brand = Brand::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'is_active' => 1,
        ]);

        $this->assertIsBool($brand->is_active);
        $this->assertTrue($brand->is_active);
    }

    public function testLogoAttribute()
    {
        $brand = Brand::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'logo' => '/uploads/logos/techcorp.png',
            'is_active' => true,
        ]);

        $this->assertEquals('/uploads/logos/techcorp.png', $brand->logo);
    }

    public function testWebsiteAttribute()
    {
        $brand = Brand::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'website' => 'https://techcorp.com',
            'is_active' => true,
        ]);

        $this->assertEquals('https://techcorp.com', $brand->website);
    }

    public function testDescriptionAttribute()
    {
        $brand = Brand::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'description' => 'A leading technology company specializing in innovative solutions',
            'is_active' => true,
        ]);

        $this->assertEquals('A leading technology company specializing in innovative solutions', $brand->description);
    }

    public function testUpdateBrand()
    {
        $brand = Brand::create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'is_active' => true,
        ]);

        $brand->update([
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $fresh = Brand::find($brand->id);
        $this->assertEquals('Updated Name', $fresh->name);
        $this->assertEquals('Updated description', $fresh->description);
    }

    public function testDeleteBrand()
    {
        $brand = Brand::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'is_active' => true,
        ]);

        $id = $brand->id;
        $brand->delete();

        $deleted = Brand::find($id);
        $this->assertNull($deleted);
    }

    public function testTimestamps()
    {
        $brand = Brand::create([
            'name' => 'TechCorp',
            'slug' => 'techcorp',
            'is_active' => true,
        ]);

        $this->assertNotNull($brand->created_at);
        $this->assertNotNull($brand->updated_at);
    }

    public function testInactiveBrand()
    {
        $brand = Brand::create([
            'name' => 'Inactive Brand',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $this->assertFalse($brand->is_active);
    }
}