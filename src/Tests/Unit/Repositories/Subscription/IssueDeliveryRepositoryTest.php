<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class IssueDeliveryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private IssueDeliveryRepository $repository;

    public function testGetUpcomingDeliveries(): void
    {
        $subscription = $this->createSubscription();

        // Create past delivery
        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'issue_title' => 'Issue #1',
            'estimated_delivery_date' => date('Y-m-d', strtotime('-5 days')),
            'status' => 'Delivered'
        ]);

        // Create upcoming delivery
        $upcoming = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 2,
            'issue_title' => 'Issue #2',
            'estimated_delivery_date' => date('Y-m-d', strtotime('+5 days')),
            'status' => 'Scheduled'
        ]);

        $deliveries = $this->repository->getUpcomingDeliveries($subscription->id);

        $this->assertCount(1, $deliveries);
        $this->assertEquals($upcoming->id, $deliveries->first()->id);
    }

    private function createSubscription(): \App\Models\Subscription
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        return \App\Models\Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'delivery_type' => 'print'
        ]);
    }

    public function testGenerateDeliverySchedule(): void
    {
        $subscription = $this->createSubscription();
        $startDate = new \DateTime();
        $endDate = (clone $startDate)->modify('+3 months');

        $deliveries = $this->repository->generateDeliverySchedule(
            $subscription->id,
            $startDate,
            $endDate,
            'monthly',
            5
        );

        $this->assertCount(3, $deliveries); // 4 monthly issues in 3 months
        $this->assertEquals(1, $deliveries[0]->issue_number);
        $this->assertEquals('Scheduled', $deliveries[0]->status);
    }

    public function testCalculateStatus(): void
    {
        $subscription = $this->createSubscription();

        // Scheduled delivery
        $scheduled = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+10 days')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+15 days'))
        ]);

        $this->assertEquals('Scheduled', $scheduled->calculateStatus());

        // In transit delivery
        $inTransit = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 2,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+3 days'))
        ]);

        $this->assertEquals('In Transit', $inTransit->calculateStatus());

        // Delivered
        $delivered = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 3,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]);

        $this->assertEquals('Delivered', $delivered->calculateStatus());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new IssueDeliveryRepository();
    }
}