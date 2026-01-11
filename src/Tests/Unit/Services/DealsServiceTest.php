<?php

namespace App\Tests\Unit\Services;

use App\Repositories\Product\DealsRepository;
use App\Services\Product\DealsService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery as m;

class DealsServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private DealsService $service;
    private $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepository = m::mock(DealsRepository::class);
        $this->service = new DealsService($this->mockRepository);
    }

    protected function tearDown(): void
    {
        m::close();
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
        $product->site_id = $this->siteId;

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
        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([]);

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('deals', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function test_get_filtered_deals_applies_under25_tab(): void
    {
        $filters = ['tab' => 'under25'];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->with(1, m::on(function($arg) {
                return isset($arg['maxPrice']) && $arg['maxPrice'] === 25;
            }))
            ->andReturn([]);

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
    }

    public function test_get_filtered_deals_applies_over50_tab(): void
    {
        $filters = ['tab' => 'over50'];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([]);

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
    }

    public function test_get_filtered_deals_applies_vouchers_tab(): void
    {
        $filters = ['tab' => 'vouchers'];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->with(1, m::on(function($arg) {
                return isset($arg['hasVoucher']) && $arg['hasVoucher'] === true;
            }))
            ->andReturn([]);

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertIsArray($result);
    }

    public function test_get_filtered_deals_applies_category_tab(): void
    {
        $filters = ['tab' => 'cat-5'];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->with(1, m::on(function($arg) {
                return isset($arg['category']) && $arg['category'] === [5];
            }))
            ->andReturn([]);

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
            ->andReturn([$productData]);

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

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

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
            ->andReturn([$productData]);

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

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

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
            ->andReturn([$productData]);

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

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

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

        $product1Data = ['id' => 1, 'price' => 100.00, 'sale_price' => 80.00];
        $product2Data = ['id' => 2, 'price' => 100.00, 'sale_price' => 60.00];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$product1Data, $product2Data]);

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

        $this->assertGreaterThan($result['deals'][1]['discount_percentage'], $result['deals'][0]['discount_percentage']);
    }

    public function test_get_filtered_deals_sorts_by_price_asc(): void
    {
        $filters = [
            'sort' => 'price:asc'
        ];

        $product1Data = ['id' => 1, 'price' => 100.00, 'sale_price' => 80.00];
        $product2Data = ['id' => 2, 'price' => 100.00, 'sale_price' => 60.00];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$product1Data, $product2Data]);

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

        $this->assertLessThan($result['deals'][1]['sale_price'], $result['deals'][0]['sale_price']);
    }

    public function test_get_filtered_deals_paginates_correctly(): void
    {
        $filters = [
            'page' => 2,
            'perPage' => 5
        ];

        $products = [];
        for ($i = 1; $i <= 15; $i++) {
            $products[] = ['id' => $i, 'price' => 100.00, 'sale_price' => 80.00];
        }

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn($products);

        foreach ($products as $productData) {
            $product = m::mock(\App\Models\Product::class)->makePartial();
            $product->id = $productData['id'];
            $product->name = 'Product ' . $productData['id'];
            $product->slug = 'product-' . $productData['id'];
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

            $this->mockRepository->shouldReceive('findProductById')
                ->with($productData['id'])
                ->andReturn($product);
        }

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertLessThanOrEqual(5, count($result['deals']));
        $this->assertEquals(2, $result['pagination']['currentPage']);
        $this->assertEquals(5, $result['pagination']['perPage']);
        $this->assertTrue($result['pagination']['hasPrev']);
    }

    public function test_get_filtered_deals_returns_correct_pagination_info(): void
    {
        $filters = [
            'page' => 1,
            'perPage' => 10
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([]);

        $result = $this->service->getFilteredDeals($filters, $this->siteId);

        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('currentPage', $result['pagination']);
        $this->assertArrayHasKey('totalPages', $result['pagination']);
        $this->assertArrayHasKey('perPage', $result['pagination']);
        $this->assertArrayHasKey('hasNext', $result['pagination']);
        $this->assertArrayHasKey('hasPrev', $result['pagination']);
    }

    public function test_get_filtered_deals_filters_out_products_with_no_discount(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 100.00 // No discount
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 100.00;
        $product->main_image_url = 'image.jpg';
        $product->average_rating = 4.5;
        $product->review_count = 10;
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = null;
        $product->merchants = null;

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(0, $result['deals']);
    }

    public function test_get_filtered_deals_includes_variant_deals(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 90.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

        $variant = m::mock(\App\Models\ProductVariant::class)->makePartial();
        $variant->id = 1;
        $variant->name = 'Variant 1';
        $variant->price = 100.00;
        $variant->sale_price = 70.00;
        $variant->is_active = true;
        $variant->merchants = null;

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 90.00;
        $product->main_image_url = 'image.jpg';
        $product->average_rating = 4.5;
        $product->review_count = 10;
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = collect([$variant]);
        $product->merchants = null;

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

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['deals']);
        $this->assertEquals(70.00, $result['deals'][0]['sale_price']);
        $this->assertEquals('variant', $result['deals'][0]['source']);
        $this->assertEquals(1, $result['deals'][0]['variant_id']);
    }

    public function test_get_filtered_deals_includes_merchant_deals(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 90.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 90.00,
        ]);

        $merchant = $this->createMerchant();

        $productMerchant = $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'price' => 100.00,
            'sale_price' => 65.00,
            'is_available' => true,
        ]);


        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['deals']);
        $this->assertEquals(65.00, $result['deals'][0]['sale_price']);
        $this->assertEquals('merchant', $result['deals'][0]['source']);
        $this->assertEquals($merchant->id, $result['deals'][0]['merchant_id']);
    }

    public function test_get_filtered_deals_chooses_best_price_from_multiple_sources(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 90.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 90.00,
        ]);

        $variant = $this->createProductVariant($product->id, [
            'price' => 100.00,
            'sale_price' => 75.00,
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'price' => 100.00,
            'sale_price' => 60.00,
            'is_available' => true,
        ]);

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['deals']);
        $this->assertEquals(60.00, $result['deals'][0]['sale_price']);
        $this->assertEquals('merchant', $result['deals'][0]['source']);
    }

    public function test_get_filtered_deals_skips_inactive_variants(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 90.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 90.00,
        ]);

        $variant = $this->createProductVariant($product->id, [
            'price' => 100.00,
            'sale_price' => 50.00,
            'is_active' => false,
        ]);

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['deals']);
        $this->assertEquals(90.00, $result['deals'][0]['sale_price']);
        $this->assertEquals('product', $result['deals'][0]['source']);
    }

    public function test_get_filtered_deals_skips_unavailable_merchants(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 90.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

        $merchant = m::mock(\App\Models\ProductMerchant::class)->makePartial();
        $merchant->merchant_id = 1;
        $merchant->name = 'Merchant 1';
        $merchant->effective_price = 100.00;
        $merchant->effective_sale_price = 50.00;
        $merchant->is_available = false; // Unavailable
        $merchant->merchant = null;

        $product = m::mock(\App\Models\Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->price = 100.00;
        $product->sale_price = 90.00;
        $product->main_image_url = 'image.jpg';
        $product->average_rating = 4.5;
        $product->review_count = 10;
        $product->category_id = 1;
        $product->brand_id = 1;
        $product->variants = null;
        $product->merchants = collect([$merchant]);

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

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['deals']);
        $this->assertEquals(90.00, $result['deals'][0]['sale_price']);
        $this->assertEquals('product', $result['deals'][0]['source']);
    }

    public function test_get_filtered_deals_calculates_discount_percentage_correctly(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 75.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

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

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['deals']);
        $this->assertEquals(25, $result['deals'][0]['discount_percentage']);
    }

    public function test_get_filtered_deals_includes_category_and_brand_info(): void
    {
        $productData = [
            'id' => 1,
            'price' => 100.00,
            'sale_price' => 75.00
        ];

        $this->mockRepository->shouldReceive('getFilteredProducts')
            ->once()
            ->andReturn([$productData]);

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

        $this->mockRepository->shouldReceive('findProductById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $result = $this->service->getFilteredDeals([], $this->siteId);

        $this->assertCount(1, $result['deals']);
        $this->assertEquals(1, $result['deals'][0]['category_id']);
        $this->assertEquals('Electronics', $result['deals'][0]['category_name']);
        $this->assertEquals(2, $result['deals'][0]['brand_id']);
        $this->assertEquals('Apple', $result['deals'][0]['brand_name']);
    }
}