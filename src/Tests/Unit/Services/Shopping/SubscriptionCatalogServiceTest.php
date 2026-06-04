<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Enums\Subscriptions\SubscriptionDeliveryType;
use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Enums\Subscriptions\SubscriptionType;
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
    public function test_get_catalog_applies_basic_filters_and_returns_paginated_results(): void
    {
        $filters = [
            'site_id' => 1,
            'search' => 'Magazine',
            'per_page' => 10,
            'page' => 1,
        ];

        $this->repository->shouldReceive('buildCatalogQuery')
            ->once()
            ->andReturn($this->queryMock);

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::on(fn($closure) => is_callable($closure)))
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('site_id', 1)
            ->andReturnSelf();

        // Price sorts now order by the computed column, not raw 'price'.
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('lowest_effective_price', 'asc')
            ->andReturnSelf();

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
        $filters = ['delivery_type' => SubscriptionType::DIGITAL->value];

        $this->repository->shouldReceive('buildCatalogQuery')->andReturn($this->queryMock);

        $this->queryMock->shouldReceive('whereIn')
            ->once()
            ->with('delivery_type', [
                SubscriptionDeliveryType::DIGITAL->value,
                SubscriptionDeliveryType::PRINT_AND_DIGITAL->value,
            ])
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
    public function test_get_catalog_applies_combined_filters(): void
    {
        $filters = [
            'search' => 'Scientific',
            'site_id' => 5,
            'featured' => 'true',
            'sort' => SubscriptionSortOption::PRICE_HIGH_TO_LOW->value,
        ];

        $this->repository->shouldReceive('buildCatalogQuery')->once()->andReturn($this->queryMock);

        $this->queryMock->shouldReceive('where')->once()->with(Mockery::on(fn($q) => is_callable($q)))->andReturnSelf();
        $this->queryMock->shouldReceive('where')->once()->with('site_id', 5)->andReturnSelf();
        $this->queryMock->shouldReceive('where')->once()->with('is_featured', true)->andReturnSelf();

        // Price sorts now reference the computed column.
        $this->queryMock->shouldReceive('orderBy')->once()->with('lowest_effective_price', 'desc')->andReturnSelf();

        $this->queryMock->shouldReceive('paginate')->once()->andReturn([]);

        $this->service->getCatalog($filters);

        $this->assertTrue(true);
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

    /**
     * A tier with a sale_price uses sale_price as the effective print price.
     * Its digital_price is used as-is when present.
     * A second tier with no sale and no digital_price falls back to its own price.
     */
    public function test_get_lowest_price_for_plan_with_tiers(): void
    {
        $plan = Mockery::mock();

        $plan->pricingTiers = [
            // Tier A: print effective = 80 (sale), digital explicit = 75
            (object)['price' => 100, 'sale_price' => 80, 'digital_price' => 75],
            // Tier B: print effective = 50 (no sale), digital fallback = 50
            (object)['price' => 50, 'sale_price' => null, 'digital_price' => null],
        ];

        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $result = $this->service->getLowestPriceForPlan($plan);

        // Lowest print: min(80, 50) = 50
        $this->assertSame(50, $result[SubscriptionType::PRINTED->value]);
        // Lowest digital: min(75, 50) = 50
        $this->assertSame(50, $result[SubscriptionType::DIGITAL->value]);
        // Overall lowest: 50
        $this->assertSame(50, $result['lowest']);
    }

    /**
     * When a tier carries a dedicated digital_price that is lower than its
     * print effective price, the digital lowest must reflect the digital_price
     * — not the print effective price.
     */
    public function test_get_lowest_price_prefers_dedicated_digital_price_over_print_effective(): void
    {
        $plan = Mockery::mock();

        $plan->pricingTiers = [
            // Print effective = 100, dedicated digital = 60 (cheaper than print)
            (object)['price' => 100, 'sale_price' => null, 'digital_price' => 60],
        ];

        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $result = $this->service->getLowestPriceForPlan($plan);

        $this->assertSame(100, $result[SubscriptionType::PRINTED->value]);
        $this->assertSame(60, $result[SubscriptionType::DIGITAL->value]);
        $this->assertSame(60, $result['lowest']);
    }

    /**
     * When no digital_price is set on a tier, the digital effective price falls
     * back to that tier's own sale_price ?? price — not a different tier's value.
     */
    public function test_get_lowest_price_digital_fallback_uses_tiers_own_effective_price(): void
    {
        $plan = Mockery::mock();

        $plan->pricingTiers = [
            // Tier A: sale = 40, no digital_price → digital fallback = 40
            (object)['price' => 100, 'sale_price' => 40, 'digital_price' => null],
            // Tier B: no sale, no digital_price → digital fallback = 90
            (object)['price' => 90, 'sale_price' => null, 'digital_price' => null],
        ];

        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $result = $this->service->getLowestPriceForPlan($plan);

        // Lowest print: min(40, 90) = 40
        $this->assertSame(40, $result[SubscriptionType::PRINTED->value]);
        // Lowest digital: min(40, 90) = 40 (each tier falls back to its own effective)
        $this->assertSame(40, $result[SubscriptionType::DIGITAL->value]);
        $this->assertSame(40, $result['lowest']);
    }

    /**
     * A plan with no pricing tiers should return null for all price buckets.
     * Callers are expected to fall back to the plan-level price field themselves.
     */
    public function test_get_lowest_price_returns_nulls_when_no_tiers_exist(): void
    {
        $plan = Mockery::mock();
        $plan->pricingTiers = [];

        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $result = $this->service->getLowestPriceForPlan($plan);

        $this->assertNull($result[SubscriptionType::PRINTED->value]);
        $this->assertNull($result[SubscriptionType::DIGITAL->value]);
        $this->assertNull($result['lowest']);
    }

    /**
     * A print-only plan should never populate the digital bucket.
     */
    public function test_get_lowest_price_print_only_plan(): void
    {
        $plan = Mockery::mock();

        $plan->pricingTiers = [
            (object)['price' => 30, 'sale_price' => null, 'digital_price' => null],
        ];

        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(false);

        $result = $this->service->getLowestPriceForPlan($plan);

        $this->assertSame(30, $result[SubscriptionType::PRINTED->value]);
        $this->assertNull($result[SubscriptionType::DIGITAL->value]);
        $this->assertSame(30, $result['lowest']);
    }

    /**
     * A digital-only plan should never populate the print bucket.
     */
    public function test_get_lowest_price_digital_only_plan(): void
    {
        $plan = Mockery::mock();

        $plan->pricingTiers = [
            (object)['price' => 20, 'sale_price' => null, 'digital_price' => 15],
        ];

        $plan->shouldReceive('hasPrintOption')->andReturn(false);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $result = $this->service->getLowestPriceForPlan($plan);

        $this->assertNull($result[SubscriptionType::PRINTED->value]);
        $this->assertSame(15, $result[SubscriptionType::DIGITAL->value]);
        $this->assertSame(15, $result['lowest']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the repository
        $this->repository = Mockery::mock(SubscriptionPlanRepository::class);

        // Mock the Query Builder (fluent interface)
        $this->queryMock = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $this->queryMock->shouldReceive('visibleToMember')
            ->byDefault()
            ->with(Mockery::any())
            ->andReturnSelf();

        $this->service = new SubscriptionCatalogService($this->repository);
    }
}
