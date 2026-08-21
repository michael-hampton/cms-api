<?php

namespace App\Tests\Unit\Services\Subscriptions;

// Import the new repositories
use App\Framework\Support\Collection;
use App\Models\Site;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Repositories\Subscriptions\SubscriptionAccountModalPlanRepository;
use App\Repositories\Subscriptions\SubscriptionAccountSiteRepository;
use App\Services\Subscriptions\SubscriptionAccountContext;
use App\Services\Subscriptions\SubscriptionAccountFaqProvider;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;
use App\Services\Subscriptions\SubscriptionListingService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountPageProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_press_stack_uses_global_listing_and_does_not_load_plans(): void
    {
        $listing = Mockery::mock(SubscriptionListingService::class);
        $modalPlanRepository = Mockery::mock(SubscriptionAccountModalPlanRepository::class);
        $siteRepository = Mockery::mock(SubscriptionAccountSiteRepository::class);

        $listing->shouldReceive('getGroupedSubscriptions')
            ->once()
            ->with(41, null)
            ->andReturn($this->emptyGroups());
        $listing->shouldReceive('getSubscriptionSummary')
            ->once()
            ->with(41, null)
            ->andReturn(['total' => 0]);

        // Since pressStack sets canAcquireSubscription to false, modal plans are never fetched
        $modalPlanRepository->shouldNotReceive('findForAccountModal');

        // Because it's a global context, it will try to resolve sites for the listings (empty in this case)
        $siteRepository->shouldReceive('findByIdsIndexed')
            ->once()
            ->with([])
            ->andReturn([]);

        $result = $this->provider($listing, $modalPlanRepository, $siteRepository)->forMember(
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
        $listing = Mockery::mock(SubscriptionListingService::class);
        $modalPlanRepository = Mockery::mock(SubscriptionAccountModalPlanRepository::class);
        $siteRepository = Mockery::mock(SubscriptionAccountSiteRepository::class);
        $site = Mockery::mock(Site::class)->shouldIgnoreMissing();
        $sitePlans = new Collection(['annual-plan']);

        $listing->shouldReceive('getGroupedSubscriptions')
            ->once()
            ->with(41, 7)
            ->andReturn($this->emptyGroups());
        $listing->shouldReceive('getSubscriptionSummary')
            ->once()
            ->with(41, 7)
            ->andReturn(['total' => 0]);

        // Member scoped calls the new repository method directly
        $modalPlanRepository->shouldReceive('findForAccountModal')
            ->once()
            ->with([7], [])
            ->andReturn($sitePlans);

        // Site-scoped workflows skip loading global cross-site records
        $siteRepository->shouldNotReceive('findByIdsIndexed');

        $result = $this->provider($listing, $modalPlanRepository, $siteRepository)->forMember(
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
        $listing = Mockery::mock(SubscriptionListingService::class);
        $modalPlanRepository = Mockery::mock(SubscriptionAccountModalPlanRepository::class);
        $siteRepository = Mockery::mock(SubscriptionAccountSiteRepository::class);
        $site = Mockery::mock(Site::class)->shouldIgnoreMissing();

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

        $listing->shouldReceive('getGroupedSubscriptions')->andReturn($grouped);
        $listing->shouldReceive('getSubscriptionSummary')->andReturn(['total' => 1]);
        $modalPlanRepository->shouldReceive('findForAccountModal')->andReturn(new Collection());

        $result = $this->provider($listing, $modalPlanRepository, $siteRepository)->forMember(
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
            Mockery::mock(SubscriptionListingService::class),
            Mockery::mock(SubscriptionAccountModalPlanRepository::class),
            Mockery::mock(SubscriptionAccountSiteRepository::class),
        )->forMember(41, 7, SubscriptionAccountContext::pressStack());
    }

    public function test_member_context_requires_a_site_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->provider(
            Mockery::mock(SubscriptionListingService::class),
            Mockery::mock(SubscriptionAccountModalPlanRepository::class),
            Mockery::mock(SubscriptionAccountSiteRepository::class),
        )->forMember(
            41,
            null,
            SubscriptionAccountContext::memberArea(Mockery::mock(Site::class)->shouldIgnoreMissing(), 'daily-news'),
        );
    }

    // Adjusted to align with the new structural layout of the constructor
    private function provider(
        SubscriptionListingService $listing,
        SubscriptionAccountModalPlanRepository $modalPlanRepository,
        SubscriptionAccountSiteRepository $siteRepository,
        ?CancellationReasonRepository $cancellationReasonRepository = null,
    ): SubscriptionAccountPageProvider {
        if ($cancellationReasonRepository === null) {
            $cancellationReasonRepository = Mockery::mock(CancellationReasonRepository::class);
            $cancellationReasonRepository
                ->shouldReceive('listActive')
                ->andReturn(new Collection());
        }

        return new SubscriptionAccountPageProvider(
            $listing,
            new SubscriptionAccountFaqProvider(),
            $modalPlanRepository,
            $siteRepository,
            $cancellationReasonRepository,
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