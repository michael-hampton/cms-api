<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Member;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionRepository $repository;
    private Member $testMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionRepository();
        $this->testMember = $this->createMember();
    }

    public function test_get_active_subscription_for_member_returns_active(): void
    {
        // Arrange
        $active = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $expired = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => 'expired',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getActiveSubscriptionForMember($this->testMember->id, $this->siteId);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($active->id, $result->id);
        $this->assertEquals(SubscriptionStatus::ACTIVE->value, $result->status);
    }

    public function test_get_active_subscription_returns_null_when_none_active(): void
    {
        // Arrange
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Expired Plan',
            'status' => 'expired',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getActiveSubscriptionForMember($this->testMember->id, $this->siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_get_subscription_history_returns_all_subscriptions(): void
    {
        // Arrange
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s', strtotime('-6 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-3 months')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getSubscriptionHistory($this->testMember->id, $this->siteId);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_subscription_history_ordered_by_created_at_desc(): void
    {
        // Arrange
        $old = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Old Plan',
            'status' => 'expired',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        $new = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'New Plan',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getSubscriptionHistory($this->testMember->id, $this->siteId);

        // Assert
        $this->assertEquals($new->id, $result->last()->id);
    }

    public function test_count_active_subscriptions_returns_correct_count(): void
    {
        // Arrange
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $count = $this->repository->countActiveSubscriptions($this->testMember->id, $this->siteId);

        // Assert
        $this->assertEquals(1, $count);
    }

    public function test_cancel_subscription_updates_status_and_auto_renew(): void
    {
        // Arrange
        $subscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'auto_renew' => true,
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->cancelSubscription($subscription->id);

        // Assert
        $this->assertTrue($result);

        $updated = Subscription::find($subscription->id);
        $this->assertEquals('cancelled', $updated->status);
        $this->assertFalse($updated->auto_renew);
    }

    public function test_cancel_subscription_returns_false_for_nonexistent(): void
    {
        // Act
        $result = $this->repository->cancelSubscription(99999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_subscriptions_filtered_by_site_id(): void
    {
        // Arrange
        $otherSite = $this->createSite();

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Site 1 Plan',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $otherSite->id,
            'plan_name' => 'Site 2 Plan',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getActiveSubscriptionForMember($this->testMember->id, $this->siteId);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Site 1 Plan', $result->plan_name);
    }

    public function test_find_print_subscriptions_for_issue_delivery_returns_matching(): void
    {
        $plan = $this->createSubscriptionPlan();

        $print = $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);

        // Digital subscription for the same plan — must be excluded.
        $this->subscription($this->createMember()->id, [
            'plan_id' => $plan->id,
            'delivery_type' => 'digital',
        ]);

        $referenceDate = new \DateTime();
        $results = $this->repository->findPrintSubscriptionsForIssueDelivery(1, $plan->id, $referenceDate);

        $this->assertCount(1, $results);
        $this->assertEquals($print->id, $results->first()->id);
    }

    public function test_find_print_subscriptions_excludes_inactive(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'delivery_type' => SubscriptionType::PRINTED->value,
            'status' => 'cancelled',
        ]);

        $referenceDate = new \DateTime();
        $results = $this->repository->findPrintSubscriptionsForIssueDelivery(1, $plan->id, $referenceDate);

        $this->assertCount(0, $results);
    }

    public function test_find_print_subscriptions_excludes_expired_by_end_date(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'delivery_type' => SubscriptionType::PRINTED->value,
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $referenceDate = new \DateTime();
        $results = $this->repository->findPrintSubscriptionsForIssueDelivery(1, $plan->id, $referenceDate);

        $this->assertCount(0, $results);
    }

    public function test_find_print_subscriptions_excludes_future_start_date(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'delivery_type' => SubscriptionType::PRINTED->value,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $referenceDate = new \DateTime();
        $results = $this->repository->findPrintSubscriptionsForIssueDelivery(1, $plan->id, $referenceDate);

        $this->assertCount(0, $results);
    }

    public function test_has_print_subscriptions_for_plan_returns_true_when_exists(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);

        $result = $this->repository->hasPrintSubscriptionsForPlan($plan->id);

        $this->assertTrue($result);
    }

    public function test_has_print_subscriptions_for_plan_returns_false_when_none(): void
    {
        $plan = $this->createSubscriptionPlan();
        $result = $this->repository->hasPrintSubscriptionsForPlan($plan->id);

        $this->assertFalse($result);
    }

    public function test_has_print_subscriptions_for_plan_ignores_digital_subscriptions(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'delivery_type' => 'digital',
        ]);

        $result = $this->repository->hasPrintSubscriptionsForPlan($plan->id);

        $this->assertFalse($result);
    }

    public function test_find_active_by_plan_and_date_returns_matching(): void
    {
        $plan = $this->createSubscriptionPlan();
        $active = $this->subscription($this->testMember->id, ['plan_id' => $plan->id]);

        $results = $this->repository->findActiveByPlanAndDate($plan->id, new \DateTime());

        $this->assertCount(1, $results);
        $this->assertEquals($active->id, $results->first()->id);
    }

    public function test_find_active_by_plan_and_date_excludes_cancelled(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $results = $this->repository->findActiveByPlanAndDate($plan->id, new \DateTime());

        $this->assertCount(0, $results);
    }

    public function test_find_active_by_plan_and_date_excludes_past_end_date(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, [
            'plan_id' => $plan->id,
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $results = $this->repository->findActiveByPlanAndDate($plan->id, new \DateTime());

        $this->assertCount(0, $results);
    }

    public function test_get_subscribers_for_plan_returns_paginated_results(): void
    {
        $plan = $this->createSubscriptionPlan();

        for ($i = 0; $i < 5; $i++) {
            $this->subscription($this->createMember()->id, ['plan_id' => $plan->id]);
        }

        $result = $this->repository->getSubscribersForPlan($plan->id, 1, 3);

        $this->assertEquals(5, $result['total']);
        $this->assertCount(3, $result['items']);
        $this->assertEquals(2, $result['last_page']);
        $this->assertEquals(1, $result['current_page']);
    }

    public function test_get_subscribers_for_plan_filters_by_status(): void
    {
        $plan = $this->createSubscriptionPlan();

        $this->subscription($this->testMember->id, ['plan_id' => $plan->id, 'status' => SubscriptionStatus::ACTIVE->value]);
        $this->subscription($this->createMember()->id, ['plan_id' => $plan->id, 'status' => 'cancelled']);

        $result = $this->repository->getSubscribersForPlan($plan->id, 1, 25, SubscriptionStatus::ACTIVE->value);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(SubscriptionStatus::ACTIVE->value, $result['items']->first()->status);
    }

    public function test_get_subscriptions_due_for_renewal(): void
    {
        // Create subscription due for renewal
        $dueSubscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'auto_renew' => true,
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Create subscription not due yet
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'auto_renew' => true,
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        $result = $this->repository->getSubscriptionsDueForRenewal($this->siteId);

        $this->assertGreaterThanOrEqual(1, $result->count());
        $this->assertTrue($result->contains('id', $dueSubscription->id));
    }

    public function test_update_next_billing_date(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $newBillingDate = new \DateTime('+2 months');
        $result = $this->repository->updateNextBillingDate($subscription->id, $newBillingDate);

        $this->assertTrue($result);

        $updated = Subscription::find($subscription->id);
        $this->assertEquals(
            $newBillingDate->format('Y-m-d'),
            $updated->next_billing_date->format('Y-m-d')
        );
    }

    public function test_update_last_payment_date(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $paymentDate = new \DateTime();
        $result = $this->repository->updateLastPaymentDate($subscription->id, $paymentDate);

        $this->assertTrue($result);

        $updated = Subscription::find($subscription->id);
        $this->assertNotNull($updated->last_payment_date);
        $this->assertEquals(
            $paymentDate->format('Y-m-d'),
            $updated->last_payment_date->format('Y-m-d')
        );
    }

    public function test_mark_as_past_due(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $result = $this->repository->markAsPastDue($subscription->id);

        $this->assertTrue($result);

        $updated = Subscription::find($subscription->id);
        $this->assertEquals('past_due', $updated->status);
    }

    public function test_get_subscriptions_with_failed_payments(): void
    {
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'past_due',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        $result = $this->repository->getSubscriptionsWithFailedPayments($this->siteId);

        $this->assertGreaterThanOrEqual(1, $result->count());

        foreach ($result as $subscription) {
            $this->assertEquals('past_due', $subscription->status);
        }
    }

    public function test_create_subscription_sets_next_billing_date(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Monthly Plan',
            'slug' => 'monthly',
            'price' => 19.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        $subscription = $this->repository->createSubscription(
            $this->testMember->id,
            $plan->id,
            $this->siteId
        );

        $this->assertNotNull($subscription->next_billing_date);
        $this->assertInstanceOf(\DateTime::class, $subscription->next_billing_date);

        // Should be approximately 1 month from now
        $expectedDate = new \DateTime('+1 month');
        $diff = $subscription->next_billing_date->diff($expectedDate);
        $this->assertLessThanOrEqual(1, $diff->days);
    }

    public function test_create_subscription_does_not_set_next_billing_date_for_lifetime(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Lifetime Plan',
            'slug' => 'lifetime',
            'price' => 299.99,
            'currency' => 'USD',
            'billing_period' => 'lifetime',
            'is_active' => true
        ]);

        $subscription = $this->repository->createSubscription(
            $this->testMember->id,
            $plan->id,
            $this->siteId
        );

        $this->assertNull($subscription->next_billing_date);
        $this->assertFalse($subscription->auto_renew);
    }

    public function test_get_cancelled_subscription_for_plan_returns_most_recent_cancelled(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        // Create older cancelled subscription
        $olderCancelled = Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-1 month'))
        ]);

        // Create newer cancelled subscription
        $newerCancelled = Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForPlan(
            $member->id,
            $plan->id,
            $this->siteId
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($olderCancelled->id, $result->id);
        $this->assertEquals('cancelled', $result->status);
    }

    public function test_get_cancelled_subscription_for_plan_returns_null_when_none_exist(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        // Act
        $result = $this->repository->getCancelledSubscriptionForPlan(
            $member->id,
            $plan->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_cancelled_subscription_for_plan_ignores_active_subscriptions(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        // Create active subscription
        Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan->name,
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForPlan(
            $member->id,
            $plan->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_cancelled_subscription_for_plan_filters_by_site(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();
        $otherSite = $this->createSite();

        // Create cancelled subscription in other site
        Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'site_id' => $otherSite->id,
            'plan_name' => $plan->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForPlan(
            $member->id,
            $plan->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_cancelled_subscription_for_plan_filters_by_plan(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan1 = $this->createSubscriptionPlan();
        $plan2 = $this->createSubscriptionPlan();

        // Create cancelled subscription for different plan
        Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan2->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan2->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan2->price,
            'currency' => $plan2->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForPlan(
            $member->id,
            $plan1->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_cancelled_subscription_for_member_returns_most_recent_cancelled(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan1 = $this->createSubscriptionPlan();
        $plan2 = $this->createSubscriptionPlan();

        // Create older cancelled subscription
        $olderCancelled = Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan1->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan1->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'price' => $plan1->price,
            'currency' => $plan1->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-1 month'))
        ]);

        // Create newer cancelled subscription (different plan)
        $newerCancelled = Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan2->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan2->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => $plan2->price,
            'currency' => $plan2->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForMember(
            $member->id,
            $this->siteId
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($olderCancelled->id, $result->id);
        $this->assertEquals('cancelled', $result->status);
    }

    public function test_get_cancelled_subscription_for_member_returns_null_when_none_exist(): void
    {
        // Arrange
        $member = $this->createMember();

        // Act
        $result = $this->repository->getCancelledSubscriptionForMember(
            $member->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_cancelled_subscription_for_member_ignores_active_subscriptions(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        // Create active subscription
        Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan->name,
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForMember(
            $member->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_cancelled_subscription_for_member_filters_by_site(): void
    {
        // Arrange
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();
        $otherSite = $this->createSite();

        // Create cancelled subscription in other site
        Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'site_id' => $otherSite->id,
            'plan_name' => $plan->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForMember(
            $member->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_cancelled_subscription_for_member_filters_by_member(): void
    {
        // Arrange
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        // Create cancelled subscription for different member
        Subscription::create([
            'member_id' => $member2->id,
            'plan_id' => $plan->id,
            'site_id' => $this->siteId,
            'plan_name' => $plan->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $result = $this->repository->getCancelledSubscriptionForMember(
            $member1->id,
            $this->siteId
        );

        // Assert
        $this->assertNull($result);
    }

    public function test_get_scheduled_due_returns_due_subscriptions(): void
    {
        $now = new \DateTimeImmutable();

        $due = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::SCHEDULED->value,
            'start_date' => $now->format('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Future Plan',
            'status' => SubscriptionStatus::SCHEDULED->value,
            'start_date' => $now->modify('+2 days')->format('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $result = $this->repository->getScheduledDue($now);

        $this->assertCount(1, $result);
        $this->assertEquals($due->id, $result->first()->id);
    }

    public function test_get_scheduled_due_ignores_non_scheduled_status(): void
    {
        $now = new \DateTimeImmutable();

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Active Plan',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => $now->modify('-1 day')->format('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $result = $this->repository->getScheduledDue($now);

        $this->assertCount(0, $result);
    }

    public function test_get_scheduled_due_respects_limit(): void
    {
        $now = new \DateTimeImmutable();

        for ($i = 0; $i < 5; $i++) {
            Subscription::create([
                'member_id' => $this->testMember->id,
                'site_id' => $this->siteId,
                'plan_name' => 'Scheduled ' . $i,
                'status' => SubscriptionStatus::SCHEDULED->value,
                'start_date' => $now->modify('-1 day')->format('Y-m-d H:i:s'),
                'price' => 29.99,
                'currency' => 'USD'
            ]);
        }

        $result = $this->repository->getScheduledDue($now, 2);

        $this->assertCount(2, $result);
    }

    public function test_get_scheduled_due_does_not_return_future_start_dates(): void
    {
        $now = new \DateTimeImmutable();

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Future Plan',
            'status' => SubscriptionStatus::SCHEDULED->value,
            'start_date' => $now->modify('+1 day')->format('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $result = $this->repository->getScheduledDue($now);

        $this->assertCount(0, $result);
    }

    public function test_mark_as_active_updates_status(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => SubscriptionStatus::SCHEDULED->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->repository->markAsActive($subscription, new \DateTimeImmutable());

        $subscription->refresh();

        $this->assertEquals(SubscriptionStatus::ACTIVE->value, $subscription->status);
    }

    protected function createSubscriptionPlan(array $attributes = []): Model
    {
        return SubscriptionPlan::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Plan ' . uniqid(),
            'slug' => 'test-plan-' . uniqid(),
            'description' => 'A test subscription plan',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $attributes));
    }

    /**
     * Create a subscription with sensible defaults, accepting partial overrides.
     */
    private function subscription(int $memberId, array $overrides = []): Model
    {
        return Subscription::create(array_merge([
            'member_id' => $memberId,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => SubscriptionStatus::ACTIVE->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
        ], $overrides));
    }
}