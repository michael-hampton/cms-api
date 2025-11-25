<?php

namespace App\Tests\Unit\Models;

use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SubscriptionPlanTest extends FunctionalTestCase
{
    public function testCreateSubscriptionPlan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'description' => 'Premium features',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 7,
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1
        ]);

        $this->assertNotNull($plan->id);
        $this->assertEquals('Premium Plan', $plan->name);
        $this->assertEquals(29.99, $plan->price);
        $this->assertTrue($plan->is_active);
        $this->assertIsArray($plan->features);
    }

    public function testIsActiveMethod(): void
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

        $this->assertTrue($plan->isActive());
        $plan->update(['is_active' => false]);
        $this->assertFalse($plan->isActive());
    }

    public function testHasTrialMethod(): void
    {
        $planWithTrial = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Trial Plan',
            'slug' => 'trial',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 14
        ]);
        $planWithoutTrial = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'No Trial',
            'slug' => 'no-trial',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 0
        ]);
        $this->assertTrue($planWithTrial->hasTrial());
        $this->assertFalse($planWithoutTrial->hasTrial());
    }

    public function testGetFormattedPrice(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Test Plan',
            'slug' => 'test',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ]);
        $this->assertEquals('USD 29.99', $plan->getFormattedPrice());
    }

    public function testGetBillingPeriodLabel(): void
    {
        $monthlyPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Monthly',
            'slug' => 'monthly',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ]);
        $lifetimePlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Lifetime',
            'slug' => 'lifetime',
            'price' => 99.00,
            'currency' => 'USD',
            'billing_period' => 'lifetime'
        ]);

        $this->assertEquals('per month', $monthlyPlan->getBillingPeriodLabel());
        $this->assertEquals('one-time', $lifetimePlan->getBillingPeriodLabel());
    }

}