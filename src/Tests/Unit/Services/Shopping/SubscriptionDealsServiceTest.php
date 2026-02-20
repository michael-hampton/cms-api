<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Models\SubscriptionBundle;
use App\Models\SubscriptionBundleItem;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionBundleRepository;
use App\Services\Shopping\SubscriptionCatalogService;
use App\Services\Shopping\SubscriptionDealsService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SubscriptionDealsServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $catalogService;
    private $bundleRepository;
    private SubscriptionDealsService $service;

    public function test_get_deals_always_sets_special_filter_to_on_sale(): void
    {
        $this->catalogService->shouldReceive('getCatalog')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return $filters['special_filter'] === 'on_sale';
            }))
            ->andReturn(['data' => [], 'total' => 0]);

        $this->service->getDeals([]);
    }

    // -----------------------------------------------------------------------
    // getDeals
    // -----------------------------------------------------------------------

    public function test_get_deals_passes_through_other_filters(): void
    {
        $inputFilters = [
            'site_id' => 5,
            'per_page' => 20,
            'page' => 2,
            'sort' => 'price_asc',
        ];

        $this->catalogService->shouldReceive('getCatalog')
            ->once()
            ->with(Mockery::on(function ($filters) use ($inputFilters) {
                return $filters['site_id'] === 5
                    && $filters['per_page'] === 20
                    && $filters['page'] === 2
                    && $filters['sort'] === 'price_asc'
                    && $filters['special_filter'] === 'on_sale';
            }))
            ->andReturn([]);

        $this->service->getDeals($inputFilters);
    }

    public function test_get_deals_overrides_caller_supplied_special_filter(): void
    {
        // Even if a caller tries to pass a different special_filter, on_sale wins.
        $this->catalogService->shouldReceive('getCatalog')
            ->once()
            ->with(Mockery::on(fn($f) => $f['special_filter'] === 'on_sale'))
            ->andReturn([]);

        $this->service->getDeals(['special_filter' => 'limited_offer']);
    }

    public function test_get_deals_returns_catalog_result(): void
    {
        $expected = ['data' => [['id' => 1, 'name' => 'Plan A']], 'total' => 1];

        $this->catalogService->shouldReceive('getCatalog')
            ->once()
            ->andReturn($expected);

        $result = $this->service->getDeals();

        $this->assertEquals($expected, $result);
    }

    public function test_get_active_bundles_returns_formatted_bundles(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 10;
        $plan->name = 'Weekly Digest';

        $item = Mockery::mock(SubscriptionBundleItem::class)->makePartial();
        $item->delivery_type = 'digital';
        $item->quantity = 1;
        $item->subscriptionPlan = $plan;
        $item->subscription_plan_id = 10;

        $bundle = Mockery::mock(SubscriptionBundle::class)->makePartial();
        $bundle->id = 1;
        $bundle->name = 'Combo Deal';
        $bundle->slug = 'combo-deal';
        $bundle->description = 'Two for less';
        $bundle->bundle_price = 40.00;
        $bundle->total_price = 50.00;
        $bundle->items = collect([$item]);
        $bundle->shouldReceive('getSavingsAmount')->andReturn(10.00);
        $bundle->shouldReceive('getDiscountPercentage')->andReturn(20);

        $this->bundleRepository->shouldReceive('getActiveBundles')
            ->with(1)
            ->once()
            ->andReturn(collect([$bundle]));

        $result = $this->service->getActiveBundles(1);

        $this->assertCount(1, $result);
        $this->assertEquals('Combo Deal', $result[0]['name']);
        $this->assertEquals(40.00, $result[0]['bundle_price']);
        $this->assertEquals(50.00, $result[0]['total_price']);
        $this->assertEquals(10.00, $result[0]['savings_amount']);
        $this->assertEquals(20, $result[0]['discount_percentage']);
        $this->assertCount(1, $result[0]['plans']);
        $this->assertEquals('Weekly Digest', $result[0]['plans'][0]['name']);
    }

    // -----------------------------------------------------------------------
    // getActiveBundles
    // -----------------------------------------------------------------------

    public function test_get_active_bundles_returns_empty_when_none_active(): void
    {
        $this->bundleRepository->shouldReceive('getActiveBundles')
            ->with(null)
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->getActiveBundles();

        $this->assertEmpty($result);
    }

    public function test_get_active_bundles_passes_site_id_to_repository(): void
    {
        $this->bundleRepository->shouldReceive('getActiveBundles')
            ->with(42)
            ->once()
            ->andReturn(collect([]));

        $this->service->getActiveBundles(42);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogService = Mockery::mock(SubscriptionCatalogService::class);
        $this->bundleRepository = Mockery::mock(SubscriptionBundleRepository::class);

        $this->service = new SubscriptionDealsService(
            $this->catalogService,
            $this->bundleRepository
        );
    }
}