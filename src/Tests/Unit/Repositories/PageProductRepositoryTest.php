<?php

namespace App\Tests\Unit\Repositories;

use App\Models\PageProduct;
use App\Repositories\PageProductRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageProductRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageProductRepository();
    }

    public function test_sync_products_removes_products_not_in_new_list(): void
    {
        // Arrange
        $page = $this->createPage();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        // Create initial associations
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product1->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product2->id,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        // Act - sync with only product2 and product3
        $this->repository->syncProducts($page->id, [$product2->id, $product3->id], $this->siteId);

        // Assert
        $associations = PageProduct::where('page_id', $page->id)->get();
        $this->assertCount(2, $associations);
        $this->assertCollectionDoesNotContain($associations, ['product_id' => $product1->id]);
        $this->assertCollectionContains($associations, ['product_id' => $product2->id]);
        $this->assertCollectionContains($associations, ['product_id' => $product3->id]);
    }

    public function test_sync_products_updates_sort_order(): void
    {
        // Arrange
        $page = $this->createPage();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        // Act
        $this->repository->syncProducts($page->id, [$product1->id, $product2->id], $this->siteId);

        // Assert
        $association1 = PageProduct::where('page_id', $page->id)
            ->where('product_id', $product1->id)
            ->first();
        $association2 = PageProduct::where('page_id', $page->id)
            ->where('product_id', $product2->id)
            ->first();

        $this->assertEquals(0, $association1->sort_order);
        $this->assertEquals(1, $association2->sort_order);
    }

    public function test_sync_products_creates_new_associations(): void
    {
        // Arrange
        $page = $this->createPage();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        // Act
        $this->repository->syncProducts($page->id, [$product1->id, $product2->id], $this->siteId);

        // Assert
        $associations = PageProduct::where('page_id', $page->id)->get();
        $this->assertCount(2, $associations);
        $this->assertCollectionContains($associations, ['product_id' => $product1->id]);
        $this->assertCollectionContains($associations, ['product_id' => $product2->id]);
    }

    public function test_sync_products_updates_existing_associations(): void
    {
        // Arrange
        $page = $this->createPage();
        $product = $this->createProduct();

        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product->id,
            'sort_order' => 5,
            'site_id' => $this->siteId
        ]);

        // Act - sync with new sort order
        $this->repository->syncProducts($page->id, [$product->id], $this->siteId);

        // Assert
        $association = PageProduct::where('page_id', $page->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(0, $association->sort_order);
    }

    public function test_sync_products_with_empty_array_removes_all(): void
    {
        // Arrange
        $page = $this->createPage();
        $product = $this->createProduct();

        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        // Act
        $this->repository->syncProducts($page->id, [], $this->siteId);

        // Assert
        $associations = PageProduct::where('page_id', $page->id)->get();
        $this->assertCount(0, $associations);
    }

    public function test_get_products_for_page_returns_sorted_products(): void
    {
        // Arrange
        $page = $this->createPage();
        $product1 = $this->createProduct(['name' => 'Product 1']);
        $product2 = $this->createProduct(['name' => 'Product 2']);
        $product3 = $this->createProduct(['name' => 'Product 3']);

        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product3->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product1->id,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product2->id,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        // Act
        $products = $this->repository->getProductsForPage($page->id);
        $products = $products->toArray();

        // Assert
        $this->assertCount(3, $products);
        $this->assertEquals($product3->id, $products[0]['product_id']);
        $this->assertEquals($product1->id, $products[1]['product_id']);
        $this->assertEquals($product2->id, $products[2]['product_id']);
    }

    public function test_get_products_for_page_returns_empty_for_page_with_no_products(): void
    {
        // Arrange
        $page = $this->createPage();

        // Act
        $products = $this->repository->getProductsForPage($page->id);

        // Assert
        $this->assertCount(0, $products);
    }
}