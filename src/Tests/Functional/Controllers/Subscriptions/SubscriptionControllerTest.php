<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsSubscriptionsList(): void
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createSubscription([
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
        ]);

        $response = $this->getForSite('/api/subscriptions');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertNotEmpty($data['data']);

        $first = $data['data'][0];
        $this->assertEquals($subscription->id, $first['id']);
        $this->assertEquals($subscription->member_id, $first['member_id']);
    }

    public function testPaymentsReturnsSubscriptionPayments(): void
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createSubscription([
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'status' => 'completed',
            'amount' => 10.00,
            'currency' => 'GBP',
            'transaction_id' => 'sub_txn_1',
            'payment_intent_id' => 'pi_123',
            'paid_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->getForSite('/api/subscriptions/payments');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('payments', $data);
        $this->assertNotEmpty($data['payments']);
        $this->assertEquals($subscription->id, $data['payments'][0]['subscription_id']);
    }

    public function testPlansReturnsActivePlans(): void
    {
        $plan = $this->createSubscriptionPlan([
            'is_active' => true,
            'name' => 'Active Plan',
        ]);

        $this->createSubscriptionPlan([
            'is_active' => false,
            'name' => 'Inactive Plan',
        ]);

        $response = $this->getForSite('/api/subscriptions/plans');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('plans', $data);
        $this->assertNotEmpty($data['plans']);

        $planNames = array_column($data['plans'], 'name');
        $this->assertContains('Active Plan', $planNames);
    }

    public function testCreatePlanCreatesPlanWithAllFields(): void
    {
        $payload = [
            'name' => 'Controller Created Plan',
            'slug' => 'controller-created-plan',
            'description' => 'Created via controller test',
            'price' => 19.99,
            'currency' => 'GBP',
            'billing_period' => 'monthly',
            'trial_days' => 7,
            'features' => ['Feature A', 'Feature B'],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 5,
            'digital_download_url' => 'https://example.com/plan-download',
            'print_shipping_required' => true,
            'includes_insider' => true,
            'is_upgrade_option' => true,
            'upgrade_from_plan_id' => null,
            'dispatch_days' => 3,
            'release_date' => '2025-01-01 10:00:00',
            'pre_release_enabled' => true,
            'categories' => ['magazine'],
            'tags' => ['monthly', 'gift'],
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider'],
            ],
        ];

        $response = $this->postForSite('/api/subscriptions/plans', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('plan', $data['data']);

        /** @var SubscriptionPlan $plan */
        $plan = SubscriptionPlan::where('name', 'Controller Created Plan')->first();
        $this->assertNotNull($plan);
        $this->assertEquals('https://example.com/plan-download', $plan->digital_download_url);
        $this->assertTrue($plan->print_shipping_required);
        $this->assertTrue($plan->is_upgrade_option);
        $this->assertEquals(3, $plan->dispatch_days);
        $this->assertTrue($plan->pre_release_enabled);
        $this->assertIsArray($plan->premium_access);
        $this->assertEquals('insider', $plan->premium_access[0]['identifier']);
    }

    public function testUpdatePlanUpdatesFields(): void
    {
        $plan = $this->createSubscriptionPlan([
            'is_active' => true,
            'digital_download_url' => null,
            'print_shipping_required' => false,
            'is_upgrade_option' => false,
            'dispatch_days' => 0,
            'pre_release_enabled' => false,
            'premium_access' => [],
        ]);

        $payload = [
            'name' => 'Updated Plan Name',
            'digital_download_url' => 'https://example.com/updated-download',
            'print_shipping_required' => true,
            'is_upgrade_option' => true,
            'dispatch_days' => 5,
            'pre_release_enabled' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider'],
                ['type' => 'newsletter', 'identifier' => 'full'],
            ],
        ];

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, $payload);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $plan = $plan->fresh();
        $this->assertEquals('Updated Plan Name', $plan->name);
        $this->assertEquals('https://example.com/updated-download', $plan->digital_download_url);
        $this->assertTrue($plan->print_shipping_required);
        $this->assertTrue($plan->is_upgrade_option);
        $this->assertEquals(5, $plan->dispatch_days);
        $this->assertTrue($plan->pre_release_enabled);
        $this->assertCount(2, $plan->premium_access);
    }

    public function testDeletePlanDeletesPlan(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->deleteForSite('/api/subscriptions/plans/' . $plan->id);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNull(SubscriptionPlan::find($plan->id));
    }
}

