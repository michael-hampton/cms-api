<?php

namespace App\Database\Seeders;

use App\Framework\Database\Database;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationGroup;

class ProductComparisonSeeder
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function run(): void
    {
        // Create category
        $category = Category::create([
            'name' => 'Beds',
            'slug' => 'beds',
            'site_id' => 52
        ]);

        // Create brand
        $brand = Brand::create([
            'name' => 'Sleep Co',
            'slug' => 'sleep-co',
            'site_id' => 52
        ]);

        // Create spec groups
        $dimensionsGroup = ProductSpecificationGroup::create([
            'name' => 'Dimensions',
            'slug' => 'dimensions',
            'is_active' => true
        ]);

        $materialsGroup = ProductSpecificationGroup::create([
            'name' => 'Materials',
            'slug' => 'materials',
            'is_active' => true
        ]);

        $generalGroup = ProductSpecificationGroup::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true
        ]);

        // Product 1: Double Bed
        $product1 = Product::create([
            'name' => 'Classic Double Bed',
            'slug' => 'classic-double-bed',
            'description' => 'Comfortable double bed',
            'price' => 599.99,
            'sale_price' => 499.99,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'site_id' => 52,
            'is_active' => true
        ]);

        // Specs for product 1
        ProductSpecification::create([
            'product_id' => $product1->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Width',
            'value' => '140cm',
            'sort_order' => 1,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product1->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Length',
            'value' => '200cm',
            'sort_order' => 2,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product1->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Height',
            'value' => '100cm',
            'sort_order' => 3,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product1->id,
            'specification_group_id' => $materialsGroup->id,
            'key' => 'Frame Material',
            'value' => 'Solid Oak',
            'sort_order' => 1,
            'category' => $materialsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product1->id,
            'specification_group_id' => $generalGroup->id,
            'key' => 'Weight',
            'value' => '45kg',
            'sort_order' => 1,
            'category' => $generalGroup->name
        ]);

        // Product 2: Double Bed (different specs)
        $product2 = Product::create([
            'name' => 'Modern Double Bed',
            'slug' => 'modern-double-bed',
            'description' => 'Stylish modern double bed',
            'price' => 699.99,
            'sale_price' => 599.99,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'site_id' => 52,
            'is_active' => true
        ]);

        ProductSpecification::create([
            'product_id' => $product2->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Width',
            'value' => '140cm',
            'sort_order' => 1,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product2->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Length',
            'value' => '200cm',
            'sort_order' => 2,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product2->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Height',
            'value' => '110cm', // DIFFERENT
            'sort_order' => 3,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product2->id,
            'specification_group_id' => $materialsGroup->id,
            'key' => 'Frame Material',
            'value' => 'Metal', // DIFFERENT
            'sort_order' => 1,
            'category' => $materialsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product2->id,
            'specification_group_id' => $generalGroup->id,
            'key' => 'Weight',
            'value' => '38kg', // DIFFERENT
            'sort_order' => 1,
            'category' => $generalGroup->name
        ]);

        // Product 3 - Missing a spec (to test hiding logic)
        $product3 = Product::create([
            'name' => 'Budget Double Bed',
            'slug' => 'budget-double-bed',
            'description' => 'Affordable double bed',
            'price' => 399.99,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'site_id' => 52,
            'is_active' => true
        ]);

        ProductSpecification::create([
            'product_id' => $product3->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Width',
            'value' => '140cm',
            'sort_order' => 1,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product3->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Length',
            'value' => '200cm',
            'sort_order' => 2,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product3->id,
            'specification_group_id' => $dimensionsGroup->id,
            'key' => 'Height',
            'value' => '95cm', // DIFFERENT
            'sort_order' => 3,
            'category' => $dimensionsGroup->name
        ]);

        ProductSpecification::create([
            'product_id' => $product3->id,
            'specification_group_id' => $materialsGroup->id,
            'key' => 'Frame Material',
            'value' => 'Pine', // DIFFERENT
            'sort_order' => 1,
            'category' => $materialsGroup->name
        ]);

        // NOTE: Weight is MISSING on product 3 - this tests the hiding logic

        echo "Created 3 comparable bed products with shared and differing specs\n";
    }
}