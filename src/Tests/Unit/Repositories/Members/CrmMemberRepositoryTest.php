<?php

namespace App\Tests\Unit\Repositories\Members;

use App\Repositories\Members\CrmMemberRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class CrmMemberRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private CrmMemberRepository $repository;

    public function test_get_recent_orders_for_member_filters_by_site_and_member(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();

        $latest = $this->createOrder([
            'site_id' => $this->siteId,
            'user_id' => $member->id,
            'order_number' => 'CRM-RECENT-2',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->createOrder([
            'site_id' => $this->siteId,
            'user_id' => $member->id,
            'order_number' => 'CRM-RECENT-1',
            'created_at' => now_datetime()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $this->createOrder([
            'site_id' => $this->siteId,
            'user_id' => $otherMember->id,
            'order_number' => 'CRM-OTHER-MEMBER',
        ]);

        $orders = $this->repository->getRecentOrdersForMember($member->id, $this->siteId, 5);

        $this->assertCount(2, $orders);
        $this->assertSame($latest->id, $orders->first()->id);
    }

    public function test_get_order_summary_for_member_returns_count_and_total(): void
    {
        $member = $this->createMember();

        $this->createOrder([
            'site_id' => $this->siteId,
            'user_id' => $member->id,
            'total' => 12.50,
        ]);
        $this->createOrder([
            'site_id' => $this->siteId,
            'user_id' => $member->id,
            'total' => 7.25,
        ]);

        $summary = $this->repository->getOrderSummaryForMember($member->id, $this->siteId);

        $this->assertSame(2, $summary['count']);
        $this->assertSame(19.75, $summary['total']);
    }

    public function test_get_recent_subscriptions_for_member_returns_latest_first(): void
    {
        $member = $this->createMember();

        $older = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Starter',
            'created_at' => now_datetime()->subDays(3)->format('Y-m-d H:i:s'),
        ]);

        $latest = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $subscriptions = $this->repository->getRecentSubscriptionsForMember($member->id, $this->siteId, 5);

        $this->assertCount(2, $subscriptions);
        $this->assertSame($latest->id, $subscriptions->first()->id);
        $this->assertNotSame($older->id, $subscriptions->first()->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CrmMemberRepository();
    }
}
