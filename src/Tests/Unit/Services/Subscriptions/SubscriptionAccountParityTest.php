<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\Site;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Repositories\Subscriptions\SubscriptionAccountModalPlanRepository;
use App\Repositories\Subscriptions\SubscriptionAccountSiteRepository;
use App\Services\Subscriptions\SubscriptionAccountContext;
use App\Services\Subscriptions\SubscriptionAccountFaqProvider;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;
use App\Services\Subscriptions\SubscriptionListingService;
use PHPUnit\Framework\TestCase;

// <-- Add import

final class SubscriptionAccountParityTest extends TestCase
{
    public function test_contexts_preserve_shared_card_data_and_only_change_contextual_urls(): void
    {
        $listing = $this->createMock(SubscriptionListingService::class);
        $modalPlanRepository = $this->createMock(SubscriptionAccountModalPlanRepository::class);
        $siteRepository = $this->createMock(SubscriptionAccountSiteRepository::class);

        // Mock the cancellation reasons repository
        $cancellationReasonRepository = $this->createMock(CancellationReasonRepository::class);
        $cancellationReasonRepository->method('listActive')->willReturn(new Collection());

        $payload = [
            'id' => 42,
            'plan_name' => 'Premium Print',
            'type' => 'print',
            'display_state' => ['key' => 'active', 'group' => 'current', 'label' => 'Active'],
            'facts' => [['label' => 'Started', 'value' => '1 Jan 2026']],
            'benefits' => [['label' => 'Archive access', 'url' => '/archive']],
            'actions' => [],
            'account_management' => [
                'can_manage_auto_renew' => true,
                'can_manage_billing_date' => true,
                'can_upgrade' => true,
                'can_manage_delivery' => true,
            ],
        ];
        $grouped = ['current' => [$payload], 'action_required' => [], 'previous' => []];
        $listing->method('getGroupedSubscriptions')->willReturn($grouped);
        $listing->method('getSubscriptionSummary')->willReturn(['total' => 1]);

        // Mock the behaviors for the new repository interactions
        $modalPlanRepository->method('findForAccountModal')->willReturn(new Collection());
        $siteRepository->method('findByIdsIndexed')->willReturn([]);

        // Instantiate with all 5 dependencies
        $provider = new SubscriptionAccountPageProvider(
            $listing,
            new SubscriptionAccountFaqProvider(),
            $modalPlanRepository,
            $siteRepository,
            $cancellationReasonRepository,
        );

        $global = $provider->forMember(7, null, SubscriptionAccountContext::pressStack())['grouped']['current'][0];
        $member = $provider->forMember(
            7,
            3,
            SubscriptionAccountContext::memberArea($this->createMock(Site::class), 'daily-news'),
        )['grouped']['current'][0];

        foreach (['plan_name', 'type', 'display_state', 'facts', 'benefits'] as $key) {
            self::assertSame($global[$key], $member[$key]);
        }

        foreach (['can_manage_auto_renew', 'can_manage_billing_date', 'can_upgrade', 'can_manage_delivery'] as $key) {
            self::assertSame($global['account_management'][$key], $member['account_management'][$key]);
        }

        self::assertStringStartsWith('/press-stack/account/subscriptions/42', $global['account_management']['history_endpoint']);
        self::assertStringStartsWith('/daily-news/member/subscriptions/unified/42', $member['account_management']['history_endpoint']);
    }
}