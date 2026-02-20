<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionPlanPricingRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionPlanPricingRepository $repository;

    public function testGetForPlan(): void
    {
        $plan = $this->createSubscriptionPlan();

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_active' => true,
            'sort_order' => 2,
            'label' => 'Annual',
            'period_description' => 'billed yearly'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'price' => 30.00,
            'is_active' => true,
            'sort_order' => 1,
            'label' => 'Six Months',
            'period_description' => 'billed every 6 months'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 1,
            'issue_count' => 1,
            'price' => 10.00,
            'is_active' => false,
            'sort_order' => 3,
            'label' => 'Monthly',
            'period_description' => 'billed monthly'
        ]);

        $pricings = $this->repository->getForPlan($plan->id);

        $this->assertCount(2, $pricings);
        $this->assertEquals(6, $pricings->first()->duration_months); // Sort order 1
    }

    public function testGetDefaultForPlan(): void
    {
        $plan = $this->createSubscriptionPlan();

        $default = SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_default' => true,
            'is_active' => true,
            'label' => 'Default',
            'period_description' => 'billed yearly'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'price' => 30.00,
            'is_default' => false,
            'is_active' => true,
            'label' => 'Not Default',
            'period_description' => 'billed every 6 months'
        ]);

        $result = $this->repository->getDefaultForPlan($plan->id);

        $this->assertNotNull($result);
        $this->assertEquals($default->id, $result->id);
    }

    public function testSetAsDefault(): void
    {
        $plan = $this->createSubscriptionPlan();

        $pricing1 = SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_default' => true,
            'is_active' => true,
            'label' => 'Pricing 1',
            'period_description' => 'billed yearly'
        ]);

        $pricing2 = SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'price' => 30.00,
            'is_default' => false,
            'is_active' => true,
            'label' => 'Pricing 2',
            'period_description' => 'billed every 6 months'
        ]);

        $result = $this->repository->setAsDefault($pricing2->id);

        $this->assertTrue($result);
        $this->assertTrue(SubscriptionPlanPricing::find($pricing2->id)->is_default);
        $this->assertFalse(SubscriptionPlanPricing::find($pricing1->id)->is_default);
    }

    public function testToggleActive(): void
    {
        $plan = $this->createSubscriptionPlan();
        $pricing = SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_active' => true,
            'label' => 'Toggle',
            'period_description' => 'billed yearly'
        ]);

        $this->repository->toggleActive($pricing->id);
        $this->assertFalse(SubscriptionPlanPricing::find($pricing->id)->is_active);

        $this->repository->toggleActive($pricing->id);
        $this->assertTrue(SubscriptionPlanPricing::find($pricing->id)->is_active);
    }

    public function testUpdateSortOrders(): void
    {
        $plan = $this->createSubscriptionPlan();
        $p1 = SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'price' => 10,
            'sort_order' => 1,
            'label' => 'P1',
            'duration_months' => 1,
            'issue_count' => 1,
            'period_description' => 'desc'
        ]);
        $p2 = SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'price' => 20,
            'sort_order' => 2,
            'label' => 'P2',
            'duration_months' => 2,
            'issue_count' => 2,
            'period_description' => 'desc'
        ]);

        $result = $this->repository->updateSortOrders([
            $p1->id => 10,
            $p2->id => 5
        ]);

        $this->assertTrue($result);
        $this->assertEquals(10, SubscriptionPlanPricing::find($p1->id)->sort_order);
        $this->assertEquals(5, SubscriptionPlanPricing::find($p2->id)->sort_order);
    }

    public function testSearchPricingTiersPaginatedWithPlanFilter(): void
    {
        $plan1 = $this->createSubscriptionPlan();
        $plan2 = $this->createSubscriptionPlan();

        SubscriptionPlanPricing::create([
            'plan_id' => $plan1->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_active' => true,
            'sort_order' => 1,
            'label' => 'Annual Plan 1',
            'period_description' => 'billed yearly'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan2->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 60.00,
            'is_active' => true,
            'sort_order' => 1,
            'label' => 'Annual Plan 2',
            'period_description' => 'billed yearly'
        ]);

        $result = $this->repository->searchPricingTiersPaginated([
            'plan_id' => $plan1->id
        ]);

        $this->assertEquals(1, $result['pagination']['total']);
        $this->assertEquals($plan1->id, $result['data']->first()->plan_id);
    }

    public function testSearchPricingTiersPaginatedWithStatusFilter(): void
    {
        $plan = $this->createSubscriptionPlan();

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_active' => true,
            'sort_order' => 1,
            'label' => 'Active',
            'period_description' => 'billed yearly'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'price' => 30.00,
            'is_active' => false,
            'sort_order' => 2,
            'label' => 'Inactive',
            'period_description' => 'billed every 6 months'
        ]);

        $result = $this->repository->searchPricingTiersPaginated([
            'plan_id' => $plan->id,
            'status' => 'active'
        ]);

        $this->assertEquals(1, $result['pagination']['total']);
        $this->assertTrue($result['data']->first()->is_active);
    }

    public function testSearchPricingTiersPaginatedWithSearchText(): void
    {
        $plan = $this->createSubscriptionPlan();

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_active' => true,
            'sort_order' => 1,
            'label' => 'Premium Annual',
            'period_description' => 'billed yearly'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'price' => 30.00,
            'is_active' => true,
            'sort_order' => 2,
            'label' => 'Standard Six Months',
            'period_description' => 'billed every 6 months'
        ]);

        $result = $this->repository->searchPricingTiersPaginated([
            'plan_id' => $plan->id,
            'search' => 'Premium'
        ]);

        $this->assertEquals(1, $result['pagination']['total']);
        $this->assertStringContainsString('Premium', $result['data']->first()->label);
    }

    public function testSearchPricingTiersPaginatedWithPagination(): void
    {
        $plan = $this->createSubscriptionPlan();

        for ($i = 1; $i <= 20; $i++) {
            SubscriptionPlanPricing::create([
                'plan_id' => $plan->id,
                'duration_months' => $i,
                'issue_count' => $i,
                'price' => 10.00 * $i,
                'is_active' => true,
                'sort_order' => $i,
                'label' => "Plan {$i}",
                'period_description' => "description {$i}"
            ]);
        }

        $result = $this->repository->searchPricingTiersPaginated([
            'plan_id' => $plan->id,
            'per_page' => 10,
            'page' => 1
        ]);

        $this->assertEquals(20, $result['pagination']['total']);
        $this->assertEquals(10, $result['data']->count());
        $this->assertEquals(2, $result['pagination']['total_pages']);
        $this->assertTrue($result['pagination']['has_more']);

        $result2 = $this->repository->searchPricingTiersPaginated([
            'plan_id' => $plan->id,
            'per_page' => 10,
            'page' => 2
        ]);

        $this->assertEquals(10, $result2['data']->count());
        $this->assertFalse($result2['pagination']['has_more']);
    }

    public function testSearchPricingTiersPaginatedOrderedBySortOrder(): void
    {
        $plan = $this->createSubscriptionPlan();

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 50.00,
            'is_active' => true,
            'sort_order' => 3,
            'label' => 'Third',
            'period_description' => 'billed yearly'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'price' => 30.00,
            'is_active' => true,
            'sort_order' => 1,
            'label' => 'First',
            'period_description' => 'billed every 6 months'
        ]);

        SubscriptionPlanPricing::create([
            'plan_id' => $plan->id,
            'duration_months' => 1,
            'issue_count' => 1,
            'price' => 10.00,
            'is_active' => true,
            'sort_order' => 2,
            'label' => 'Second',
            'period_description' => 'billed monthly'
        ]);

        $result = $this->repository->searchPricingTiersPaginated([
            'plan_id' => $plan->id
        ]);

        $data = $result['data']->toArray();
        $this->assertEquals('First', $data[0]['label']);
        $this->assertEquals('Second', $data[1]['label']);
        $this->assertEquals('Third', $data[2]['label']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionPlanPricingRepository();
    }
}
