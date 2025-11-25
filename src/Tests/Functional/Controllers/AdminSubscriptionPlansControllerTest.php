<?php

namespace App\Tests\Functional\Controllers;

use App\Models\SubscriptionPlan;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdminSubscriptionPlansControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexDisplaysPlans(): void
    {
        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        $response = $this->getForSite('/admin/subscription-plans');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Subscription Plans', $content);
        $this->assertStringContainsString('Premium', $content);
    }

    public function testCreateDisplaysForm(): void
    {
        $response = $this->getForSite('/admin/subscription-plans/create');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Create Subscription Plan', $content);
    }

    public function testStoreCreatesNewPlan(): void
    {
        $data = [
            'name' => 'New Plan',
            'slug' => 'new-plan',
            'description' => 'Test description',
            'price' => 19.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 14,
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'is_featured' => false
        ];

        $response = $this->postForSite('/admin/subscription-plans', $data);

        $this->assertEquals(302, $response->getStatusCode());

        $plan = SubscriptionPlan::where('slug', 'new-plan')->first();
        $this->assertNotNull($plan);
        $this->assertEquals('New Plan', $plan->name);
        $this->assertEquals(19.99, $plan->price);
    }

    public function testUpdateModifiesPlan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Original Name',
            'slug' => 'original',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ]);

        $response = $this->putForSite("/admin/subscription-plans/{$plan->id}", [
            '_method' => 'PUT',
            'name' => 'Updated Name',
            'price' => 15.00
        ]);

        $this->assertEquals(302, $response->getStatusCode());

        $updated = SubscriptionPlan::find($plan->id);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals(15.00, $updated->price);
    }

    public function testToggleActiveChangesStatus(): void
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

        $response = $this->postForSite(
            "/admin/subscription-plans/{$plan->id}/toggle-active",
            [],
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(200, $response->getStatusCode());

        $updated = SubscriptionPlan::find($plan->id);
        $this->assertFalse($updated->is_active);
    }
}