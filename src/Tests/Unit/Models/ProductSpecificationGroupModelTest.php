<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductSpecification;
use App\Models\ProductSpecificationGroup;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductSpecificationGroupModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateSpecificationGroup(): void
    {
        $group = ProductSpecificationGroup::create([
            'name' => 'Technical Specs',
            'slug' => 'technical-specs',
            'sort_order' => 0,
            'is_active' => true
        ]);

        $this->assertNotNull($group->id);
        $this->assertEquals('Technical Specs', $group->name);
        $this->assertEquals('technical-specs', $group->slug);
        $this->assertTrue($group->is_active);
    }

    public function testGenerateSlugFromName(): void
    {
        $slug = ProductSpecificationGroup::generateSlug('Technical Specifications');
        $this->assertEquals('technical-specifications', $slug);
    }

    public function testGenerateUniqueSlugWhenDuplicate(): void
    {
        ProductSpecificationGroup::create([
            'name' => 'Dimensions',
            'slug' => 'dimensions',
        ]);

        $slug = ProductSpecificationGroup::generateSlug('Dimensions');
        $this->assertEquals('dimensions-1', $slug);
    }

    public function testGetOrCreateFindsExistingGroup(): void
    {
        $group1 = ProductSpecificationGroup::create([
            'name' => 'Weight',
            'slug' => 'weight',
        ]);

        // Try with different case
        $group2 = ProductSpecificationGroup::getOrCreate('WEIGHT');

        $this->assertEquals($group1->id, $group2->id);
    }

    public function testGetOrCreateCreatesNewGroup(): void
    {
        $count = ProductSpecificationGroup::count();

        $group = ProductSpecificationGroup::getOrCreate('Brand New Category');

        $this->assertEquals($count + 1, ProductSpecificationGroup::count());
        $this->assertEquals('Brand new category', $group->name);
    }

    public function testSpecificationsRelationship(): void
    {
        $product = $this->createProduct();
        $group = ProductSpecificationGroup::create([
            'name' => 'Dimensions',
            'slug' => 'dimensions'
        ]);

        $spec = ProductSpecification::create([
            'product_id' => $product->id,
            'specification_group_id' => $group->id,
            'key' => 'Width',
            'value' => '10cm',
            'category' => 'size'
        ]);

        $this->assertCount(1, $group->specifications);
        $this->assertEquals($spec->id, $group->specifications->first()->id);
    }

    public function testActiveSpecificationsOnlyIncludesActiveProducts(): void
    {
        $activeProduct = $this->createProduct(['is_active' => true]);
        $inactiveProduct = $this->createProduct(['is_active' => false]);
        $group = ProductSpecificationGroup::create([
            'name' => 'Color',
            'slug' => 'color'
        ]);


        ProductSpecification::create([
            'product_id' => $activeProduct->id,
            'specification_group_id' => $group->id,
            'key' => 'Color',
            'value' => 'Red',
            'category' => 'color'
        ]);

        ProductSpecification::create([
            'product_id' => $inactiveProduct->id,
            'specification_group_id' => $group->id,
            'key' => 'Color',
            'value' => 'Blue',
            'category' => 'color'
        ]);
        $activeSpecs = $group->activeSpecifications(true)->get();
        $this->assertCount(1, $activeSpecs);
        $this->assertEquals('Red', $activeSpecs->first()->value);
    }
}