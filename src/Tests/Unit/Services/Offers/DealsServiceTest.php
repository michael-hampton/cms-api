<?php

namespace App\Tests\Unit\Services\Offers;

use App\Enums\Boost\BoostContext;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Offers\DealsRepository;
use App\Repositories\ReviewRepository;
use App\Services\Adverts\Boost\BoostEventService;
use App\Services\Adverts\Boost\BoostRankingService;
use App\Services\Offers\DealsService;
use App\Tests\Unit\UnitTestCase;
use Mockery as m;

class DealsServiceTest extends UnitTestCase
{
    private int $siteId = 1;

    private DealsService $service;
    private $mockRepository;
    private $reviewRepository;
    private $boostRankingService;
    private $boostRepository;
    private $boostEventService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepository = m::mock(DealsRepository::class);
        $this->reviewRepository = m::mock(ReviewRepository::class);
        $this->boostRankingService = m::mock(BoostRankingService::class);
        $this->boostRepository = m::mock(BoostRepository::class);
        $this->boostEventService = m::mock(BoostEventService::class);

        // Default: no boosted products
        $this->boostRankingService->shouldReceive('getActiveBoostedIds')
            ->andReturn([])
            ->byDefault();

        // Default: no active boosts for impression recording
        $this->boostRepository->shouldReceive('findActiveForTarget')
            ->andReturnNull()
            ->byDefault();

