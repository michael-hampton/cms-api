<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\Site;
use App\Services\Subscriptions\SubscriptionAccountContext;
use App\Services\Subscriptions\SubscriptionAccountFaqProvider;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;
use App\Services\Subscriptions\SubscriptionListingService;
use App\Services\Subscriptions\SubscriptionPlanService;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountPageProviderTest extends TestCase
{
    public function test_press_stack_uses_global_listing_and_does_not_load_plans(): void
    {
        $listing = $this->createMock(SubscriptionListingService::class);
        $plans = $this->createMock(SubscriptionPlanService::class);

        $listing->expects(self::once())
            ->method('getGroupedSubscriptions')
            ->with(41, null)
            ->willReturn($this->emptyGroups());
        $listing->expects(self::once())
            ->method('getSubscriptionSummary')
            ->with(41, null)
            ->willReturn(['total' => 0]);
        $plans->expects(self::never())->method('getActivePlansForSite');

        $result = $this->provider($listing, $plans)->forMember(
            41,
            null,
            SubscriptionAccountContext::pressStack(),
        );

        self::assertFalse($result['account_context']['is_site_scoped']);
        self::assertFalse($result['account_context']['can_acquire_subscription']);
        self::assertSame([], $result['plans']);
        self::assertSame([], $result['subscription_modal_data']);
    }

    public function test_member_area_filters_by_site_and_loads_site_plans(): void
    {
        $listing = $this->createMock(SubscriptionListingService::class);
        $plans = $this->createMock(SubscriptionPlanService::class);
        $site = $this->createMock(Site::class);
        $sitePlans = new Collection(['annual-plan']);

        $listing->expects(self::once())
            ->method('getGroupedSubscriptions')
            ->with(41, 7)
            ->willReturn($this->emptyGroups());
        $listing->expects(self::once())
            ->method('getSubscriptionSummary')
            ->with(41, 7)
            ->willReturn(['total' => 0]);
        $plans->expects(self::once())
            ->method('getActivePlansForSite')
            ->with(7)
            ->willReturn($sitePlans);

        $result = $this->provider($listing, $plans)->forMember(
            41,
            7,
            SubscriptionAccountContext::memberArea($site, 'daily-news'),
        );

        self::assertTrue($result['account_context']['is_site_scoped']);
        self::assertTrue($result['account_context']['can_acquire_subscription']);
        self::assertSame('daily-news', $result['account_context']['site_slug']);
        self::assertSame($sitePlans, $result['plans']);
        self::assertSame($sitePlans, $result['subscription_modal_data']['plans']);
    }

    public function test_member_context_generates_complete_member_payload_directly(): void
    {
        $listing = $this->createMock(SubscriptionListingService::class);
        $plans = $this->createMock(SubscriptionPlanService::class);
        $site = $this->createMock(Site::class);
        $grouped = $this->emptyGroups();
        $grouped['current'][] = [
            'id' => 42,
            'actions' => [
                ['key' => 'renew', 'type' => 'redirect', 'url' => '/press-stack/account/subscriptions/42/renew'],
                ['key' => 'reactivate', 'type' => 'api', 'endpoint' => '/press-stack/account/subscriptions/42/reactivate'],
            ],
            'cancellation_flow' => [
                'endpoint' => '/press-stack/account/subscriptions/42/cancel',
                'effective_date' => '30 Jun 2026',
            ],
            'account_management' => [],
        ];

        $listing->method('getGroupedSubscriptions')->willReturn($grouped);
        $listing->method('getSubscriptionSummary')->willReturn(['total' => 1]);
        $plans->method('getActivePlansForSite')->willReturn(new Collection());

        $result = $this->provider($listing, $plans)->forMember(
            41,
            7,
            SubscriptionAccountContext::memberArea($site, 'daily-news'),
        );

        $subscription = $result['grouped']['current'][0];
        self::assertSame(
            '/daily-news/member/subscriptions/unified/42/history',
            $subscription['account_management']['history_endpoint'],
        );
        self::assertSame(
            '/daily-news/member/subscriptions/unified/42/delivery/pause',
            $subscription['account_management']['delivery_pause_endpoint'],
        );
        self::assertSame(
            '/daily-news/member/subscriptions/unified/42/renew',
            $subscription['actions'][0]['url'],
        );
        self::assertSame(
            '/daily-news/member/subscriptions/unified/42/reactivate',
            $subscription['actions'][1]['endpoint'],
        );
        self::assertSame(
            '/daily-news/member/subscriptions/unified/42/cancel',
            $subscription['cancellation_flow']['endpoint'],
        );
    }

    public function test_global_context_rejects_a_site_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->provider(
            $this->createMock(SubscriptionListingService::class),
            $this->createMock(SubscriptionPlanService::class),
        )->forMember(41, 7, SubscriptionAccountContext::pressStack());
    }

    public function test_member_context_requires_a_site_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->provider(
            $this->createMock(SubscriptionListingService::class),
            $this->createMock(SubscriptionPlanService::class),
        )->forMember(
            41,
            null,
            SubscriptionAccountContext::memberArea($this->createMock(Site::class), 'daily-news'),
        );
    }

    private function provider(
        SubscriptionListingService $listing,
        SubscriptionPlanService $plans,
    ): SubscriptionAccountPageProvider {
        return new SubscriptionAccountPageProvider(
            $listing,
            $plans,
            new SubscriptionAccountFaqProvider(),
        );
    }

    private function emptyGroups(): array
    {
        return [
            'current' => [],
            'action_required' => [],
            'previous' => [],
        ];
    }
}
