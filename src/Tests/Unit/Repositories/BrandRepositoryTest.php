<?php

namespace App\Tests\Unit\Repositories;

use App\Repositories\Cms\BrandRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BrandRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private BrandRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BrandRepository();
    }

    public function test_it_can_find_brand_by_slug(): void
    {
        $brand = $this->createBrand(['slug' => 'apple', 'name' => 'Apple']);;

        $found = $this->repository->findBySlug('apple');

        $this->assertNotNull($found);
        $this->assertEquals($brand->id, $found->id);
        $this->assertEquals('apple', $found->slug);
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        $found = $this->repository->findBySlug('non-existent-slug');

        $this->assertNull($found);
    }

    public function test_get_active_brands_returns_only_active(): void
    {
       $this->createBrand(['is_active' => true]);
        $this->createBrand(['is_active' => true]);
        $this->createBrand(['is_active' => false]);

        $brands = $this->repository->getActiveBrands();

        $this->assertCount(2, $brands);
        foreach ($brands as $brand) {
            $this->assertEquals(true, $brand->is_active);
        }
    }

    public function test_active_brands_ordered_by_name(): void
    {
        $this->createBrand(['name' => 'Apple', 'slug' => 'apple', 'status' => 'active']);
        $this->createBrand(['name' => 'Zebra', 'slug' => 'zebra', 'status' => 'active']);
        $this->createBrand(['name' => 'Microsoft', 'slug' => 'microsoft', 'status' => 'active']);

        $brands = $this->repository->getActiveBrands();
        $names = $brands->pluck('name')->toArray();

        $this->assertEquals(['Apple', 'Microsoft', 'Zebra'], $names);
    }

    public function test_get_alternatives_excludes_specified_brand(): void
    {
        $brand1 = $this->createBrand(['name' => 'Brand 1']);
        $brand2 = $this->createBrand(['name' => 'Brand 2']);
        $brand3 = $this->createBrand(['name' => 'Brand 3']);

        $alternatives = $this->repository->getAlternatives($brand1->id);

        $this->assertCount(2, $alternatives);
        $this->assertCollectionDoesNotContain($alternatives, ['id' => $brand1->id]);
        $this->assertCollectionContains($alternatives, ['id' => $brand2->id]);
        $this->assertCollectionContains($alternatives, ['id' => $brand3->id]);
    }

    public function test_get_alternatives_only_returns_active_brands(): void
    {
        $brand1 = $this->createBrand(['name' => 'Brand 1', 'is_active' => true]);
        $brand2 = $this->createBrand(['name' => 'Brand 2', 'is_active' => true]);
        $inactive = $this->createBrand(['name' => 'Inactive', 'is_active' => false]);

        $alternatives = $this->repository->getAlternatives($brand1->id);

        $this->assertCount(1, $alternatives);
        $this->assertCollectionDoesNotContain($alternatives, ['id' => $inactive->id]);
    }

    public function test_get_alternatives_ordered_by_name(): void
    {
        $brand1 = $this->createBrand(['name' => 'Apple', 'is_active' => true]);
        $brand2 = $this->createBrand(['name' => 'Microsoft', 'is_active' => true]);
        $brand3 = $this->createBrand(['name' => 'Zebra', 'is_active' => true]);

        $alternatives = $this->repository->getAlternatives($brand1->id);
        $names = $alternatives->pluck('name')->toArray();

        $this->assertEquals(['Microsoft', 'Zebra'], $names);
    }

    public function test_get_products_by_brand_id(): void
    {
        $brand = $this->createBrand();
        $product1 = $this->createProduct(['brand_id' => $brand->id, 'name' => 'iPhone']);
        $product2 = $this->createProduct(['brand_id' => $brand->id, 'name' => 'MacBook']);
        $otherProduct = $this->createProduct(['name' => 'Samsung Phone']);

        $products = $this->repository->getProductsByBrandId($brand->id);

        $this->assertCount(2, $products);
        $this->assertCollectionContains($products, ['name' => 'iPhone']);
        $this->assertCollectionContains($products, ['name' => 'MacBook']);
        $this->assertCollectionDoesNotContain($products, ['name' => 'Samsung Phone']);
    }

    public function test_search_returns_paginated_result(): void
    {
        for ($i = 1; $i <= 15; $i++) {
           $this->createBrand();
        }

        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $criteria->setPage(1);

        $result = $this->repository->search($criteria);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertCount(10, $result->getData());
    }

    public function test_search_only_returns_active_brands(): void
    {
        $this->createBrand(['name' => 'Active Brand', 'slug' => 'active', 'is_active' => true]);
        $this->createBrand(['name' => 'Inactive Brand', 'slug' => 'inactive', 'is_active' => false]);

        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);

        $result = $this->repository->search($criteria);

        $this->assertCount(1, $result->getData());
        $this->assertEquals('Active Brand', $result->getData()[0]['name']);
    }
}