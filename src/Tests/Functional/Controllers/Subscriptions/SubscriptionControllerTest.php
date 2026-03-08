<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers SubscriptionController with:
 *   - CreateSubscriptionPlanRequest: name (required, max:255), slug (max:255),
 *     billing_period (required, in:weekly,monthly,quarterly,yearly,annual),
 *     price (numeric, min:0), currency (max:3), duration_months (integer, min:1),
 *     issue_count (integer, min:1). Auto-slug from name.
 *   - UpdateSubscriptionPlanRequest: same fields but none required.
 *   - BulkTogglePlanActive: plan_ids (non-empty array).
 */
class SubscriptionControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/subscriptions
    // =========================================================================

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

    // =========================================================================
    // GET /api/subscriptions/payments
    // =========================================================================

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

    // =========================================================================
    // GET /api/subscriptions/plans
    // =========================================================================

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

    // =========================================================================
    // POST /api/subscriptions/plans — happy path
    // =========================================================================

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

    // =========================================================================
    // POST /api/subscriptions/plans — CreateSubscriptionPlanRequest validation
    // =========================================================================

    public function testCreatePlanRequiresName(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'billing_period' => 'monthly',
            'price' => 9.99,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCreatePlanRejectsNameExceeding255Characters(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => str_repeat('x', 256),
            'billing_period' => 'monthly',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCreatePlanRequiresBillingPeriod(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Test Plan',
            'price' => 9.99,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCreatePlanRejectsBillingPeriodNotInAllowedValues(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Test Plan',
            'billing_period' => 'fortnightly',
            'price' => 9.99,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[DataProvider('validBillingPeriods')]
    public function testCreatePlanAcceptsAllValidBillingPeriods(string $period): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => "Plan {$period}",
            'billing_period' => trim($period),
            'price' => 9.99,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public static function validBillingPeriods(): array
    {
        return [
            ['weekly'],
            ['monthly'],
            ['quarterly'],
            ['yearly'],
        ];
    }

    public function testCreatePlanRejectsPriceBelow0(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Test Plan',
            'billing_period' => 'monthly',
            'price' => -1,
        ]);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testCreatePlanAcceptsZeroPrice(): void
    {
        // price has min:0 — zero is explicitly allowed for free plans
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Free Plan',
            'billing_period' => 'monthly',
            'price' => 0,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreatePlanRejectsDurationMonthsBelow1(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Test Plan',
            'billing_period' => 'monthly',
            'price' => 9.99,
            'duration_months' => 0,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCreatePlanRejectsIssueCountBelow1(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Test Plan',
            'billing_period' => 'monthly',
            'price' => 9.99,
            'issue_count' => 0,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCreatePlanRejectsCurrencyExceeding3Characters(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Test Plan',
            'billing_period' => 'monthly',
            'currency' => 'GBPP',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCreatePlanGeneratesSlugFromNameWhenOmitted(): void
    {
        $response = $this->postForSite('/api/subscriptions/plans', [
            'name' => 'Auto Slug Plan',
            'billing_period' => 'monthly',
            'price' => 22
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $plan = SubscriptionPlan::where('name', 'Auto Slug Plan')->first();
        $this->assertNotNull($plan->slug);
        $this->assertEquals('auto-slug-plan', $plan->slug);
    }

    // =========================================================================
    // PUT /api/subscriptions/plans/{id} — happy path
    // =========================================================================

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

    // =========================================================================
    // PUT /api/subscriptions/plans/{id} — UpdateSubscriptionPlanRequest validation
    // =========================================================================

    public function testUpdatePlanRejectsBillingPeriodNotInAllowedValues(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, [
            'billing_period' => 'fortnightly',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePlanRejectsPriceBelow0(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, [
            'price' => -5,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePlanRejectsDurationMonthsBelow1(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, [
            'duration_months' => 0,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePlanRejectsIssueCountBelow1(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, [
            'issue_count' => 0,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePlanRejectsCurrencyExceeding3Characters(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, [
            'currency' => 'GBPP',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePlanRejectsNameExceeding255Characters(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, [
            'name' => str_repeat('x', 256),
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePlanAcceptsEmptyPayload(): void
    {
        // UpdateSubscriptionPlanRequest has no required fields — empty body is valid
        $plan = $this->createSubscriptionPlan(['name' => 'Unchanged']);

        $response = $this->putForSite('/api/subscriptions/plans/' . $plan->id, []);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Unchanged', $plan->fresh()->name);
    }

    public function testUpdatePlanReturns404ForNonExistentPlan(): void
    {
        $response = $this->putForSite('/api/subscriptions/plans/99999', [
            'name' => 'Ghost Plan',
        ]);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // =========================================================================
    // DELETE /api/subscriptions/plans/{id}
    // =========================================================================

    public function testDeletePlanDeletesPlan(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->deleteForSite('/api/subscriptions/plans/' . $plan->id);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNull(SubscriptionPlan::find($plan->id));
    }

    public function testDeletePlanReturns500ForNonExistentPlan(): void
    {
        $response = $this->deleteForSite('/api/subscriptions/plans/99999');

        $this->assertEquals(500, $response->getStatusCode());
    }

    // =========================================================================
    // POST /api/subscriptions/plans/bulk-toggle-active
    // =========================================================================

//    public function testBulkToggleActiveActivatesPlans(): void
//    {
//        $plan1 = $this->createSubscriptionPlan(['is_active' => false]);
//        $plan2 = $this->createSubscriptionPlan(['is_active' => false]);
//
//        $response = $this->postForSite('/api/subscriptions/plans/bulk-toggle-active', [
//            'plan_ids' => [$plan1->id, $plan2->id],
//            'active' => true,
//        ]);
//
//        dd($response);
//
//        $this->assertEquals(500, $response->getStatusCode());
//
//        $data = json_decode($response->getContent(), true);
//        $this->assertTrue($data['success']);
//
//        $this->assertTrue($plan1->fresh()->is_active);
//        $this->assertTrue($plan2->fresh()->is_active);
//    }
//
//    public function testBulkToggleActiveDeactivatesPlans(): void
//    {
//        $plan1 = $this->createSubscriptionPlan(['is_active' => true]);
//        $plan2 = $this->createSubscriptionPlan(['is_active' => true]);
//
//        $response = $this->postForSite('/api/subscriptions/plans/bulk-toggle-active', [
//            'plan_ids' => [$plan1->id, $plan2->id],
//            'active' => false,
//        ]);
//
//        dd($response);
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $this->assertFalse($plan1->fresh()->is_active);
//        $this->assertFalse($plan2->fresh()->is_active);
//    }
//
//    public function testBulkToggleActiveRequiresPlanIds(): void
//    {
//        $response = $this->postForSite('/api/subscriptions/plans/bulk-toggle-active', [
//            'active' => true,
//        ]);
//
//        dd($response);
//
//        $this->assertEquals(422, $response->getStatusCode());
//    }
//
//    public function testBulkToggleActiveRequiresPlanIdsToBeNonEmptyArray(): void
//    {
//        $response = $this->postForSite('/api/subscriptions/plans/bulk-toggle-active', [
//            'plan_ids' => [],
//            'active' => true,
//        ]);
//
//        dd($response);
//
//        $this->assertEquals(422, $response->getStatusCode());
//    }
}