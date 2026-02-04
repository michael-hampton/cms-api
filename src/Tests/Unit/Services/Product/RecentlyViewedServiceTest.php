<?php
// src/Tests/Unit/Services/Product/RecentlyViewedServiceTest.php

namespace App\Tests\Unit\Services\Product;

use App\Framework\Support\Collection;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\RecentlyViewedService;
use App\Services\Shared\SessionStore;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class RecentlyViewedServiceTest extends FunctionalTestCase
{
    private ProductRepository $productRepository;
    private SessionStore $sessionStore;
    private RecentlyViewedService $service;

    public function testAddProductAddsToBeginning()
    {
        $product = new Product(['id' => 5, 'name' => 'Product 5']);

        $this->sessionStore->shouldReceive('get')
            ->with('recently_viewed', [])
            ->andReturn([1, 2, 3]);

        $this->sessionStore->shouldReceive('put')
            ->once()
            ->with('recently_viewed', [5, 1, 2, 3]);

        $this->service->addProduct($product);
    }

    public function testAddProductRemovesDuplicates()
    {
        $product = new Product(['id' => 2, 'name' => 'Product 2']);

        $this->sessionStore->shouldReceive('get')
            ->andReturn([1, 2, 3, 4]);

        $this->sessionStore->shouldReceive('put')
            ->once()
            ->with('recently_viewed', [2, 1, 3, 4]);

        $this->service->addProduct($product);
    }

    public function testGetProductsReturnsEmpty()
    {
        $this->sessionStore->shouldReceive('get')
            ->with('recently_viewed', [])
            ->andReturn([]);

        $result = $this->service->getProducts();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function testGetProductsReturnsLimited()
    {
        $products = collect([
            new Product(['id' => 1]),
            new Product(['id' => 2]),
        ]);

        $this->sessionStore->shouldReceive('get')
            ->andReturn([1, 2, 3, 4, 5]);

        $this->productRepository->shouldReceive('getActiveProducts')
            ->with([1, 2], 2)
            ->andReturn($products);

        $result = $this->service->getProducts(2);

        $this->assertCount(2, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->sessionStore = Mockery::mock(SessionStore::class);
        $this->service = new RecentlyViewedService($this->productRepository, $this->sessionStore);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}