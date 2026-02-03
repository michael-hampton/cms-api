<?php

namespace App\Tests\Unit\Services\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Resources\ProductRecommendationResource;
use App\Services\Recommendations\ProductRecommendationService;
use App\Services\Recommendations\RecommendationEngine;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ProductRecommendationServiceTest extends FunctionalTestCase
{
    private ProductRecommendationService $service;
    private $productRepository;
    private $engine;
    private $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->engine = Mockery::mock(RecommendationEngine::class);
        $this->resource = Mockery::mock(ProductRecommendationResource::class);

        $this->service = new ProductRecommendationService(
            $this->productRepository,
            $this->engine,
            $this->resource
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetCrossSellProductsReturnsRelatedProducts(): void
    {
        $product = $this->createMockProduct(1);
        $related1 = $this->createMockProduct(2);
        $related2 = $this->createMockProduct(3);

        $this->productRepository
            ->shouldReceive('findRelated')
            ->with($product, 4)
            ->once()
            ->andReturn(collect([$related1, $related2]));

        $result = $this->service->getCrossSellProducts($product, 4);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function testGetRecommendedProductsUsesEngine(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $product1 = $this->createMockProduct(1);
        $product2 = $this->createMockProduct(2);

        $this->engine->shouldReceive('getRecommendations')
            ->once()
            ->with($member, $siteId, 6)
            ->andReturn(collect([$product1, $product2]));

        $result = $this->service->getRecommendedProducts($member, $siteId, 6);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function testGetFormattedRecommendationsUsesResource(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $product = $this->createMockProduct(1);
        $product->name = 'Test Product';
        $product->price = 99.99;

        $this->engine->shouldReceive('getRecommendations')
            ->once()
            ->andReturn(collect([$product]));

        $formatted = [
            'id' => 1,
            'name' => 'Test Product',
            'price' => 99.99,
        ];

        $this->resource->shouldReceive('collection')
            ->once()
            ->andReturn([$formatted]);

        $result = $this->service->getFormattedRecommendations($member, $siteId, 6);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Test Product', $result[0]['name']);
    }

    private function createMockProduct(int $id): Product
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = $id;
        $product->name = "Product {$id}";
        $product->slug = "product-{$id}";
        $product->description = "Description for product {$id}";
        $product->price = 99.99;
        $product->sale_price = null;
        $product->discount_percentage = 0;
        $product->main_image_url = null;
        $product->image = 'default.jpg';
        $product->currency = 'USD';

        return $product;
    }
}