<?php

namespace App\Tests\Unit\Repositories;

use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SubscriptionPlanRepositoryTest extends FunctionalTestCase
{
    private SubscriptionPlanRepository $repository;

    public function testGetActivePlans(): void
    {
        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Active Plan',
            'slug' => 'active',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Inactive Plan',
            'slug' => 'inactive',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => false
        ]);

        $plans = $this->repository->getActivePlans($this->siteId);

        $this->assertEquals(1, $plans->count());
        $this->assertEquals('Active Plan', $plans->first()->name);
    }

    public function testFindBySlug(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium',
            'slug' => 'premium-plan',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ]);

        $found = $this->repository->findBySlug('premium-plan', $this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals($plan->id, $found->id);
        $this->assertEquals('Premium', $found->name);
    }

    public function testToggleActive(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Test Plan',
            'slug' => 'test',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        $this->assertTrue($plan->is_active);

        $this->repository->toggleActive($plan->id);
        $plan = $this->repository->find($plan->id);

        $this->assertFalse($plan->is_active);
    }

    public function testToggleFeatured(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Test Plan',
            'slug' => 'test',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_featured' => false
        ]);

        $this->assertFalse($plan->is_featured);

        $this->repository->toggleFeatured($plan->id);
        $plan = $this->repository->find($plan->id);

        $this->assertTrue($plan->is_featured);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionPlanRepository();
    }
}