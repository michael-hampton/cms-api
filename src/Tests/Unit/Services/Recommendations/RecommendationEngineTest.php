<?php

namespace App\Tests\Unit\Services\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Recommendations\RecommendationEngine;
use App\Services\Recommendations\Signals\PurchaseSignalProvider;
use App\Services\Recommendations\Signals\ViewSignalProvider;
use App\Services\Recommendations\Signals\PopularProductProvider;
use Mockery;
use PHPUnit\Framework\TestCase;

class RecommendationEngineTest extends TestCase
{
    private $productRepository;
    private $purchaseSignals;
    private $viewSignals;
    private $popularProducts;
    private RecommendationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->purchaseSignals = Mockery::mock(PurchaseSignalProvider::class);
        $this->viewSignals = Mockery::mock(ViewSignalProvider::class);
        $this->popularProducts = Mockery::mock(PopularProductProvider::class);

        $this->engine = new RecommendationEngine(
            $this->productRepository,
            $this->purchaseSignals,
            $this->viewSignals,
            $this->popularProducts
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetRecommendationsWeightsPurchasesHigher(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->popularProducts->shouldReceive('getProducts')
            ->once()
            ->with($siteId, 6, [10, 30, 40])
            ->andReturn(collect([]));

        // Mock purchased product IDs
        $this->purchaseSignals->shouldReceive('getProductIds')
            ->once()
            ->with(1)
            ->andReturn([10]);

        // Mock viewed product IDs
        $this->viewSignals->shouldReceive('getProductIds')
            ->once()
            ->with(1)
            ->andReturn([20]);

        // Mock base products
        $purchasedProduct = $this->createMockProduct(10);
        $viewedProduct = $this->createMockProduct(20);

        $this->productRepository->shouldReceive('findMany')
            ->once()
            ->with([10, 20])
            ->andReturn(collect([$purchasedProduct, $viewedProduct]));

        // Mock related products for purchased item
        $relatedToPurchased = $this->createMockProduct(30);
        $this->productRepository->shouldReceive('findRelated')
            ->once()
            ->with($purchasedProduct, 3)
            ->andReturn(collect([$relatedToPurchased]));

        // Mock related products for viewed item
        $relatedToViewed = $this->createMockProduct(40);
        $this->productRepository->shouldReceive('findRelated')
            ->once()
            ->with($viewedProduct, 2)
            ->andReturn(collect([$relatedToViewed]));

        $result = $this->engine->getRecommendations($member, $siteId, 6);

        $this->assertInstanceOf(Collection::class, $result);
        // Product 30 should come first (weight 3.0 from purchase)
        // Product 40 should come second (weight 1.0 from view)
        $this->assertEquals(30, $result->first()->id);
    }

    public function testGetRecommendationsExcludesPurchasedProducts(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->popularProducts->shouldReceive('getProducts')
            ->once()
            ->with($siteId, 6, [10])
            ->andReturn(collect([]));

        $this->purchaseSignals->shouldReceive('getProductIds')
            ->once()
            ->andReturn([10]);

        $this->viewSignals->shouldReceive('getProductIds')
            ->once()
            ->andReturn([]);

        $purchasedProduct = $this->createMockProduct(10);
        $this->productRepository->shouldReceive('findMany')
            ->once()
            ->andReturn(collect([$purchasedProduct]));

        // Related product is the same as purchased
        $this->productRepository->shouldReceive('findRelated')
            ->once()
            ->andReturn(collect([$purchasedProduct]));

        $result = $this->engine->getRecommendations($member, $siteId, 6);

        // Should not include the purchased product
        $this->assertCount(0, $result);
    }

    public function testGetRecommendationsFallsBackToPopular(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->purchaseSignals->shouldReceive('getProductIds')
            ->once()
            ->andReturn([]);

        $this->viewSignals->shouldReceive('getProductIds')
            ->once()
            ->andReturn([]);

        $this->productRepository->shouldReceive('findMany')
            ->once()
            ->andReturn(collect([]));

        $popularProduct = $this->createMockProduct(100);
        $this->popularProducts->shouldReceive('getProducts')
            ->once()
            ->with($siteId, 6, [])
            ->andReturn(collect([$popularProduct]));

        $result = $this->engine->getRecommendations($member, $siteId, 6);

        $this->assertCount(1, $result);
        $this->assertEquals(100, $result->first()->id);
    }

    private function createMockProduct(int $id): Product
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = $id;
        $product->name = "Product {$id}";
        return $product;
    }
}