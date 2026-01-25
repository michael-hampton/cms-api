<?php

namespace App\Tests\Unit\Repositories;

namespace App\Tests\Unit\Repositories\Product;

use App\Models\ProductSpecification;
use App\Models\ProductSpecificationGroup;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductSpecificationGroupRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ProductSpecificationGroupRepository $repository;

    public function testGetAllWithCountsReturnsActiveGroupsOnly(): void
    {
        $activeGroup = ProductSpecificationGroup::create([
            'name' => 'Active Group',
            'slug' => 'active-group',
            'is_active' => true
        ]);

        $inactiveGroup = ProductSpecificationGroup::create([
            'name' => 'Inactive Group',
            'slug' => 'inactive-group',
            'is_active' => false
        ]);

        $product = $this->createProduct(['site_id' => $this->siteId]);

        ProductSpecification::create([
            'product_id' => $product->id,
            'specification_group_id' => $activeGroup->id,
            'key' => 'Test',
            'value' => 'Value',
            'category' => 'color'
        ]);

        $groups = $this->repository->getAllWithCounts($this->siteId);

        $this->assertCount(1, $groups);
        $this->assertEquals('Active Group', $groups->first()['name']);
    }

    public function testGetAllWithCountsIncludesProductCounts(): void
    {
        $group = ProductSpecificationGroup::create([
            'name' => 'Color',
            'slug' => 'color',
            'is_active' => true
        ]);

        // Create products with proper site_id and is_active
        $product1 = $this->createProduct([
            'site_id' => $this->siteId,
            'is_active' => true,
            'name' => 'Product 1'
        ]);
        $product2 = $this->createProduct([
            'site_id' => $this->siteId,
            'is_active' => true,
            'name' => 'Product 2'
        ]);

        // Create specifications with proper relationships
        $spec1 = ProductSpecification::create([
            'product_id' => $product1->id,
            'specification_group_id' => $group->id,
            'key' => 'Color',
            'value' => 'Red',
            'category' => 'color'
        ]);

        $spec2 = ProductSpecification::create([
            'product_id' => $product2->id,
            'specification_group_id' => $group->id,
            'key' => 'Color',
            'value' => 'Blue',
            'category' => 'color'
        ]);

        $groups = $this->repository->getAllWithCounts($this->siteId);

        $this->assertCount(1, $groups);

        $this->assertEquals(2, $groups->first()['product_count']);
    }

    public function testGetAllWithCountsIncludesSpecificationValues(): void
    {
        $group = ProductSpecificationGroup::create([
            'name' => 'Size',
            'slug' => 'size',
            'is_active' => true
        ]);

        $product = $this->createProduct(['site_id' => $this->siteId, 'is_active' => true]);

        ProductSpecification::create([
            'product_id' => $product->id,
            'specification_group_id' => $group->id,
            'key' => 'Width',
            'value' => '10cm',
            'category' => 'size'
        ]);

        ProductSpecification::create([
            'product_id' => $product->id,
            'specification_group_id' => $group->id,
            'key' => 'Height',
            'value' => '20cm',
            'category' => 'size'
        ]);

        $groups = $this->repository->getAllWithCounts($this->siteId);
        $specs = $groups->first()['specifications'];

        $this->assertCount(2, $specs);
        $this->assertEquals('Width', $specs[0]['key']);
        $this->assertContains('10cm', $specs[0]['values']);
    }

    public function testFindOrCreateByNameCreatesNewGroup(): void
    {
        $count = ProductSpecificationGroup::count();

        $group = $this->repository->findOrCreateByName('New Category');

        $this->assertEquals($count + 1, ProductSpecificationGroup::count());
        $this->assertEquals('New category', $group->name);
    }

    public function testFindOrCreateByNameFindsExisting(): void
    {
        $existing = ProductSpecificationGroup::create([
            'name' => 'Existing',
            'slug' => 'existing'
        ]);

        $group = $this->repository->findOrCreateByName('EXISTING');

        $this->assertEquals($existing->id, $group->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductSpecificationGroupRepository();
    }
}