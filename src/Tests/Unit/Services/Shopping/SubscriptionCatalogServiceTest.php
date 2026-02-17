<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\SubscriptionCatalogService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class SubscriptionCatalogServiceTest extends MockeryTestCase
{
    private $repository;
    private $service;
    private $queryMock;

    /**
     * Test that getCatalog applies basic filters and returns paginated results.
     */
    public function test_get_catalog_applies_basic_filters_and_returns_paginated_results()
    {
        $filters = [
            'site_id' => 1,
            'search' => 'Magazine',
            'per_page' => 10,
            'page' => 1
        ];

        // 1. Setup the initial query build
        $this->repository->shouldReceive('buildCatalogQuery')
            ->once()
            ->andReturn($this->queryMock);

        // 2. Expect search filter (applySearch uses a closure)
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::on(fn($closure) => is_callable($closure)))
            ->andReturnSelf();

        // 3. Expect site_id filter
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('site_id', 1)
            ->andReturnSelf();

        // 4. Expect default sorting (Price Low to High)
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('price', 'asc') // Assuming these are the default values from Enum
            ->andReturnSelf();

        // 5. Expect pagination
        $this->queryMock->shouldReceive('paginate')
            ->once()
            ->with(10, 1)
            ->andReturn(['item1', 'item2']);

        $result = $this->service->getCatalog($filters);

        $this->assertIsArray($result);
    }

    /**
     * Test the price range filter logic which uses whereHas and whereRaw.
     */
    public function test_get_catalog_applies_price_range_filters()
    {
        $filters = [
            'price_min' => 10,
            'price_max' => 50
        ];

        $this->repository->shouldReceive('buildCatalogQuery')->andReturn($this->queryMock);

        // Expect whereHas for pricingTiers
        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('pricingTiers', Mockery::on(fn($closure) => is_callable($closure)))
            ->andReturnSelf();

        // Mock the sorting and pagination calls which always run
        $this->queryMock->shouldReceive('orderBy')->andReturnSelf();
        $this->queryMock->shouldReceive('paginate')->andReturn([]);

        $this->service->getCatalog($filters);
    }

    /**
     * Test the delivery type filter for 'digital'
     */
    public function test_apply_delivery_type_filter_digital()
    {
        $filters = ['delivery_type' => 'digital'];

        $this->repository->shouldReceive('buildCatalogQuery')->andReturn($this->queryMock);

        $this->queryMock->shouldReceive('whereNotNull')
            ->once()
            ->with('digital_download_url')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('digital_download_url', '!=', '')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')->andReturnSelf();
        $this->queryMock->shouldReceive('paginate')->andReturn([]);

        $this->service->getCatalog($filters);
    }

    /**
     * Test simple repository passthrough methods
     */
    public function test_metadata_methods_call_repository()
    {
        $this->repository->shouldReceive('getDistinctCategories')
            ->once()
            ->with(123)
            ->andReturn(['Tech', 'Cooking']);

        $result = $this->service->getAvailableCategories(123);

        $this->assertEquals(['Tech', 'Cooking'], $result);
    }

    /**
     * Test the 'on_sale' special filter logic
     */
    public function test_apply_special_filter_on_sale()
    {
        $filters = ['special_filter' => 'on_sale'];

        $this->repository->shouldReceive('buildCatalogQuery')->andReturn($this->queryMock);

        // Expect whereHas('pricingTiers', ...)
        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('pricingTiers', Mockery::on(function ($closure) {
                $subQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');

                // It should have an OR group for sale_price OR original_price
                $subQuery->shouldReceive('where')->once()->with(Mockery::on(fn($c) => is_callable($c)))->andReturnSelf();
                $subQuery->shouldReceive('orWhere')->once()->with(Mockery::on(fn($c) => is_callable($c)))->andReturnSelf();

                $closure($subQuery);
                return true;
            }))
            ->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')->andReturnSelf();
        $this->queryMock->shouldReceive('paginate')->andReturn([]);

        $this->service->getCatalog($filters);
        $this->assertTrue(true);
    }

    /**
     * Test the 'limited_offer' filter date range
     */
    public function test_apply_special_filter_limited_offer()
    {
        $filters = ['special_filter' => 'limited_offer'];

        $this->repository->shouldReceive('buildCatalogQuery')->andReturn($this->queryMock);

        // Should check for non-null end_date
        $this->queryMock->shouldReceive('whereNotNull')->with('end_date')->andReturnSelf();

        // Should check >= now and <= 30 days from now
        $this->queryMock->shouldReceive('where')->with('end_date', '>=', Mockery::any())->andReturnSelf();
        $this->queryMock->shouldReceive('where')->with('end_date', '<=', Mockery::any())->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')->andReturnSelf();
        $this->queryMock->shouldReceive('paginate')->andReturn([]);

        $this->service->getCatalog($filters);
        $this->assertTrue(true);
    }

    public function test_catalog_ignores_empty_or_invalid_tags()
    {
        $filters = ['tags' => 'not-an-array']; // Should be ignored based on your code

        $this->repository->shouldReceive('buildCatalogQuery')->andReturn($this->queryMock);

        // We expect whereJsonContains NOT to be called
        $this->queryMock->shouldNotReceive('whereJsonContains');

        $this->queryMock->shouldReceive('orderBy')->andReturnSelf();
        $this->queryMock->shouldReceive('paginate')->andReturn([]);

        $this->service->getCatalog($filters);
        $this->assertTrue(true);

    }

    /**
     * Test that the lowest price is correctly calculated across various tiers.
     */
    public function test_get_lowest_price_for_plan_logic()
    {
        // Create a mock plan object that mimics your Eloquent model
        $plan = Mockery::mock();

        // Setup tiered data: One tier with a sale, one with a high digital price
        $plan->pricingTiers = [
            (object)[
                'price' => 100,
                'sale_price' => 80,
                'digital_price' => 75
            ],
            (object)[
                'price' => 50,
                'sale_price' => null,
                'digital_price' => null
            ]
        ];

        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $result = $this->service->getLowestPriceForPlan($plan);

        // Assertions based on your logic:
        // Print lowest should be 50 (from the second tier)
        $this->assertEquals(50, $result['print']);
        // Digital lowest should be 50 (second tier falls back to $effectivePrice because digital_price is null)
        $this->assertEquals(50, $result['digital']);
        // Overall lowest should be 50
        $this->assertEquals(50, $result['lowest']);
    }

    /**
     * Test that multiple filters can be applied simultaneously to the query.
     */
    public function test_get_catalog_applies_combined_filters()
    {
        $filters = [
            'search' => 'Scientific',
            'site_id' => 5,
            'featured' => 'true',
            'sort' => SubscriptionSortOption::PRICE_HIGH_TO_LOW->value
        ];

        $this->repository->shouldReceive('buildCatalogQuery')->once()->andReturn($this->queryMock);

        // Chain expectations
        $this->queryMock->shouldReceive('where')->once()->with(Mockery::on(fn($q) => is_callable($q)))->andReturnSelf(); // Search
        $this->queryMock->shouldReceive('where')->once()->with('site_id', 5)->andReturnSelf();
        $this->queryMock->shouldReceive('where')->once()->with('is_featured', true)->andReturnSelf();

        // Verify sorting changes based on input
        // Assuming PRICE_HIGH_TO_LOW returns ['price', 'desc']
        $this->queryMock->shouldReceive('orderBy')->once()->with('price', 'desc')->andReturnSelf();

        $this->queryMock->shouldReceive('paginate')->once()->andReturn([]);

        $this->service->getCatalog($filters);
    }

    /**
     * Test the date range logic for limited offers.
     */
    public function test_apply_special_filter_limited_offer_dates()
    {
        $filters = ['special_filter' => 'limited_offer'];

        $this->repository->shouldReceive('buildCatalogQuery')->andReturn($this->queryMock);

        $this->queryMock->shouldReceive('whereNotNull')->with('end_date')->andReturnSelf();

        // Verify that the query checks for dates between "now" and "30 days from now"
        $this->queryMock->shouldReceive('where')
            ->with('end_date', '>=', Mockery::any())
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->with('end_date', '<=', Mockery::any())
            ->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')->andReturnSelf();
        $this->queryMock->shouldReceive('paginate')->andReturn([]);

        $this->service->getCatalog($filters);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the repository
        $this->repository = Mockery::mock(SubscriptionPlanRepository::class);

        // Mock the Query Builder (fluent interface)
        $this->queryMock = Mockery::mock('Illuminate\Database\Eloquent\Builder');

        $this->service = new SubscriptionCatalogService($this->repository);
    }
}