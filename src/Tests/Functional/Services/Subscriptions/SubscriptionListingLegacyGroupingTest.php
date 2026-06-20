<?php

namespace App\Tests\Functional\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Subscription;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionListingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class SubscriptionListingLegacyGroupingTest extends FunctionalTestCase
{
    use CreatesTestData;

    private SubscriptionListingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SubscriptionListingService(
            new SubscriptionRepository(),
            new NewsletterRepository(),
        );
    }

    public function test_action_required_and_paused_subscriptions_remain_in_legacy_active_bucket(): void
    {
        $member = $this->createMember();

        $this->createSubscription($member->id, 'paused');
        $this->createSubscription($member->id, 'past_due');
        $this->createSubscription($member->id, 'pending');

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        self::assertCount(3, $grouped['active'][SubscriptionType::DIGITAL->value]);
        self::assertCount(0, $grouped['expired'][SubscriptionType::DIGITAL->value]);
        self::assertCount(1, $grouped['current']);
        self::assertCount(2, $grouped['action_required']);
    }

    public function test_only_previous_subscriptions_are_added_to_legacy_expired_bucket(): void
    {
        $member = $this->createMember();

        $this->createSubscription($member->id, 'expired', '-1 day');
        $this->createSubscription($member->id, 'replaced', '-1 day');

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        self::assertCount(0, $grouped['active'][SubscriptionType::DIGITAL->value]);
        self::assertCount(2, $grouped['expired'][SubscriptionType::DIGITAL->value]);
        self::assertCount(2, $grouped['previous']);
    }

    private function createSubscription(
        int $memberId,
        string $status,
        string $endDate = '+1 month',
    ): Subscription {
        return Subscription::create([
            'member_id' => $memberId,
            'site_id' => $this->siteId,
            'plan_name' => ucfirst(str_replace('_', ' ', $status)),
            'status' => $status,
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'end_date' => date('Y-m-d H:i:s', strtotime($endDate)),
            'pause_until' => $status === 'paused'
                ? date('Y-m-d H:i:s', strtotime('+2 weeks'))
                : null,
            'price' => 10,
            'currency' => 'GBP',
        ]);
    }
}
