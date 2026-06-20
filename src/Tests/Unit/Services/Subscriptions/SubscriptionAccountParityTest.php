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

final class SubscriptionAccountParityTest extends TestCase
{
    public function test_contexts_preserve_shared_card_data_and_only_change_contextual_urls(): void
    {
        $listing = $this->createMock(SubscriptionListingService::class);
        $plans = $this->createMock(SubscriptionPlanService::class);
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
        $plans->method('getActivePlansForSite')->willReturn(new Collection());

        $provider = new SubscriptionAccountPageProvider($listing, $plans, new SubscriptionAccountFaqProvider());
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
