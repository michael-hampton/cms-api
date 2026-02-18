<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
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

    public function testSearchSchedulesPaginatedWithNoFilters(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'issue_title' => 'Test Issue',
            'on_sale_date' => '2026-01-15',
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        $result = $this->repository->searchSchedulesPaginated(
            $this->siteId,
            [],
            1,
            20
        );

        $this->assertEquals(1, $result->getTotal());
        $this->assertEquals(1, $result->getPage());
        $this->assertCount(1, $result->getData());
    }

    public function testSearchSchedulesPaginatedWithPromotionFilter(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'promotion_id' => 100,
            'issue_number' => 1,
            'issue_title' => 'Product Issue',
            'on_sale_date' => '2026-01-15',
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'promotion_id' => 200,
            'issue_number' => 2,
            'issue_title' => 'Other Product',
            'on_sale_date' => '2026-01-16',
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        $result = $this->repository->searchSchedulesPaginated(
            $this->siteId,
            ['promotion_id' => 100],
            1,
            20
        );

        $this->assertEquals(1, $result->getTotal());
        $this->assertEquals(100, $result->getData()[0]['promotion_id']);
    }

    public function testSearchSchedulesPaginatedPagination(): void
    {
        $subscription = $this->createSubscription();

        // Create 25 deliveries
        for ($i = 1; $i <= 25; $i++) {
            IssueDelivery::create([
                'site_id' => $this->siteId,
                'subscription_id' => $subscription->id,
                'issue_number' => $i,
                'issue_title' => "Issue $i",
                'on_sale_date' => "2026-01-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'status' => IssueScheduleStatus::ACTIVE->value
            ]);
        }

        // Page 1
        $page1 = $this->repository->searchSchedulesPaginated($this->siteId, [], 1, 20);
        $this->assertEquals(25, $page1->getTotal());
        $this->assertCount(20, $page1->getData());

        // Page 2
        $page2 = $this->repository->searchSchedulesPaginated($this->siteId, [], 2, 20);
        $this->assertEquals(25, $page2->getTotal());
        $this->assertCount(5, $page2->getData());
    }

    public function testSearchSchedulesPaginatedWithSearchByIssueCode(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'issue_title' => 'Test',
            'issue_code' => 'ABC-001',
            'on_sale_date' => '2026-01-15',
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        $result = $this->repository->searchSchedulesPaginated(
            $this->siteId,
            ['search' => 'ABC'],
            1,
            20
        );

        $this->assertEquals(1, $result->getTotal());
    }

    public function testSearchSchedulesPaginatedWithDateRangeFilter(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'issue_title' => 'Jan Issue',
            'on_sale_date' => '2026-01-15',
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'issue_number' => 2,
            'issue_title' => 'Feb Issue',
            'on_sale_date' => '2026-02-15',
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        $result = $this->repository->searchSchedulesPaginated(
            $this->siteId,
            [
                'from_date' => '2026-02-01',
                'to_date' => '2026-02-28'
            ],
            1,
            20
        );

        $this->assertEquals(1, $result->getTotal());
        $this->assertEquals('Feb Issue', $result->getData()[0]['issue_title']);
    }

    public function testUpdateDeliveryStatusWithTrackingInfo(): void
    {
        $subscription = $this->createSubscription();
        $delivery = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'status' => IssueScheduleStatus::ACTIVE->value
        ]);

        $trackingInfo = [
            'carrier' => 'UPS',
            'tracking_number' => '1Z999AA10123456784'
        ];

        $updated = $this->repository->updateDeliveryStatus(
            $delivery->id,
            IssueScheduleStatus::ACTIVE->value,
            $trackingInfo
        );

        $this->assertEquals(IssueScheduleStatus::ACTIVE->value, $updated->status);
        $this->assertEquals($trackingInfo, $updated->tracking_info);
    }

    public function testValidateCsvRowWithInvalidStatus(): void
    {
        $result = $this->repository->bulkCreateFromCsv($this->siteId, [
            [
                'title' => 'Test',
                'issue_number' => 1,
                'on_sale_date' => '2026-01-01',
                'status' => 'invalid_status'
            ]
        ]);

        $this->assertNotEmpty($result['errors']);
    }

    public function testGetUpcomingDeliveriesRespectsLimit(): void
    {
        $subscription = $this->createSubscription();

        // Create 20 upcoming deliveries
        for ($i = 1; $i <= 20; $i++) {
            IssueDelivery::create([
                'subscription_id' => $subscription->id,
                'issue_number' => $i,
                'issue_title' => "Issue #$i",
                'estimated_delivery_date' => date('Y-m-d', strtotime("+$i days")),
                'status' => IssueScheduleStatus::ACTIVE->value
            ]);
        }

        $deliveries = $this->repository->getUpcomingDeliveries($subscription->id, 5);

        $this->assertCount(5, $deliveries);
    }

    public function testGetPastDeliveriesRespectsLimit(): void
    {
        $subscription = $this->createSubscription();

        // Create 10 past deliveries
        for ($i = 1; $i <= 10; $i++) {
            IssueDelivery::create([
                'subscription_id' => $subscription->id,
                'issue_number' => $i,
                'issue_title' => "Issue #$i",
                'estimated_delivery_date' => date('Y-m-d', strtotime("-$i days")),
                'status' => IssueScheduleStatus::ACTIVE->value
            ]);
        }

        $deliveries = $this->repository->getPastDeliveries($subscription->id, 3);

        $this->assertCount(3, $deliveries);
    }

    public function testDeleteReturnsFalseForNonExistentDelivery(): void
    {
        $result = $this->repository->delete(99999);
        $this->assertFalse($result);
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
            'delivery_type' => SubscriptionType::PRINTED->value
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