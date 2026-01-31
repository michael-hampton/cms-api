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
use App\Services\Recommendations\ProductRecommendationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ProductRecommendationServiceTest extends FunctionalTestCase
{
    private ProductRecommendationService $service;
    private $productRepository;
    private $orderRepository;
    private $productViewRepository;

    public function testGetRecommendedProductsReturnsRelatedProducts(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->setOrderExpectations([10, 20]);
        $this->setViewExpectations([30, 40]);

        // Mock products
        $product1 = $this->createMockProduct(10);
        $product2 = $this->createMockProduct(20);
        $product3 = $this->createMockProduct(30);
        $product4 = $this->createMockProduct(40);

        $relatedProduct1 = $this->createMockProduct(50);
        $relatedProduct2 = $this->createMockProduct(60);
        $relatedProduct3 = $this->createMockProduct(70);
        $relatedProduct4 = $this->createMockProduct(80);

        // Set up find expectations
        $this->productRepository->shouldReceive('find')->with(10)->once()->andReturn($product1);
        $this->productRepository->shouldReceive('find')->with(20)->once()->andReturn($product2);
        $this->productRepository->shouldReceive('find')->with(30)->once()->andReturn($product3);
        $this->productRepository->shouldReceive('find')->with(40)->once()->andReturn($product4);

        // Set up related product expectations
        $this->productRepository->shouldReceive('findRelated')->with($product1, 3)->once()->andReturn(collect([$relatedProduct1]));
        $this->productRepository->shouldReceive('findRelated')->with($product2, 3)->once()->andReturn(collect([$relatedProduct2]));
        $this->productRepository->shouldReceive('findRelated')->with($product3, 2)->once()->andReturn(collect([$relatedProduct3]));
        $this->productRepository->shouldReceive('findRelated')->with($product4, 2)->once()->andReturn(collect([$relatedProduct4]));

        $result = $this->service->getRecommendedProducts($member, $siteId, 4);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(4, $result);
    }

    private function setOrderExpectations(array $productIds = []): void
    {
        $order = new Order();
        $items = collect();

        foreach ($productIds as $productId) {
            $orderItem = new OrderItem(['product_id' => $productId]);
            $items->push($orderItem);
        }

        $order->items = $items;
        $orders = collect([$order]);

        $this->orderRepository
            ->shouldReceive('getByUser')
            ->with(1)
            ->once()
            ->andReturn($orders);
    }

    private function setViewExpectations(array $productIds = []): void
    {
        $this->productViewRepository
            ->shouldReceive('getViewedProductIdsByMember')
            ->with(1, 20, 30)
            ->once()
            ->andReturn($productIds);
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

        return $product;
    }

    public function testGetRecommendedProductsExcludesPurchasedProducts(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->setOrderExpectations([10]);
        $this->setViewExpectations([]);

        $purchasedProduct = $this->createMockProduct(10);
        $relatedProduct = $this->createMockProduct(20);
        $alreadyPurchased = $this->createMockProduct(10); // Should be excluded

        $this->productRepository->shouldReceive('find')->with(10)->once()->andReturn($purchasedProduct);
        $this->productRepository->shouldReceive('findRelated')->with($purchasedProduct, 3)->once()->andReturn(collect([$relatedProduct, $alreadyPurchased]));

        $result = $this->service->getRecommendedProducts($member, $siteId, 2);

        $resultIds = $result->pluck('id')->toArray();
        $this->assertNotContains(10, $resultIds);
        $this->assertContains(20, $resultIds);
    }

    public function testGetRecommendedProductsIncludesViewingHistory(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->setOrderExpectations([]);
        $this->setViewExpectations([100, 200]);

        $viewedProduct1 = $this->createMockProduct(100);
        $viewedProduct2 = $this->createMockProduct(200);
        $relatedProduct1 = $this->createMockProduct(300);
        $relatedProduct2 = $this->createMockProduct(400);

        $this->productRepository->shouldReceive('find')->with(100)->once()->andReturn($viewedProduct1);
        $this->productRepository->shouldReceive('find')->with(200)->once()->andReturn($viewedProduct2);
        $this->productRepository->shouldReceive('findRelated')->with($viewedProduct1, 2)->once()->andReturn(collect([$relatedProduct1]));
        $this->productRepository->shouldReceive('findRelated')->with($viewedProduct2, 2)->once()->andReturn(collect([$relatedProduct2]));

        $result = $this->service->getRecommendedProducts($member, $siteId, 2);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function testGetRecommendedProductsFallbackToPopular(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->setOrderExpectations([]);
        $this->setViewExpectations([]);

        $popularProduct = $this->createMockProduct(500);

        $this->productRepository->shouldReceive('getRecommendationProducts')
            ->with($siteId, 6, [])
            ->once()
            ->andReturn(collect([$popularProduct]));

        $result = $this->service->getRecommendedProducts($member, $siteId, 6);

        $this->assertCount(1, $result);
        $this->assertEquals(500, $result->first()->id);
    }

    public function testGetFormattedRecommendationsReturnsFormattedArray(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->setOrderExpectations([]);
        $this->setViewExpectations([]);

        $product = $this->createMockProduct(1);
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->description = 'A great product for testing purposes';
        $product->main_image_url = 'https://example.com/image.jpg';
        $product->image = 'fallback.jpg';
        $product->price = 99.99;
        $product->sale_price = 79.99;
        $product->discount_percentage = 20;

        $this->productRepository->shouldReceive('getRecommendationProducts')
            ->once()
            ->andReturn(collect([$product]));

        $result = $this->service->getFormattedRecommendations($member, $siteId, 6);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('Test Product', $result[0]['name']);
        $this->assertEquals(99.99, $result[0]['price']);
        $this->assertTrue($result[0]['has_discount']);
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

    public function testTruncateDescriptionWorksCorrectly(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setOrderExpectations([]);
        $this->setViewExpectations([]);

        $product = $this->createMockProduct(1);
        $product->name = 'Product';
        $product->slug = 'product';
        $product->description = str_repeat('A', 200);
        $product->main_image_url = 'image.jpg';
        $product->price = 10.00;
        $product->sale_price = null;
        $product->discount_percentage = 0;

        $this->productRepository->shouldReceive('getRecommendationProducts')
            ->once()
            ->andReturn(collect([$product]));

        $result = $this->service->getFormattedRecommendations($member, 1, 1);

        $this->assertEquals(153, strlen($result[0]['description']));
        $this->assertStringEndsWith('...', $result[0]['description']);
    }

    public function testGetViewingBasedRecommendationsUsesViewHistory(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $viewedProduct1 = $this->createMockProduct(100);
        $viewedProduct2 = $this->createMockProduct(200);

        $this->productViewRepository
            ->shouldReceive('getViewedProductsByMember')
            ->with(1, 10)
            ->once()
            ->andReturn(collect([$viewedProduct1, $viewedProduct2]));

        $relatedProduct = $this->createMockProduct(300);

        $this->productRepository
            ->shouldReceive('findRelated')
            ->with($viewedProduct1, 3)
            ->once()
            ->andReturn(collect([$relatedProduct]));

        $this->productRepository
            ->shouldReceive('findRelated')
            ->with($viewedProduct2, 3)
            ->once()
            ->andReturn(collect([]));

        $this->productViewRepository
            ->shouldReceive('getFrequentlyViewedWith')
            ->twice()
            ->andReturn([]);

        $result = $this->service->getViewingBasedRecommendations($member, $siteId, 6);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    public function testGetViewingBasedRecommendationsReturnsEmptyWhenNoViews(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $this->productViewRepository
            ->shouldReceive('getViewedProductsByMember')
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->getViewingBasedRecommendations($member, $siteId, 6);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testGetViewingBasedRecommendationsIncludesFrequentlyViewedWith(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;

        $viewedProduct = $this->createMockProduct(100);

        $this->productViewRepository
            ->shouldReceive('getViewedProductsByMember')
            ->once()
            ->andReturn(collect([$viewedProduct]));

        $this->productRepository
            ->shouldReceive('findRelated')
            ->once()
            ->andReturn(collect([]));

        $frequentProduct = $this->createMockProduct(500);

        $this->productViewRepository
            ->shouldReceive('getFrequentlyViewedWith')
            ->with(100, 3)
            ->once()
            ->andReturn([500]);

        // Mock Product::whereIn
        $this->productRepository->shouldReceive('getActiveProducts')
            ->with([500])
            ->andReturn(collect([$frequentProduct]));

        $result = $this->service->getViewingBasedRecommendations($member, $siteId, 6);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->productViewRepository = Mockery::mock(ProductViewRepository::class);

        $this->service = new ProductRecommendationService(
            $this->productRepository,
            $this->orderRepository,
            $this->productViewRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }


}