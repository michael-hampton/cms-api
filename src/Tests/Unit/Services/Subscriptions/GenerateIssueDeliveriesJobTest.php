<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Jobs\Subscriptions\GenerateIssueDeliveriesJob;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class GenerateIssueDeliveriesJobTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_creates_deliveries_for_eligible_subscriptions(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $issueDelivery = IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => now_datetime()->addDays(7),
        ]);

        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now_datetime()->subDays(30),
            'end_date' => now_datetime()->addDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan'
        ]);

        SubscriptionWindow::create([
            'subscription_id' => $subscription->id,
            'window_start' => now_datetime()->subDays(30),
            'window_end' => now_datetime()->addDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId
        ]);

        $job = app(GenerateIssueDeliveriesJob::class);
        $result = $job->handle($issueDelivery);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);
    }

    public function test_skips_existing_deliveries_idempotent(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $issueDelivery = IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => now_datetime()->addDays(7),
        ]);

        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'start_date' => now_datetime()->subDays(30),
        ]);

        SubscriptionWindow::create([
            'subscription_id' => $subscription->id,
            'window_start' => now_datetime()->subDays(30),
            'window_end' => now_datetime()->addDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId
        ]);

        // Run twice
        $job = app(GenerateIssueDeliveriesJob::class);
        $job->handle($issueDelivery);
        $result = $job->handle($issueDelivery);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_only_processes_subscriptions_within_active_window(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $issueDelivery = IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => now_datetime()->addDays(7),
        ]);

        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'start_date' => now_datetime()->subDays(30),
        ]);

        // Window that doesn't cover the scheduled date
        SubscriptionWindow::create([
            'subscription_id' => $subscription->id,
            'window_start' => now_datetime()->subDays(60),
            'window_end' => now_datetime()->subDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId
        ]);

        $job = app(GenerateIssueDeliveriesJob::class);
        $result = $job->handle($issueDelivery);

        $this->assertEquals(0, $result['created']);
    }
}