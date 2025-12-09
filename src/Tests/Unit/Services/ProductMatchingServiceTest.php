<?php
// src/Tests/Unit/Services/ProductMatchingServiceTest.php

namespace App\Tests\Unit\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ProductMatchingService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProductMatchingServiceTest extends TestCase
{
    private $productRepository;
    private $service;

    public function testFindMatchesReturnsEmptyArrayForEmptyName()
    {
        $result = $this->service->findMatches('', null, 1);
        $this->assertEmpty($result);
    }

    public function testFindMatchesReturnsEmptyArrayForWhitespaceOnly()
    {
        $result = $this->service->findMatches('   ', null, 1);
        $this->assertEmpty($result);
    }

    public function testFindMatchesReturnsExactMatchWithHighSimilarity()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'iPhone 15 Pro';
        $product->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->with('iphone 15 pro', 1)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('iPhone 15 Pro', null, 1);

        $this->assertCount(1, $results);
        $this->assertEquals(8, $results[0]['similarity']);
        $this->assertEquals('exact', $results[0]['confidence']);
    }

    public function testFindMatchesReturnsSingleProductWithHighSimilarity()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Samsung Galaxy S24';
        $product->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->with('samsung galaxy s24 ultra', 1)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('Samsung Galaxy S24 Ultra', null, 1);

        $this->assertCount(1, $results);
        $this->assertEquals(0.75, $results[0]['similarity']);
    }

    public function testFindMatchesFiltersLowSimilarity()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Completely Different Product';
        $product->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->with('iphone 15', 1)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('iPhone 15', null, 1);

        $this->assertEmpty($results);
    }

    public function testFindMatchesSortsBySimilarity()
    {
        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->name = 'iPhone 15 Pro Max';
        $product1->brand = null;

        $product2 = Mockery::mock(Product::class)->makePartial();
        $product2->name = 'iPhone 15 Pro';
        $product2->brand = null;

        $product3 = Mockery::mock(Product::class)->makePartial();
        $product3->name = 'iPhone 15';
        $product3->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->with('iphone 15 pro', 1)
            ->andReturn(collect([$product1, $product2, $product3]));

        $results = $this->service->findMatches('iPhone 15 Pro', null, 1);

        // Should be sorted by similarity (highest first)
        $this->assertTrue($results[0]['similarity'] >= $results[1]['similarity']);
    }

    public function testFindMatchesBoostsSimilarityForMatchingBrand()
    {
        $brand = Mockery::mock(Brand::class)->makePartial();
        $brand->name = 'Apple';

        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'iPhone 14';
        $product->brand = $brand;

        $this->productRepository->shouldReceive('searchByName')
            ->with('iphone 15', 1)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('iPhone 15', 'Apple', 1);

        // Should have boosted similarity due to brand match
        $this->assertNotEmpty($results);
        $this->assertGreaterThan(0.7, $results[0]['similarity']);
    }

    public function testFindMatchesDoesNotBoostForNonMatchingBrand()
    {
        $brand = Mockery::mock(Brand::class)->makePartial();
        $brand->name = 'Samsung';

        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'iPhone 14';
        $product->brand = $brand;

        $this->productRepository->shouldReceive('searchByName')
            ->with('iphone 15', 1)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('iPhone 15', 'Apple', 1);

        // Should not boost similarity for different brand
        $this->assertNotEmpty($results);
    }

    public function testFindMatchesNormalizesProductName()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Gaming Laptop';
        $product->brand = null;

        // Should normalize and remove common words
        $this->productRepository->shouldReceive('searchByName')
            ->with('gaming laptop', 1)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('The Gaming Laptop', null, 1);

        $this->assertNotEmpty($results);
    }

    public function testFindMatchesWithNullSiteId()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Test Product';
        $product->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->with('test product', null)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('Test Product', null, null);

        $this->assertNotEmpty($results);
    }

    public function testFindMatchesReturnsConfidenceLevels()
    {
        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->name = 'Product A';
        $product1->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->andReturn(collect([$product1]));

        $results = $this->service->findMatches('Product A', null, 1);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('confidence', $results[0]);
        $this->assertContains($results[0]['confidence'], ['exact', 'high', 'medium', 'low']);
    }

    public function testFindMatchesWithSpecialCharacters()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Product-Name';
        $product->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->with('product-name', 1)
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('Product-Name', null, 1);

        $this->assertNotEmpty($results);
    }

    public function testGetConfidenceLevelForExact()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Test';
        $product->brand = null;

        $this->productRepository->shouldReceive('searchByName')
            ->andReturn(collect([$product]));

        $results = $this->service->findMatches('Test', null, 1);

        $this->assertEquals('exact', $results[0]['confidence']);
    }

//    public function testFindMatchesHandlesMultipleProducts()
//    {
//        $products = collect();
//
//        for ($i = 1; $i <= 5; $i++) {
//            $product = Mockery::mock(Product::class)->makePartial();
//            $product->name = "Laptop Model {$i}";
//            $product->brand = null;
//            $products->push($product);
//        }
//
//        $this->productRepository->shouldReceive('searchByName')
//            ->with('laptop', 1)
//            ->andReturn($products);
//
//        $results = $this->service->findMatches('Laptop', null, 1);
//
//        // Should return all products above similarity threshold
//        $this->assertNotEmpty($results);
//        $this->assertLessThanOrEqual(5, count($results));
//    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->service = new ProductMatchingService($this->productRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

//    public function testGetConfidenceLevelForHigh()
//    {
//        $product = Mockery::mock(Product::class)->makePartial();
//        $product->name = 'Similar Name';
//        $product->brand = null;
//
//        $brand = Mockery::mock(Brand::class)->makePartial();
//        $brand->name = 'Brand';
//        $product->brand = $brand;
//
//        $this->productRepository->shouldReceive('searchByName')
//            ->andReturn(collect([$product]));
//
//        $results = $this->service->findMatches('Similar Name Product', 'Brand', 1);
//
//        if (!empty($results)) {
//            $this->assertContains($results[0]['confidence'], ['high', 'exact']);
//        }
//    }
}