        $this->service = new DealsService(
            $this->reviewRepository,
            $this->boostRankingService,
            $this->boostRepository,
            $this->boostEventService,
            $this->mockRepository
        );
    }


    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_get_todays_deals_returns_featured_deals_when_available(): void
    {
        $today = date('Y-m-d');

        $featuredDeals = [
            [
                'id' => 1,
                'product_id' => 1,
                'site_id' => 1,
                'featured_date' => $today,
                'is_active' => true,
                'position' => 1
            ]
        ];

        $this->mockRepository->shouldReceive('getFeaturedDealsByDate')
            ->once()
            ->with(1, $today, 20)
            ->andReturn($featuredDeals);

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->main_image_url = 'image.jpg';
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = null;
        $product->merchants = null;
        $product->shouldReceive('getAverageRatingAttribute')->andReturn(4.5);
        $product->shouldReceive('getReviewCountAttribute')->andReturn(10);

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'BrandName';
        $product->brand = $brand;

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $deals = $this->service->getTodaysDeals(20, $this->siteId);

        $this->assertIsArray($deals);
    }

    public function test_get_todays_deals_generates_default_deals_when_no_featured(): void
    {
        $today = date('Y-m-d');

        $this->mockRepository->shouldReceive('getFeaturedDealsByDate')
            ->once()
            ->with(1, $today, 20)
            ->andReturn([]);

        $this->mockRepository->shouldReceive('getProductsForDeals')
            ->once()
            ->andReturn([]);

        $deals = $this->service->getTodaysDeals(20, $this->siteId);

        $this->assertIsArray($deals);
        $this->assertEquals([], $deals);
    }

    public function test_refresh_todays_deals_deactivates_old_deals(): void
    {
        $today = date('Y-m-d');

        $this->mockRepository->shouldReceive('deactivateOldFeaturedDeals')
            ->once()
            ->with(1, $today)
            ->andReturn(5);

        $this->mockRepository->shouldReceive('deactivateFeaturedDealsByDate')
            ->once()
            ->with(1, $today)
            ->andReturn(3);

        $this->mockRepository->shouldReceive('getProductsForDeals')
            ->once()
            ->andReturn([]);

        $deals = $this->service->refreshTodaysDeals($this->siteId);

        $this->assertIsArray($deals);
    }

    public function test_refresh_todays_deals_creates_new_featured_deals(): void
    {
        $today = date('Y-m-d');

        $this->mockRepository->shouldReceive('deactivateOldFeaturedDeals')
            ->once()
            ->andReturn(0);

        $this->mockRepository->shouldReceive('deactivateFeaturedDealsByDate')
            ->once()
            ->andReturn(0);

        $productData = [
            'id' => 1,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 100.00,
            'sale_price' => 80.00
        ];

        $this->mockRepository->shouldReceive('getProductsForDeals')
            ->once()
            ->andReturn([$productData]);

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->main_image_url = 'image.jpg';
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = null;
        $product->merchants = null;
        $product->site_id = $this->siteId;
        $product->shouldReceive('getAverageRatingAttribute')->andReturn(4.5);
        $product->shouldReceive('getReviewCountAttribute')->andReturn(10);

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'BrandName';
        $product->brand = $brand;

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->mockRepository->shouldReceive('createFeaturedDeal')
            ->once()
            ->andReturn(m::mock(\App\Models\FeaturedDeal::class));

        $deals = $this->service->refreshTodaysDeals($this->siteId);

        $this->assertIsArray($deals);
        $this->assertCount(1, $deals);
    }

    public function test_get_filtered_deals_returns_correct_structure(): void
    {
        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function test_get_filtered_deals_applies_under25_tab(): void
    {
        $product = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;

        $filters = ['max_price' => 25];

        $this->boostRankingService->shouldReceive('getActiveBoostedIds')
            ->once()
            ->with(BoostContext::Deals->value)
            ->andReturn([5, 10]);

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->with(1, m::on(function($arg) {
                return isset($arg['max_price']) && $arg['max_price'] === 25;
            }), [5, 10])
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
    }

    public function test_get_filtered_deals_applies_over50_tab(): void
    {
        $product = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;

        $filters = ['min_price' => 50];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
    }

    public function test_get_filtered_deals_applies_vouchers_tab(): void
    {
        $product = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;

        $filters = ['tab' => 'vouchers'];

        $this->boostRankingService->shouldReceive('getActiveBoostedIds')
            ->once()
            ->with(BoostContext::Deals->value)
            ->andReturn([5, 10]);

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->with(1, m::on(function($arg) {
                return isset($arg['tab']) && $arg['tab'] === 'vouchers';
            }), [5, 10])
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
    }

    public function test_get_filtered_deals_applies_category_tab(): void
    {
        $filters = ['category_ids' => [1780]];

        $product = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;

        $this->boostRankingService->shouldReceive('getActiveBoostedIds')
            ->once()
            ->with(BoostContext::Deals->value)
            ->andReturn([5, 10]);

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->with(1, m::on(function($arg) {
                return isset($arg['category_ids']) && $arg['category_ids'] === [1780];
            }), [5, 10])
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
    }

    public function test_get_filtered_deals_applies_price_filter(): void
    {
        $filters = [
            'minPrice' => 50,
            'maxPrice' => 100
        ];

        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 75.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$productData]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 75.00;
        $product->main_image_url = 'image.jpg';
        $product->average_rating = 4.5;
        $product->review_count = 10;
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = null;
        $product->merchants = null;

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'BrandName';
        $product->brand = $brand;

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
        foreach ($result['deals'] as $deal) {
            $this->assertGreaterThanOrEqual(50, $deal['sale_price']);
            $this->assertLessThanOrEqual(100, $deal['sale_price']);
        }
    }

    public function test_get_filtered_deals_applies_rating_filter(): void
    {
        $filters = [
            'rating' => [4]
        ];

        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 80.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$productData]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 80.00;
        $product->main_image_url = 'image.jpg';
        $product->average_rating = 4.5;
        $product->review_count = 10;
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = null;
        $product->merchants = null;

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'BrandName';
        $product->brand = $brand;

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
        foreach ($result['deals'] as $deal) {
            $this->assertGreaterThanOrEqual(4, $deal['rating']);
        }
    }

    public function test_get_filtered_deals_applies_discount_filter(): void
    {
        $filters = [
            'discount' => 30
        ];

        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 60.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$productData]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 60.00;
        $product->main_image_url = 'image.jpg';
        $product->average_rating = 4.5;
        $product->review_count = 10;
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = null;
        $product->merchants = null;

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'BrandName';
        $product->brand = $brand;

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
        foreach ($result['deals'] as $deal) {
            $this->assertGreaterThanOrEqual(30, $deal['discount_percentage']);
        }
    }

    public function test_get_filtered_deals_sorts_by_discount_desc(): void
    {
        $filters = [
            'sort' => 'discount:desc'
        ];

        $product1 = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product1->id = 1;
        $product1->price = 100.00;
        $product1->sale_price = 80.00;
        $product2 = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product2->price = 100.00;
        $product2->sale_price = 60.00;
        $product2->id = 2;

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product1, $product2]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1, 2])
            ->andReturn(collect([$review]));

        $product1 = m::mock(\App\Models\Product::class)->makePartial();
        $product1->id = 1;
        $product1->name = 'Product 1';
        $product1->slug = 'product-1';
        $product1->price = 100.00;
        $product1->sale_price = 80.00;
        $product1->main_image_url = 'image.jpg';
        $product1->average_rating = 4.5;
        $product1->review_count = 10;
        $product1->category_id = 1;
        $product1->brand_id = 1;
        $product1->variants = null;
        $product1->merchants = null;

        $product2 = m::mock(\App\Models\Product::class)->makePartial();
        $product2->id = 2;
        $product2->name = 'Product 2';
        $product2->slug = 'product-2';
        $product2->price = 100.00;
        $product2->sale_price = 60.00;
        $product2->main_image_url = 'image.jpg';
        $product2->average_rating = 4.5;
        $product2->review_count = 10;
        $product2->category_id = 1;
        $product2->brand_id = 1;
        $product2->variants = null;
        $product2->merchants = null;

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product1->category = $category;
        $product2->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'BrandName';
        $product1->brand = $brand;
        $product2->brand = $brand;

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertGreaterThan($result['data'][0]['discount_percentage'], $result['data'][1]['discount_percentage']);
    }

    public function test_get_filtered_deals_sorts_by_price_asc(): void
    {
        $filters = [
            'sort' => 'price:asc'
        ];

        $product1 = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product1->id = 1;
        $product1->price = 100.00;
        $product1->sale_price = 80.00;
        $product2 = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product2->price = 100.00;
        $product2->sale_price = 60.00;
        $product2->id = 2;

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product1, $product2]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1, 2])
            ->andReturn(collect([$review]));

        $product1 = m::mock(\App\Models\Product::class)->makePartial();
        $product1->id = 1;
        $product1->name = 'Product 1';
        $product1->slug = 'product-1';
        $product1->price = 100.00;
        $product1->sale_price = 80.00;
        $product1->main_image_url = 'image.jpg';
        $product1->average_rating = 4.5;
        $product1->review_count = 10;
        $product1->category_id = 1;
        $product1->brand_id = 1;
        $product1->variants = null;
        $product1->merchants = null;

        $product2 = m::mock(\App\Models\Product::class)->makePartial();
        $product2->id = 2;
        $product2->name = 'Product 2';
        $product2->slug = 'product-2';
        $product2->price = 100.00;
        $product2->sale_price = 60.00;
        $product2->main_image_url = 'image.jpg';
        $product2->average_rating = 4.5;
        $product2->review_count = 10;
        $product2->category_id = 1;
        $product2->brand_id = 1;
        $product2->variants = null;
        $product2->merchants = null;

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product1->category = $category;
        $product2->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'BrandName';
        $product1->brand = $brand;
        $product2->brand = $brand;

        $this->mockRepository->shouldReceive('findProductById')
            ->with(1)
            ->andReturn($product1);

        $this->mockRepository->shouldReceive('findProductById')
            ->with(2)
            ->andReturn($product2);

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertLessThan($result['data'][0]['sale_price'], $result['data'][1]['sale_price']);
    }

    public function test_get_filtered_deals_returns_correct_pagination_info(): void
    {
        $filters = [
            'page' => 1,
            'perPage' => 10
        ];

        $product = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('current_page', $result['pagination']);
        $this->assertArrayHasKey('total', $result['pagination']);
        $this->assertArrayHasKey('per_page', $result['pagination']);
        $this->assertArrayHasKey('last_page', $result['pagination']);
    }


    public function test_get_filtered_deals_includes_category_and_brand_info(): void
    {
        $product1 = \Mockery::mock(\App\Models\Product::class)->makePartial();
        $product1->id = 1;
        $product1->price = 100.00;
        $product1->sale_price = 80.00;
        $product1->category_id = 1;
        $product1->brand_id = 2;

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([
                'data' => new \App\Framework\Support\Collection([$product1]),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 1,
                    'last_page' => 1
                ]
            ]);

        $review = m::mock(\App\Models\Review::class)->makePartial();

        $this->reviewRepository->shouldReceive('getTopReview')
            ->once()
            ->with([1])
            ->andReturn(collect([$review]));

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 75.00;
        $product->main_image_url = 'image.jpg';
        $product->average_rating = 4.5;
        $product->review_count = 10;
        $product->category_id = 1;
        $product->brand_id = 2;
        $product->variants = null;
        $product->merchants = null;

        $category = m::mock(\App\Models\Category::class)->makePartial();
        $category->name = 'Electronics';
        $product->category = $category;

        $brand = m::mock(\App\Models\Brand::class)->makePartial();
        $brand->name = 'Apple';
        $product->brand = $brand;

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['data']);
        $this->assertEquals(1, $result['data'][0]['category_id']);
        $this->assertEquals(2, $result['data'][0]['brand_id']);
    }
}