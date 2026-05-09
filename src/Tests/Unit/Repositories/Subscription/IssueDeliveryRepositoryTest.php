<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Exceptions\Stock\StockException;
use App\Models\IssueDelivery;
use App\Models\Model;
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
        \App\Framework\Database\Database::table('issues_delivered')->insert([
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new IssueDeliveryRepository();
    }
}