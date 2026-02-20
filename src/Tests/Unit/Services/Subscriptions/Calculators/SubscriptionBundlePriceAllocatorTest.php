<?php

namespace App\Tests\Unit\Services\Subscriptions\Calculators;

use App\Models\SubscriptionBundle;
use App\Models\SubscriptionBundleItem;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Calculators\SubscriptionBundlePriceAllocator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SubscriptionBundlePriceAllocatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionBundlePriceAllocator $allocator;

    public function test_allocates_bundle_price_proportionally_across_two_equal_plans(): void
    {
        $bundle = $this->makeBundle(20.00, [
            [101, 10.00],
            [102, 10.00],
        ]);

        $result = $this->allocator->allocate($bundle);

        $this->assertCount(2, $result);
        $this->assertEquals(10.00, $result[101]);
        $this->assertEquals(10.00, $result[102]);
        $this->assertSum($bundle->bundle_price, $result);
    }

    /**
     * Build a mock SubscriptionBundle with given item plan prices.
     *
     * @param float $bundlePrice
     * @param array $items [ [planId, listPrice], ... ]
     */
    private function makeBundle(float $bundlePrice, array $items): SubscriptionBundle
    {
        $bundleItems = collect();

        foreach ($items as [$planId, $listPrice]) {
            $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
            $plan->id = $planId;
            $plan->price = $listPrice;

            $item = Mockery::mock(SubscriptionBundleItem::class)->makePartial();
            $item->subscription_plan_id = $planId;
            $item->shouldReceive('getAttribute')
                ->with('subscriptionPlan')
                ->andReturn($plan);
            $item->subscriptionPlan = $plan;

            $bundleItems->push($item);
        }

        $bundle = Mockery::mock(SubscriptionBundle::class)->makePartial();
        $bundle->bundle_price = $bundlePrice;
        $bundle->shouldReceive('getAttribute')->with('items')->andReturn($bundleItems);
        $bundle->items = $bundleItems;

        return $bundle;
    }

    private function assertSum(float $expected, array $allocated): void
    {
        $sum = array_sum($allocated);
        $this->assertEquals(
            $expected,
            round($sum, 2),
            "Allocated prices ({$sum}) do not sum to expected bundle_price ({$expected})"
        );
    }

    public function test_allocates_proportionally_with_unequal_plan_prices(): void
    {
        // Plan A: £60 (75%), Plan B: £20 (25%), bundle_price: £60
        $bundle = $this->makeBundle(60.00, [
            [101, 60.00],
            [102, 20.00],
        ]);

        $result = $this->allocator->allocate($bundle);

        // 75% of 60 = 45, 25% of 60 = 15
        $this->assertEquals(45.00, $result[101]);
        $this->assertEquals(15.00, $result[102]);
        $this->assertSum($bundle->bundle_price, $result);
    }

    public function test_remainder_assigned_to_last_plan_guarantees_exact_sum(): void
    {
        // Three plans: prices 10, 10, 10 = 30. bundle_price = 25 (divisor does not divide evenly in cents)
        $bundle = $this->makeBundle(25.00, [
            [101, 10.00],
            [102, 10.00],
            [103, 10.00],
        ]);

        $result = $this->allocator->allocate($bundle);

        $this->assertCount(3, $result);
        // Each plan is 1/3 of bundle_price = 8.33... — we floor for the first two, remainder to last
        $this->assertEquals(8.33, $result[101]);
        $this->assertEquals(8.33, $result[102]);
        $this->assertEquals(8.34, $result[103]); // gets the remainder
        $this->assertSum($bundle->bundle_price, $result);
    }

    public function test_sum_of_allocated_prices_equals_bundle_price_exactly(): void
    {
        // Use a bundle_price that is not cleanly divisible
        $bundle = $this->makeBundle(99.99, [
            [101, 30.00],
            [102, 70.00],
        ]);

        $result = $this->allocator->allocate($bundle);

        $this->assertSum(99.99, $result);
    }

    public function test_single_plan_gets_full_bundle_price(): void
    {
        $bundle = $this->makeBundle(49.99, [
            [101, 60.00],
        ]);

        $result = $this->allocator->allocate($bundle);

        $this->assertCount(1, $result);
        $this->assertEquals(49.99, $result[101]);
    }

    public function test_throws_when_all_plan_prices_are_zero(): void
    {
        $bundle = $this->makeBundle(50.00, [
            [101, 0.00],
            [102, 0.00],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('zero list price');

        $this->allocator->allocate($bundle);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function test_returns_empty_array_for_bundle_with_no_items(): void
    {
        $bundle = Mockery::mock(SubscriptionBundle::class)->makePartial();
        $bundle->bundle_price = 50.00;
        $bundle->items = collect([]);

        $result = $this->allocator->allocate($bundle);

        $this->assertEmpty($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocator = new SubscriptionBundlePriceAllocator();
    }
}