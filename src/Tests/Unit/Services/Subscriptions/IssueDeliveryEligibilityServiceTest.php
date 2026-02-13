<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class IssueDeliveryEligibilityServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private IssueDeliveryEligibilityService $service;

    public function test_is_subscription_eligible_returns_false_if_no_scheduled_date(): void
    {
        $subscription = new Subscription([], null);
        $issueDelivery = new IssueDelivery([], null);
        $issueDelivery->on_sale_date = null;
        $issueDelivery->estimated_delivery_date = null;

        $this->assertFalse($this->service->isSubscriptionEligible($subscription, $issueDelivery));
    }

    public function test_is_subscription_eligible_returns_false_if_plan_mismatch(): void
    {
        $plan1 = $this->createSubscriptionPlan(['name' => 'Plan 1']);
        $plan2 = $this->createSubscriptionPlan(['name' => 'Plan 2']);

        $scheduledDate = new \DateTime('2023-01-01');
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan1->id,
            'on_sale_date' => $scheduledDate
        ]);
        $subscription = $this->createSubscription([
            'plan_id' => $plan2->id,
            'status' => 'active'
        ]);

        $this->assertFalse($this->service->isSubscriptionEligible($subscription, $issueDelivery));
    }

    public function test_is_subscription_eligible_returns_false_if_not_active(): void
    {
        $plan = $this->createSubscriptionPlan();
        $scheduledDate = new \DateTime('2023-01-01');
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => $scheduledDate
        ]);
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'status' => 'cancelled'
        ]);

        $this->assertFalse($this->service->isSubscriptionEligible($subscription, $issueDelivery));
    }

    public function test_is_subscription_eligible_returns_false_if_not_started(): void
    {
        $plan = $this->createSubscriptionPlan();
        $scheduledDate = new \DateTime('2023-01-01');
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => $scheduledDate
        ]);
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => new \DateTime('2023-01-02')
        ]);

        $this->assertFalse($this->service->isSubscriptionEligible($subscription, $issueDelivery));
    }

    public function test_is_subscription_eligible_returns_false_if_already_ended(): void
    {
        $plan = $this->createSubscriptionPlan();
        $scheduledDate = new \DateTime('2023-01-10');
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => $scheduledDate
        ]);
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'status' => 'active',
            'end_date' => new \DateTime('2023-01-09')
        ]);

        $this->assertFalse($this->service->isSubscriptionEligible($subscription, $issueDelivery));
    }

    public function test_is_subscription_eligible_returns_false_if_no_active_window(): void
    {
        $plan = $this->createSubscriptionPlan();
        $scheduledDate = new \DateTime('2023-01-10');
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => $scheduledDate
        ]);
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => new \DateTime('2023-01-01'),
            'end_date' => new \DateTime('2023-12-31')
        ]);

        // No window created

        $this->assertFalse($this->service->isSubscriptionEligible($subscription, $issueDelivery));
    }

    public function test_is_subscription_eligible_returns_true_if_all_criteria_met(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $scheduledDate = new \DateTime('2023-01-10');
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => $scheduledDate
        ]);
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => new \DateTime('2023-01-01'),
            'end_date' => new \DateTime('2023-12-31')
        ]);

        SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription->id,
            'window_start' => new \DateTime('2023-01-01'),
            'window_end' => new \DateTime('2023-01-31'),
            'site_id' => 1
        ]);

        $this->assertTrue($this->service->isSubscriptionEligible($subscription, $issueDelivery));
    }

    public function test_get_eligible_subscriptions_returns_empty_collection_if_no_scheduled_date(): void
    {
        $issueDelivery = new IssueDelivery([], null);
        $issueDelivery->on_sale_date = null;
        $issueDelivery->estimated_delivery_date = null;

        $results = $this->service->getEligibleSubscriptions($issueDelivery);
        $this->assertCount(0, $results);
    }

    public function test_get_eligible_subscriptions_filters_correctly(): void
    {
        $plan1 = $this->createSubscriptionPlan();
        $plan2 = $this->createSubscriptionPlan();
        $member = $this->createMember();

        $scheduledDate = new \DateTime('2023-01-10');
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan1->id,
            'on_sale_date' => $scheduledDate
        ]);

        // Eligible
        $sub1 = $this->createSubscription([
            'plan_id' => $plan1->id,
            'status' => 'active',
            'start_date' => new \DateTime('2023-01-01'),
            'end_date' => new \DateTime('2023-12-31')
        ]);
        SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $sub1->id,
            'window_start' => new \DateTime('2023-01-01'),
            'window_end' => new \DateTime('2023-01-31'),
            'site_id' => 1
        ]);

        // Wrong plan
        $sub2 = $this->createSubscription([
            'plan_id' => $plan2->id,
            'status' => 'active'
        ]);

        // Inactive
        $sub3 = $this->createSubscription([
            'plan_id' => $plan1->id,
            'status' => 'cancelled'
        ]);

        // No window
        $sub4 = $this->createSubscription([
            'plan_id' => $plan1->id,
            'status' => 'active',
            'start_date' => new \DateTime('2023-01-01')
        ]);

        $results = $this->service->getEligibleSubscriptions($issueDelivery);

        $this->assertCount(1, $results);
        $this->assertEquals($sub1->id, $results->first()->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IssueDeliveryEligibilityService();
    }
}
