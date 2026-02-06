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

    public function testGetAllDeliveries(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'issue_title' => 'Issue #1',
            'estimated_delivery_date' => date('Y-m-d', strtotime('-5 days')),
            'status' => 'Delivered'
        ]);

        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 2,
            'issue_title' => 'Issue #2',
            'estimated_delivery_date' => date('Y-m-d', strtotime('+5 days')),
            'status' => 'Scheduled'
        ]);

        $deliveries = $this->repository->getAllDeliveries($subscription->id);

        $this->assertCount(2, $deliveries);
    }

    public function testGetPastDeliveries(): void
    {
        $subscription = $this->createSubscription();

        // Past delivery
        $past = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'issue_title' => 'Issue #1',
            'estimated_delivery_date' => date('Y-m-d', strtotime('-5 days')),
            'status' => 'Delivered'
        ]);

        // Upcoming delivery
        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 2,
            'issue_title' => 'Issue #2',
            'estimated_delivery_date' => date('Y-m-d', strtotime('+5 days')),
            'status' => 'Scheduled'
        ]);

        $deliveries = $this->repository->getPastDeliveries($subscription->id);

        $this->assertCount(1, $deliveries);
        $this->assertEquals($past->id, $deliveries->first()->id);
    }

    public function testUpdateDeliveryStatus(): void
    {
        $subscription = $this->createSubscription();
        $delivery = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'status' => 'Scheduled'
        ]);

        $updated = $this->repository->updateDeliveryStatus($delivery->id, 'Delivered');

        $this->assertEquals('Delivered', $updated->status);
    }

    public function testBulkCreateFromCsv(): void
    {
        $rows = [
            [
                'title' => 'Issue 1',
                'issue_number' => 1,
                'on_sale_date' => '2026-01-01',
                'status' => 'active'
            ],
            [
                'title' => '', // Invalid
                'issue_number' => 2,
                'on_sale_date' => '2026-02-01'
            ]
        ];

        $result = $this->repository->bulkCreateFromCsv($this->siteId, $rows);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['success_count']);
        $this->assertEquals(1, $result['error_count']);
        $this->assertCount(1, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('Title is required', $result['errors'][0]['error']);
    }

    public function testGetAllForSite(): void
    {
        $subscription = $this->createSubscription();
        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'title' => 'Test',
            'on_sale_date' => '2026-01-01'
        ]);

        $deliveries = $this->repository->getAllForSite($this->siteId);
        $this->assertCount(1, $deliveries);
    }

    public function testDelete(): void
    {
        $subscription = $this->createSubscription();
        $delivery = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'status' => 'Scheduled'
        ]);

        $result = $this->repository->delete($delivery->id);
        $this->assertTrue($result);
        $this->assertNull(IssueDelivery::find($delivery->id));
    }

    public function testDeleteWithExistingDeliveriesThrowsException(): void
    {
        $subscription = $this->createSubscription();
        $delivery1 = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'status' => 'Scheduled'
        ]);
        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 2,
            'status' => 'Scheduled'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete schedule with existing deliveries');

        $this->repository->delete($delivery1->id);
    }

    public function testSearchSchedules(): void
    {
        $subscription = $this->createSubscription();
        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'issue_number' => '101',
            'issue_title' => 'Specific Issue',
            'on_sale_date' => '2026-01-15',
            'status' => 'Published'
        ]);

        // Test status filter
        $results = $this->repository->searchSchedules($this->siteId, ['status' => 'Published']);
        $this->assertCount(1, $results);

        // Test search filter (title)
        $results = $this->repository->searchSchedules($this->siteId, ['search' => 'Specific']);
        $this->assertCount(1, $results);

        // Test search filter (issue_number)
        $results = $this->repository->searchSchedules($this->siteId, ['search' => '101']);
        $this->assertCount(1, $results);

        // Test date range
        $results = $this->repository->searchSchedules($this->siteId, [
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31'
        ]);
        $this->assertCount(1, $results);
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
        $startDate = new \DateTime('2026-01-01');
        $endDate = (clone $startDate)->modify('+3 months');

        $deliveries = $this->repository->generateDeliverySchedule(
            $subscription->id,
            $startDate,
            $endDate,
            'monthly',
            5
        );

        $this->assertCount(4, $deliveries); // Jan, Feb, Mar, Apr (since Apr 1st <= endDate)
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