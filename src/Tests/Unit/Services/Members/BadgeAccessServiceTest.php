<?php

namespace App\Tests\Unit\Services\Members;

use App\Models\Member;
use App\Models\Site;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Members\BadgeAccessService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class BadgeAccessServiceTest extends TestCase
{
    private SubscriptionRepository $subscriptions;
    private BadgeAccessService $service;

    public function test_allows_site_member_when_subscription_gate_is_disabled(): void
    {
        $member = $this->member(siteId: 10);
        $site = $this->site(id: 10, requiresSubscription: false);

        $this->subscriptions->shouldNotReceive('getActiveSubscriptionForMember');

        $this->assertTrue($this->service->canAccessBadges($member, $site));
    }

    public function test_denies_wrong_site_member_even_when_subscription_gate_is_disabled(): void
    {
        $member = $this->member(siteId: 99);
        $site = $this->site(id: 10, requiresSubscription: false);

        $this->subscriptions->shouldNotReceive('getActiveSubscriptionForMember');

        $this->assertFalse($this->service->canAccessBadges($member, $site));
    }

    public function test_denies_site_member_without_subscription_when_gate_is_enabled(): void
    {
        $member = $this->member(id: 5, siteId: 10);
        $site = $this->site(id: 10, requiresSubscription: true);

        $this->subscriptions->shouldReceive('getActiveSubscriptionForMember')
            ->with(5, 10, false)
            ->once()
            ->andReturn(null);

        $this->assertFalse($this->service->canAccessBadges($member, $site));
    }

    public function test_allows_site_member_with_active_subscription_when_gate_is_enabled(): void
    {
        $member = $this->member(id: 5, siteId: 10);
        $site = $this->site(id: 10, requiresSubscription: true);
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $this->subscriptions->shouldReceive('getActiveSubscriptionForMember')
            ->with(5, 10, false)
            ->once()
            ->andReturn($subscription);

        $this->assertTrue($this->service->canAccessBadges($member, $site));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptions = Mockery::mock(SubscriptionRepository::class);
        $this->service = new BadgeAccessService($this->subscriptions);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function member(int $id = 1, int $siteId = 10): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        $member->site_id = $siteId;

        return $member;
    }

    private function site(int $id, bool $requiresSubscription): Site
    {
        $site = Mockery::mock(Site::class)->makePartial();

        $site->id = $id;
        $site->settings = [
            BadgeAccessService::REQUIRE_ACTIVE_SUBSCRIPTION_SETTING => $requiresSubscription,
        ];

        return $site;
    }
}
