<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Exceptions\Stock\StockException;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class IssueDeliveryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private IssueDeliveryRepository $repository;

    private SubscriptionPlan $plan;
    private SubscriptionPlan $otherPlan;

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

    public function test_decrement_stock_reduces_quantity_in_database(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'issue_title' => 'Spring Issue',
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $updated = $this->repository->decrementStock($issue->id, 3);

        $this->assertEquals(7, $updated->stock_quantity);
        $this->assertEquals(7, IssueDelivery::find($issue->id)->stock_quantity);
    }

    public function test_decrement_stock_by_one(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $updated = $this->repository->decrementStock($issue->id, 1);

        $this->assertEquals(4, $updated->stock_quantity);
    }

    public function test_decrement_stock_to_zero(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 2,
            'status' => 'active',
        ]);

        $updated = $this->repository->decrementStock($issue->id, 2);

        $this->assertEquals(0, $updated->stock_quantity);
        $this->assertEquals(0, IssueDelivery::find($issue->id)->stock_quantity);
    }

    public function test_decrement_stock_returns_issue_delivery_model(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $result = $this->repository->decrementStock($issue->id, 1);

        $this->assertInstanceOf(IssueDelivery::class, $result);
        $this->assertEquals($issue->id, $result->id);
    }

    public function test_decrement_stock_throws_when_issue_not_found(): void
    {
        $this->expectException(StockException::class);
        $this->expectExceptionMessage('IssueDelivery#99999');

        $this->repository->decrementStock(99999, 1);
    }

    public function test_multiple_sequential_decrements_are_cumulative(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 20,
            'status' => 'active',
        ]);

        $this->repository->decrementStock($issue->id, 5);
        $this->repository->decrementStock($issue->id, 3);
        $final = $this->repository->decrementStock($issue->id, 2);

        $this->assertEquals(10, $final->stock_quantity);
        $this->assertEquals(10, IssueDelivery::find($issue->id)->stock_quantity);
    }

    public function test_increment_stock_increases_quantity_in_database(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $updated = $this->repository->incrementStock($issue->id, 3);

        $this->assertEquals(8, $updated->stock_quantity);
        $this->assertEquals(8, IssueDelivery::find($issue->id)->stock_quantity);
    }

    public function test_increment_stock_from_zero(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 0,
            'status' => 'active',
        ]);

        $updated = $this->repository->incrementStock($issue->id, 10);

        $this->assertEquals(10, $updated->stock_quantity);
    }

    public function test_increment_stock_returns_issue_delivery_model(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $result = $this->repository->incrementStock($issue->id, 1);

        $this->assertInstanceOf(IssueDelivery::class, $result);
        $this->assertEquals($issue->id, $result->id);
    }

    public function test_increment_stock_throws_when_issue_not_found(): void
    {
        $this->expectException(StockException::class);
        $this->expectExceptionMessage('IssueDelivery#99999');

        $this->repository->incrementStock(99999, 1);
    }

    public function test_decrement_then_increment_returns_to_original_quantity(): void
    {
        $subscription = $this->createSubscription();
        $issue = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number' => 1,
            'stock_quantity' => 15,
            'status' => 'active',
        ]);

        $this->repository->decrementStock($issue->id, 4);
        $restored = $this->repository->incrementStock($issue->id, 4);

        $this->assertEquals(15, $restored->stock_quantity);
        $this->assertEquals(15, IssueDelivery::find($issue->id)->stock_quantity);
    }

    public function test_returns_array_with_expected_keys(): void
    {
        $sub = $this->createSubscription();

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('last_page', $result);
    }

    public function test_returns_empty_result_when_no_deliveries_exist(): void
    {
        $sub = $this->createSubscription();

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
        );

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['data']);
        $this->assertEquals(1, $result['last_page']);
    }

    public function test_upcoming_delivery_is_classified_as_upcoming(): void
    {
        $sub = $this->createSubscription();
        $issue = $this->makeUpcoming($sub->plan_id);

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'all',
        );

        $row = collect($result['data'])->firstWhere('id', $issue->id);
        $this->assertNotNull($row);
        $this->assertEquals('upcoming', $row['type']);
    }

    public function test_past_undelivered_issue_is_classified_as_missed(): void
    {
        $sub = $this->createSubscription();
        $issue = $this->makeMissed($sub->plan_id);
        // Deliberately no markDelivered() call — this is what makes it 'missed'

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'all',
        );

        $row = collect($result['data'])->firstWhere('id', $issue->id);
        $this->assertNotNull($row);
        $this->assertEquals('missed', $row['type']);
    }

    public function test_issue_with_delivered_record_is_classified_as_delivered(): void
    {
        $sub = $this->createSubscription();
        $issue = $this->makePast($sub->plan_id);
        $this->markDelivered($issue->id, $sub->id);

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'all',
        );

        $row = collect($result['data'])->firstWhere('id', $issue->id);
        $this->assertNotNull($row);
        $this->assertEquals('delivered', $row['type']);
    }

    public function test_filter_upcoming_excludes_past_issues(): void
    {
        $sub = $this->createSubscription();
        $past = $this->makePast($sub->plan_id, 1);
        $upcoming = $this->makeUpcoming($sub->plan_id, 2);

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'upcoming',
        );

        $ids = array_column($result['data'], 'id');
        $this->assertContains($upcoming->id, $ids);
        $this->assertNotContains($past->id, $ids);
    }

    public function test_filter_previous_returns_only_delivered(): void
    {
        $sub = $this->createSubscription();
        $past = $this->makePast($sub->plan_id, 1);
        $upcoming = $this->makeUpcoming($sub->plan_id, 2);
        $this->markDelivered($past->id, $sub->id);

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'previous',
        );

        $ids = array_column($result['data'], 'id');
        $this->assertContains($past->id, $ids);
        $this->assertNotContains($upcoming->id, $ids);
    }

    public function test_filter_missed_returns_only_missed_issues(): void
    {
        $sub = $this->createSubscription();
        $missed = $this->makeMissed($sub->plan_id, 1);
        $delivered = $this->makeMissed($sub->plan_id, 2);
        $this->markDelivered($delivered->id, $sub->id);

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'missed',
        );

        $ids = array_column($result['data'], 'id');
        $this->assertContains($missed->id, $ids);
        $this->assertNotContains($delivered->id, $ids);
    }

    public function test_pagination_respects_per_page(): void
    {
        $sub = $this->createSubscription();

        for ($i = 1; $i <= 5; $i++) {
            $this->makeUpcoming($sub->plan_id, $i);
        }

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'upcoming',
            null,
            null,
            1,
            3,
        );

        // Current page contains 3 rows
        $this->assertCount(3, $result['data']);

        // Total matching rows across all pages
        $this->assertEquals(5, $result['total']);

        // 5 rows @ 3 per page = 2 pages
        $this->assertEquals(2, $result['last_page']);
    }

    public function test_pagination_returns_second_page(): void
    {
        $sub = $this->createSubscription();

        for ($i = 1; $i <= 5; $i++) {
            $this->makeUpcoming($sub->plan_id, $i);
        }

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'all',
            null,
            null,
            2,
            3,
        );

        $this->assertCount(2, $result['data']);
    }

    public function test_each_row_has_required_fields(): void
    {
        $sub = $this->createSubscription();
        $this->makeUpcoming($sub->plan_id);

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
        );

        $row = $result['data'][0];
        foreach (['id', 'issue_number', 'issue_title', 'on_sale_date', 'estimated_delivery_date', 'status', 'type'] as $key) {
            $this->assertArrayHasKey($key, $row, "Row is missing key: {$key}");
        }
    }

    public function test_from_date_excludes_earlier_deliveries(): void
    {
        $sub = $this->createSubscription();
        $early = IssueDelivery::create([
            'subscription_plan_id' => $sub->plan_id,
            'issue_number' => 1,
            'estimated_delivery_date' => '2024-01-10',
        ]);
        $late = IssueDelivery::create([
            'subscription_plan_id' => $sub->plan_id,
            'issue_number' => 2,
            'estimated_delivery_date' => '2024-06-01',
        ]);

        $result = $this->repository->getPaginatedForSubscription(
            $sub->plan_id,
            $sub->id,
            'all',
            new \DateTime('2024-03-01'),
        );

        $ids = array_column($result['data'], 'id');
        $this->assertContains($late->id, $ids);
        $this->assertNotContains($early->id, $ids);
    }

    private function markDelivered(int $issueDeliveryId, int $subscriptionId): void
    {
        \App\Framework\Database\Database::table('subscription_issue_fulfilments')->insert([
            'issue_delivery_id' => $issueDeliveryId,
            'subscription_id' => $subscriptionId,
            'delivered_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function makeMissed(int $planId, int $issueNumber = 99): Model
    {
        return IssueDelivery::create([
            'subscription_plan_id' => $planId,
            'issue_number' => $issueNumber,
            'issue_title' => "Old Issue #{$issueNumber}",
            'on_sale_date' => date('Y-m-d', strtotime('-20 days')),
            'estimated_delivery_date' => date('Y-m-d', strtotime('-10 days')),
            'status' => 'Delivered',
        ]);
    }

    /** Create an IssueDelivery row tied to $planId with a future delivery date. */
    private function makeUpcoming(int $planId, int $issueNumber = 1): Model
    {
        return IssueDelivery::create([
            'subscription_plan_id' => $planId,
            'issue_number' => $issueNumber,
            'issue_title' => "Issue #{$issueNumber}",
            'on_sale_date' => date('Y-m-d', strtotime('+' . $issueNumber . ' days')),
            'estimated_delivery_date' => date('Y-m-d', strtotime('+' . ($issueNumber + 5) . ' days')),
            'status' => 'Scheduled',
        ]);
    }

    /** Create an IssueDelivery row tied to $planId with a past delivery date. */
    private function makePast(int $planId, int $issueNumber = 99): Model
    {
        return IssueDelivery::create([
            'subscription_plan_id' => $planId,
            'issue_number' => $issueNumber,
            'issue_title' => "Old Issue #{$issueNumber}",
            'on_sale_date' => date('Y-m-d', strtotime('-20 days')),
            'estimated_delivery_date' => date('Y-m-d', strtotime('-10 days')),
            'status' => 'Delivered',
        ]);
    }

    // =========================================================================
    // getFutureDeliveriesForSubscription
    // =========================================================================

    public function test_get_future_deliveries_returns_rows_matching_given_statuses(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 1,
            'status'          => 'pending',
        ]);
        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 2,
            'status'          => 'scheduled',
        ]);
        // Should be excluded
        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 3,
            'status'          => 'superseded',
        ]);

        $results = $this->repository->getFutureDeliveriesForSubscription(
            $subscription->id,
            ['pending', 'scheduled', 'not_dispatched'],
        );

        $this->assertCount(2, $results);
        $statuses = array_column($results, 'status');
        $this->assertContains('pending',   $statuses);
        $this->assertContains('scheduled', $statuses);
    }

    public function test_get_future_deliveries_excludes_other_subscriptions(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();

        IssueDelivery::create([
            'subscription_id' => $subscriptionA->id,
            'issue_number'    => 1,
            'status'          => 'pending',
        ]);
        IssueDelivery::create([
            'subscription_id' => $subscriptionB->id,
            'issue_number'    => 2,
            'status'          => 'pending',
        ]);

        $results = $this->repository->getFutureDeliveriesForSubscription(
            $subscriptionA->id,
            ['pending'],
        );

        $this->assertCount(1, $results);
        $this->assertEquals($subscriptionA->id, $results[0]->subscription_id);
    }

    public function test_get_future_deliveries_returns_empty_array_when_none_match(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 1,
            'status'          => 'superseded',
        ]);

        $results = $this->repository->getFutureDeliveriesForSubscription(
            $subscription->id,
            ['pending', 'scheduled', 'not_dispatched'],
        );

        $this->assertCount(0, $results);
    }

    public function test_get_future_deliveries_returns_empty_array_for_unknown_subscription(): void
    {
        $results = $this->repository->getFutureDeliveriesForSubscription(
            999999,
            ['pending'],
        );

        $this->assertCount(0, $results);
    }

    public function test_get_future_deliveries_returns_issue_delivery_instances(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 1,
            'status'          => 'pending',
        ]);

        $results = $this->repository->getFutureDeliveriesForSubscription(
            $subscription->id,
            ['pending'],
        );

        $this->assertInstanceOf(IssueDelivery::class, $results[0]);
    }

    // =========================================================================
    // supersedeManyByIds
    // =========================================================================

    public function test_supersede_many_updates_status_to_superseded_for_all_ids(): void
    {
        $subscription = $this->createSubscription();

        $deliveryA = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 1,
            'status'          => 'pending',
        ]);
        $deliveryB = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 2,
            'status'          => 'scheduled',
        ]);

        $this->repository->supersedeManyByIds([$deliveryA->id, $deliveryB->id]);

        $this->assertEquals('superseded', IssueDelivery::find($deliveryA->id)->status);
        $this->assertEquals('superseded', IssueDelivery::find($deliveryB->id)->status);
    }

    public function test_supersede_many_does_not_affect_rows_not_in_ids(): void
    {
        $subscription = $this->createSubscription();

        $target = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 1,
            'status'          => 'pending',
        ]);
        $untouched = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 2,
            'status'          => 'pending',
        ]);

        $this->repository->supersedeManyByIds([$target->id]);

        $this->assertEquals('superseded', IssueDelivery::find($target->id)->status);
        $this->assertEquals('pending',    IssueDelivery::find($untouched->id)->status);
    }

    public function test_supersede_many_is_no_op_for_empty_array(): void
    {
        $subscription = $this->createSubscription();

        $delivery = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 1,
            'status'          => 'pending',
        ]);

        // Must not throw and must not mutate any row
        $this->repository->supersedeManyByIds([]);

        $this->assertEquals('pending', IssueDelivery::find($delivery->id)->status);
    }

    public function test_supersede_many_handles_single_id(): void
    {
        $subscription = $this->createSubscription();

        $delivery = IssueDelivery::create([
            'subscription_id' => $subscription->id,
            'issue_number'    => 1,
            'status'          => 'not_dispatched',
        ]);

        $this->repository->supersedeManyByIds([$delivery->id]);

        $this->assertEquals('superseded', IssueDelivery::find($delivery->id)->status);
    }

    // =========================================================================
    // countFutureForSubscription
    // =========================================================================

    public function test_count_future_returns_correct_count_for_matching_statuses(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create(['subscription_id' => $subscription->id, 'issue_number' => 1, 'status' => 'pending']);
        IssueDelivery::create(['subscription_id' => $subscription->id, 'issue_number' => 2, 'status' => 'scheduled']);
        IssueDelivery::create(['subscription_id' => $subscription->id, 'issue_number' => 3, 'status' => 'not_dispatched']);
        // Not counted
        IssueDelivery::create(['subscription_id' => $subscription->id, 'issue_number' => 4, 'status' => 'superseded']);

        $count = $this->repository->countFutureForSubscription(
            $subscription->id,
            ['pending', 'scheduled', 'not_dispatched'],
        );

        $this->assertEquals(3, $count);
    }

    public function test_count_future_returns_zero_when_no_rows_match(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create(['subscription_id' => $subscription->id, 'issue_number' => 1, 'status' => 'superseded']);

        $count = $this->repository->countFutureForSubscription(
            $subscription->id,
            ['pending', 'scheduled', 'not_dispatched'],
        );

        $this->assertEquals(0, $count);
    }

    public function test_count_future_excludes_other_subscriptions(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();

        IssueDelivery::create(['subscription_id' => $subscriptionA->id, 'issue_number' => 1, 'status' => 'pending']);
        IssueDelivery::create(['subscription_id' => $subscriptionB->id, 'issue_number' => 2, 'status' => 'pending']);

        $count = $this->repository->countFutureForSubscription(
            $subscriptionA->id,
            ['pending'],
        );

        $this->assertEquals(1, $count);
    }

    public function test_count_future_returns_zero_for_unknown_subscription(): void
    {
        $count = $this->repository->countFutureForSubscription(999999, ['pending']);

        $this->assertEquals(0, $count);
    }

    // =========================================================================
    // getUpcomingScheduleIssues
    // =========================================================================

    public function test_get_upcoming_schedule_issues_returns_schedule_rows_for_edition(): void
    {
        $plan = $this->createSubscriptionPlan();
        $plan2 = $this->createSubscriptionPlan();

        // Schedule rows: subscription_plan_id set, subscription_id null
        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 1,
            'on_sale_date'         => '2026-03-01',
            'status'               => 'active',
        ]);
        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 2,
            'on_sale_date'         => '2026-04-01',
            'status'               => 'scheduled',
        ]);
        // Different edition — must be excluded
        IssueDelivery::create([
            'subscription_plan_id' => $plan2->id,
            'subscription_id'      => null,
            'issue_number'         => 1,
            'on_sale_date'         => '2026-03-15',
            'status'               => 'active',
        ]);

        $results = $this->repository->getUpcomingScheduleIssues(1, 10);

        $this->assertCount(2, $results);
        foreach ($results as $row) {
            $this->assertEquals(1, $row->subscription_plan_id);
        }
    }

    public function test_get_upcoming_schedule_issues_excludes_fulfilment_rows(): void
    {
        $subscription = $this->createSubscription();

        // Fulfilment row (has subscription_id) — must not appear
        IssueDelivery::create([
            'subscription_plan_id' => $subscription->plan_id,
            'subscription_id'      => $subscription->id,
            'issue_number'         => 1,
            'on_sale_date'         => '2026-03-01',
            'status'               => 'active',
        ]);
        // Schedule row (no subscription_id) — must appear
        IssueDelivery::create([
            'subscription_plan_id' => $subscription->plan_id,
            'subscription_id'      => null,
            'issue_number'         => 2,
            'on_sale_date'         => '2026-04-01',
            'status'               => 'active',
        ]);

        $results = $this->repository->getUpcomingScheduleIssues($subscription->plan_id, 10);

        $this->assertCount(1, $results);
        $this->assertNull($results->first()->subscription_id);
    }

    public function test_get_upcoming_schedule_issues_orders_by_on_sale_date_ascending(): void
    {
        $plan = $this->createSubscriptionPlan();

        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 3,
            'on_sale_date'         => '2026-06-01',
            'status'               => 'active',
        ]);
        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 1,
            'on_sale_date'         => '2026-04-01',
            'status'               => 'active',
        ]);
        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 2,
            'on_sale_date'         => '2026-05-01',
            'status'               => 'active',
        ]);

        $results = $this->repository->getUpcomingScheduleIssues($plan->id, 10);

        $saleDates = $results
            ->pluck('on_sale_date')
            ->map(fn ($date) => $date->format('Y-m-d H:i:s'))
            ->all();

        $this->assertEquals([
            '2026-04-01 00:00:00',
            '2026-05-01 00:00:00',
            '2026-06-01 00:00:00',
        ], $saleDates);
    }

    public function test_get_upcoming_schedule_issues_respects_limit(): void
    {
        $plan = $this->createSubscriptionPlan();

        for ($i = 1; $i <= 5; $i++) {
            IssueDelivery::create([
                'subscription_plan_id' => $plan->id,
                'subscription_id'      => null,
                'issue_number'         => $i,
                'on_sale_date'         => "2026-0{$i}-01",
                'status'               => 'active',
            ]);
        }

        $results = $this->repository->getUpcomingScheduleIssues($plan->id, 3);

        $this->assertCount(3, $results);
    }

    public function test_get_upcoming_schedule_issues_excludes_non_schedule_statuses(): void
    {
        $plan = $this->createSubscriptionPlan();

        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 1,
            'on_sale_date'         => '2026-03-01',
            'status'               => 'superseded', // not in default schedule statuses
        ]);
        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 2,
            'on_sale_date'         => '2026-04-01',
            'status'               => 'active',
        ]);

        $results = $this->repository->getUpcomingScheduleIssues($plan->id, 10);

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results->first()->status);
    }

    public function test_get_upcoming_schedule_issues_accepts_custom_schedule_statuses(): void
    {
        $plan = $this->createSubscriptionPlan();
        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 1,
            'on_sale_date'         => '2026-03-01',
            'status'               => 'draft',
        ]);
        IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'subscription_id'      => null,
            'issue_number'         => 2,
            'on_sale_date'         => '2026-04-01',
            'status'               => 'active',
        ]);

        $results = $this->repository->getUpcomingScheduleIssues($plan->id, 10, ['draft']);

        $this->assertCount(1, $results);
        $this->assertEquals('draft', $results->first()->status);
    }

    public function test_get_upcoming_schedule_issues_returns_empty_collection_for_unknown_edition(): void
    {
        $results = $this->repository->getUpcomingScheduleIssues(999999, 10);

        $this->assertCount(0, $results);
    }

    // =========================================================================
    // createFulfilmentFromSchedule
    // =========================================================================

    public function test_create_fulfilment_from_schedule_persists_new_row(): void
    {
        $subscription  = $this->createSubscription();
        $scheduleIssue = $this->makeScheduleIssue($subscription->plan_id);

        $created = $this->repository->createFulfilmentFromSchedule(
            $subscription->id,
            $subscription->plan_id,
            $scheduleIssue,
        );

        $this->assertNotNull($created->id);
        $fromDb = IssueDelivery::find($created->id);
        $this->assertNotNull($fromDb);
    }

    public function test_create_fulfilment_from_schedule_sets_subscription_id(): void
    {
        $subscription  = $this->createSubscription();
        $scheduleIssue = $this->makeScheduleIssue($subscription->plan_id);

        $created = $this->repository->createFulfilmentFromSchedule(
            $subscription->id,
            $subscription->plan_id,
            $scheduleIssue,
        );

        $this->assertEquals($subscription->id, $created->subscription_id);
    }

    public function test_create_fulfilment_from_schedule_sets_status_to_pending(): void
    {
        $subscription  = $this->createSubscription();
        $scheduleIssue = $this->makeScheduleIssue($subscription->plan_id);

        $created = $this->repository->createFulfilmentFromSchedule(
            $subscription->id,
            $subscription->plan_id,
            $scheduleIssue,
        );

        $this->assertEquals('pending', $created->status);
    }

    public function test_create_fulfilment_from_schedule_copies_fields_from_schedule_issue(): void
    {
        $subscription  = $this->createSubscription();
        $scheduleIssue = $this->makeScheduleIssue($subscription->plan_id, [
            'issue_number'            => 42,
            'issue_title'             => 'Spring Edition',
            'on_sale_date'            => '2026-03-01',
            'estimated_delivery_date' => '2026-03-10',
            'issue_code'              => 'SPR-26',
            'cut_off_date'            => '2026-02-20',
        ]);

        $created = $this->repository->createFulfilmentFromSchedule(
            $subscription->id,
            $subscription->plan_id,
            $scheduleIssue,
        );

        $this->assertEquals(42,           $created->issue_number);
        $this->assertEquals('Spring Edition', $created->issue_title);
        $this->assertEquals('2026-03-01', $created->on_sale_date->format('Y-m-d'));
        $this->assertEquals('2026-03-10', $created->estimated_delivery_date->format('Y-m-d'));
        $this->assertEquals('SPR-26',     $created->issue_code);
        $this->assertEquals('2026-02-20', $created->cut_off_date->format('Y-m-d'));
    }

    public function test_create_fulfilment_from_schedule_sets_subscription_plan_id_to_edition_id(): void
    {
        $subscription  = $this->createSubscription();
        $scheduleIssue = $this->makeScheduleIssue($subscription->plan_id);

        $created = $this->repository->createFulfilmentFromSchedule(
            $subscription->id,
            $subscription->plan_id,
            $scheduleIssue,
        );

        $this->assertEquals($subscription->plan_id, $created->subscription_plan_id);
    }

    public function test_create_fulfilment_from_schedule_returns_issue_delivery_instance(): void
    {
        $subscription  = $this->createSubscription();
        $scheduleIssue = $this->makeScheduleIssue($subscription->plan_id);

        $created = $this->repository->createFulfilmentFromSchedule(
            $subscription->id,
            $subscription->plan_id,
            $scheduleIssue,
        );

        $this->assertInstanceOf(IssueDelivery::class, $created);
    }

    public function test_create_fulfilment_from_schedule_does_not_mutate_the_schedule_row(): void
    {
        $subscription  = $this->createSubscription();
        $scheduleIssue = $this->makeScheduleIssue($subscription->plan_id);
        $originalId    = $scheduleIssue->id;

        $this->repository->createFulfilmentFromSchedule(
            $subscription->id,
            $subscription->plan_id,
            $scheduleIssue,
        );

        // Schedule row still exists and is unchanged
        $scheduleRowAfter = IssueDelivery::find($originalId);
        $this->assertNotNull($scheduleRowAfter);
        $this->assertNull($scheduleRowAfter->subscription_id);
    }

    public function test_find_future_issues_for_plan_returns_matching_future_active_issues(): void
    {
        $plan = $this->createSubscriptionPlan();

        $past = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Past Issue',
            'on_sale_date'         => '2026-01-01 00:00:00',
            'status'               => IssueScheduleStatus::ACTIVE->value,
        ]);

        $future = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 2,
            'issue_title'          => 'Future Issue',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => IssueScheduleStatus::ACTIVE->value,
        ]);

        $results = $this->repository->findFutureIssuesForPlan(
            $plan->id,
            new \DateTimeImmutable('2026-02-01 00:00:00'),
            10,
        );

        $this->assertCount(1, $results);
        $this->assertEquals($future->id, $results->first()->id);
    }

    public function test_find_future_issues_for_plan_excludes_other_plans(): void
    {
        $planA = $this->createSubscriptionPlan();
        $planB = $this->createSubscriptionPlan();

        $matching = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $planA->id,
            'issue_number'         => 1,
            'issue_title'          => 'Matching Issue',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => IssueScheduleStatus::ACTIVE->value,
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $planB->id,
            'issue_number'         => 1,
            'issue_title'          => 'Wrong Plan Issue',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => IssueScheduleStatus::ACTIVE->value,
        ]);

        $results = $this->repository->findFutureIssuesForPlan(
            $planA->id,
            new \DateTimeImmutable('2026-02-01 00:00:00'),
            10,
        );

        $this->assertCount(1, $results);
        $this->assertEquals($matching->id, $results->first()->id);
    }

    public function test_find_future_issues_for_plan_excludes_inactive_issues(): void
    {
        $plan = $this->createSubscriptionPlan();

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Draft Issue',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => IssueScheduleStatus::DRAFT->value,
        ]);

        $results = $this->repository->findFutureIssuesForPlan(
            $plan->id,
            new \DateTimeImmutable('2026-02-01 00:00:00'),
            10,
        );

        $this->assertCount(0, $results);
    }

    public function test_find_future_issues_for_plan_orders_by_on_sale_date_then_issue_number(): void
    {
        $plan = $this->createSubscriptionPlan();

        $second = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 2,
            'issue_title'          => 'Issue 2',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => IssueScheduleStatus::ACTIVE->value,
        ]);

        $first = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => IssueScheduleStatus::ACTIVE->value,
        ]);

        $earlier = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 99,
            'issue_title'          => 'Earlier Issue',
            'on_sale_date'         => '2026-02-15 00:00:00',
            'status'               => IssueScheduleStatus::ACTIVE->value,
        ]);

        $results = $this->repository->findFutureIssuesForPlan(
            $plan->id,
            new \DateTimeImmutable('2026-02-01 00:00:00'),
            10,
        );

        $this->assertEquals($earlier->id, $results->get(0)->id);
        $this->assertEquals($first->id, $results->get(1)->id);
        $this->assertEquals($second->id, $results->get(2)->id);
    }

    public function test_find_future_issues_for_plan_respects_limit(): void
    {
        $plan = $this->createSubscriptionPlan();

        for ($i = 1; $i <= 5; $i++) {
            IssueDelivery::create([
                'site_id'              => $this->siteId,
                'subscription_plan_id' => $plan->id,
                'issue_number'         => $i,
                'issue_title'          => "Issue {$i}",
                'on_sale_date'         => "2026-03-0{$i} 00:00:00",
                'status'               => IssueScheduleStatus::ACTIVE->value,
            ]);
        }

        $results = $this->repository->findFutureIssuesForPlan(
            $plan->id,
            new \DateTimeImmutable('2026-03-01 00:00:00'),
            3,
        );

        $this->assertCount(3, $results);
    }

    public function test_find_current_or_next_for_subscription_returns_earliest_open_delivery(): void
    {
        $subscription = $this->createSubscription();

        $later = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscription->id,
            'subscription_plan_id' => $subscription->plan_id,
            'issue_number'         => 2,
            'issue_title'          => 'Later Issue',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => 'scheduled',
        ]);

        $earlier = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscription->id,
            'subscription_plan_id' => $subscription->plan_id,
            'issue_number'         => 1,
            'issue_title'          => 'Earlier Issue',
            'on_sale_date'         => '2026-02-01 00:00:00',
            'status'               => 'pending',
        ]);

        $result = $this->repository->findCurrentOrNextForSubscription($subscription->id);

        $this->assertNotNull($result);
        $this->assertEquals($earlier->id, $result->id);
    }

    public function test_find_current_or_next_for_subscription_ignores_dispatched_deliveries(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscription->id,
            'subscription_plan_id' => $subscription->plan_id,
            'issue_number'         => 1,
            'issue_title'          => 'Dispatched Issue',
            'on_sale_date'         => '2026-02-01 00:00:00',
            'status'               => 'dispatched',
        ]);

        $open = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscription->id,
            'subscription_plan_id' => $subscription->plan_id,
            'issue_number'         => 2,
            'issue_title'          => 'Open Issue',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => 'scheduled',
        ]);

        $result = $this->repository->findCurrentOrNextForSubscription($subscription->id);

        $this->assertNotNull($result);
        $this->assertEquals($open->id, $result->id);
    }

    public function test_find_current_or_next_for_subscription_ignores_other_subscriptions(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();

        $matching = IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscriptionA->id,
            'subscription_plan_id' => $subscriptionA->plan_id,
            'issue_number'         => 1,
            'issue_title'          => 'Matching Issue',
            'on_sale_date'         => '2026-02-01 00:00:00',
            'status'               => 'scheduled',
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscriptionB->id,
            'subscription_plan_id' => $subscriptionB->plan_id,
            'issue_number'         => 1,
            'issue_title'          => 'Other Subscription Issue',
            'on_sale_date'         => '2026-01-01 00:00:00',
            'status'               => 'scheduled',
        ]);

        $result = $this->repository->findCurrentOrNextForSubscription($subscriptionA->id);

        $this->assertNotNull($result);
        $this->assertEquals($matching->id, $result->id);
    }

    public function test_find_current_or_next_for_subscription_returns_null_when_no_open_delivery_exists(): void
    {
        $subscription = $this->createSubscription();

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscription->id,
            'subscription_plan_id' => $subscription->plan_id,
            'issue_number'         => 1,
            'issue_title'          => 'Delivered Issue',
            'on_sale_date'         => '2026-02-01 00:00:00',
            'status'               => 'delivered',
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_id'      => $subscription->id,
            'subscription_plan_id' => $subscription->plan_id,
            'issue_number'         => 2,
            'issue_title'          => 'Dispatched Issue',
            'on_sale_date'         => '2026-03-01 00:00:00',
            'status'               => 'dispatched',
        ]);

        $result = $this->repository->findCurrentOrNextForSubscription($subscription->id);

        $this->assertNull($result);
    }


    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create a schedule-level IssueDelivery (subscription_plan_id set, subscription_id null).
     */
    private function makeScheduleIssue(int $planId, array $overrides = []): IssueDelivery
    {
        return IssueDelivery::create(array_merge([
            'subscription_plan_id'    => $planId,
            'subscription_id'         => null,
            'issue_number'            => 1,
            'issue_title'             => 'Test Issue',
            'on_sale_date'            => '2026-03-01',
            'estimated_delivery_date' => '2026-03-10',
            'status'                  => 'active',
            'issue_code'              => 'TST-001',
            'cut_off_date'            => '2026-02-20',
        ], $overrides));
    }

    public function test_find_future_issues_for_plan_starting_from_issue_includes_starting_issue(): void
    {
        $issue1 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $issue2 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
        ]);

        $issue3 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 3,
            'on_sale_date' => '2026-06-01 00:00:00',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $issue2->id,
            limit: 2,
        );

        $this->assertCount(2, $results);

        $this->assertEquals([
            $issue2->id,
            $issue3->id,
        ], $results->pluck('id')->all());
    }

    public function test_find_future_issues_for_plan_starting_from_issue_excludes_earlier_issues(): void
    {
        $issue1 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $issue2 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
        ]);

        $issue3 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 3,
            'on_sale_date' => '2026-06-01 00:00:00',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $issue2->id,
            limit: 10,
        );

        $ids = $results->pluck('id')->all();

        $this->assertNotContains($issue1->id, $ids);
        $this->assertContains($issue2->id, $ids);
        $this->assertContains($issue3->id, $ids);
    }

    public function test_find_future_issues_for_plan_starting_from_issue_only_returns_active_issues(): void
    {
        $startingIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
            'status'       => IssueScheduleStatus::ACTIVE->value,
        ]);

        $activeIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
            'status'       => IssueScheduleStatus::ACTIVE->value,
        ]);

        $inactiveIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 3,
            'on_sale_date' => '2026-06-01 00:00:00',
            'status'       => 'inactive',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $startingIssue->id,
            limit: 10,
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($startingIssue->id, $ids);
        $this->assertContains($activeIssue->id, $ids);
        $this->assertNotContains($inactiveIssue->id, $ids);
    }

    public function test_find_future_issues_for_plan_starting_from_issue_only_returns_issues_for_same_plan(): void
    {
        $startingIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $samePlanIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
        ]);

        $otherPlanIssue = $this->createScheduleIssue($this->otherPlan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $startingIssue->id,
            limit: 10,
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($startingIssue->id, $ids);
        $this->assertContains($samePlanIssue->id, $ids);
        $this->assertNotContains($otherPlanIssue->id, $ids);
    }

    public function test_find_future_issues_for_plan_starting_from_issue_respects_limit(): void
    {
        $startingIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $issue2 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
        ]);

        $issue3 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 3,
            'on_sale_date' => '2026-06-01 00:00:00',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $startingIssue->id,
            limit: 2,
        );

        $this->assertCount(2, $results);

        $this->assertEquals([
            $startingIssue->id,
            $issue2->id,
        ], $results->pluck('id')->all());
    }

    public function test_find_future_issues_for_plan_starting_from_issue_orders_by_on_sale_date_then_issue_number(): void
    {
        $startingIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $issue3 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 3,
            'on_sale_date' => '2026-05-01 00:00:00',
        ]);

        $issue2 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
        ]);

        $issue4 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 4,
            'on_sale_date' => '2026-06-01 00:00:00',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $startingIssue->id,
            limit: 10,
        );

        $this->assertEquals([
            $startingIssue->id,
            $issue2->id,
            $issue3->id,
            $issue4->id,
        ], $results->pluck('id')->all());
    }

    public function test_find_future_issues_for_plan_starting_from_issue_returns_empty_when_starting_issue_not_found(): void
    {
        $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: 999999,
            limit: 10,
        );

        $this->assertCount(0, $results);
    }

    public function test_find_future_issues_for_plan_starting_from_issue_returns_empty_when_starting_issue_belongs_to_different_plan(): void
    {
        $startingIssueOnOtherPlan = $this->createScheduleIssue($this->otherPlan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $startingIssueOnOtherPlan->id,
            limit: 10,
        );

        $this->assertCount(0, $results);
    }

    public function test_find_future_issues_for_plan_starting_from_issue_returns_empty_when_starting_issue_is_not_active(): void
    {
        $inactiveStartingIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-04-01 00:00:00',
            'status'       => 'inactive',
        ]);

        $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-05-01 00:00:00',
            'status'       => IssueScheduleStatus::ACTIVE->value,
        ]);

        $results = $this->repository->findFutureIssuesForPlanStartingFromIssue(
            subscriptionPlanId: $this->plan->id,
            startingIssueDeliveryId: $inactiveStartingIssue->id,
            limit: 10,
        );

        $this->assertCount(0, $results);
    }

    public function test_find_available_editions_for_subscription_plan_returns_active_future_issues_for_plan(): void
    {
        $issue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        $results = $this->repository->findAvailableEditionsForSubscriptionPlan(
            subscriptionPlanId: $this->plan->id,
            fromDate: new \DateTimeImmutable(),
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($issue->id, $ids);
    }

    public function test_find_available_editions_for_subscription_plan_excludes_issues_from_other_plans(): void
    {
        $matchingIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        $otherPlanIssue = $this->createScheduleIssue($this->otherPlan->id, [
            'issue_number' => 2,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        $results = $this->repository->findAvailableEditionsForSubscriptionPlan(
            subscriptionPlanId: $this->plan->id,
            fromDate: new \DateTimeImmutable(),
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($matchingIssue->id, $ids);
        $this->assertNotContains($otherPlanIssue->id, $ids);
    }

    public function test_find_available_editions_for_subscription_plan_excludes_inactive_issues(): void
    {
        $activeIssue = $this->createScheduleIssue($this->plan->id, [
            'status' => IssueScheduleStatus::ACTIVE->value,
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        $inactiveIssue = $this->createScheduleIssue($this->plan->id, [
            'status' => 'inactive',
            'issue_number' => 2,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+2 months')),
        ]);

        $results = $this->repository->findAvailableEditionsForSubscriptionPlan(
            subscriptionPlanId: $this->plan->id,
            fromDate: new \DateTimeImmutable(),
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($activeIssue->id, $ids);
        $this->assertNotContains($inactiveIssue->id, $ids);
    }

    public function test_find_available_editions_for_subscription_plan_excludes_past_issues(): void
    {
        $pastIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-01-01 00:00:00',
        ]);

        $futureIssue = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-08-01 00:00:00',
        ]);

        $results = $this->repository->findAvailableEditionsForSubscriptionPlan(
            subscriptionPlanId: $this->plan->id,
            fromDate: new \DateTimeImmutable('2026-06-01 00:00:00'),
        );

        $ids = $results->pluck('id')->all();

        $this->assertNotContains($pastIssue->id, $ids);
        $this->assertContains($futureIssue->id, $ids);
    }

    public function test_find_available_editions_for_subscription_plan_orders_by_on_sale_date_then_issue_number(): void
    {
        $issue3 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 3,
            'on_sale_date' => '2026-08-01 00:00:00',
        ]);

        $issue2 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 2,
            'on_sale_date' => '2026-08-01 00:00:00',
        ]);

        $issue1 = $this->createScheduleIssue($this->plan->id, [
            'issue_number' => 1,
            'on_sale_date' => '2026-07-01 00:00:00',
        ]);

        $results = $this->repository->findAvailableEditionsForSubscriptionPlan(
            subscriptionPlanId: $this->plan->id,
            fromDate: new \DateTimeImmutable('2026-06-01 00:00:00'),
        );

        $this->assertEquals([
            $issue1->id,
            $issue2->id,
            $issue3->id,
        ], $results->pluck('id')->all());
    }

    public function test_find_available_editions_for_subscription_plan_returns_empty_collection_when_no_matches(): void
    {
        $this->createScheduleIssue($this->otherPlan->id, [
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        $results = $this->repository->findAvailableEditionsForSubscriptionPlan(
            subscriptionPlanId: $this->plan->id,
            fromDate: new \DateTimeImmutable(),
        );

        $this->assertCount(0, $results);
    }


    private function createScheduleIssue(
        int $subscriptionPlanId,
        array $overrides = [],
    ): Model {
        return $this->createIssueDelivery(array_merge([
            'subscription_plan_id'     => $subscriptionPlanId,
            'status'                   => IssueScheduleStatus::ACTIVE->value,
            'issue_number'             => 1,
            'on_sale_date'             => '2026-04-01 00:00:00',
            'estimated_delivery_date'  => '2026-04-08 00:00:00',
            'stock_quantity'           => 100,
            'created_at'               => date('Y-m-d H:i:s'),
            'updated_at'               => date('Y-m-d H:i:s'),
        ], $overrides));
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new IssueDeliveryRepository();

        $this->plan = $this->createSubscriptionPlan([
            'site_id'   => $this->siteId,
            'name'      => 'Main Plan',
            'is_active' => true,
        ]);

        $this->otherPlan = $this->createSubscriptionPlan([
            'site_id'   => $this->siteId,
            'name'      => 'Other Plan',
            'is_active' => true,
        ]);
    }
}