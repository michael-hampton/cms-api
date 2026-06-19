<?php

namespace App\Tests\Functional\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionAccountStateResolver;
use App\Services\Subscriptions\SubscriptionCancellationFlowProvider;
use App\Services\Subscriptions\SubscriptionContinuationResolver;
use App\Services\Subscriptions\SubscriptionInvoiceGateway;
use App\Services\Subscriptions\SubscriptionListingService;
use App\Services\Subscriptions\SubscriptionPauseService;
use App\Services\Subscriptions\SubscriptionPaymentRecoveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class SubscriptionListingServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private SubscriptionListingService $service;

    public function test_groups_subscriptions_by_account_state(): void
    {
        $member = $this->createMember();
        $this->createSubscription($member->id, 'Current', 'active', SubscriptionType::DIGITAL->value, '+1 year');
        $this->createSubscription($member->id, 'Payment Due', 'past_due', SubscriptionType::DIGITAL->value, '+1 month');
        $this->createSubscription($member->id, 'Expired', 'expired', SubscriptionType::PRINTED->value, '-1 month');

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertCount(1, $grouped['current']);
        $this->assertCount(1, $grouped['action_required']);
        $this->assertCount(1, $grouped['previous']);
    }

    public function test_active_subscription_exposes_pause_and_cancel_actions(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Annual Digital',
            'active',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );

        $formatted = $this->service->formatSubscriptionForListing($subscription);
        $actionKeys = array_column($formatted['actions'], 'key');

        $this->assertContains('pause', $actionKeys);
        $this->assertContains('cancel', $actionKeys);
    }

    public function test_summary_counts_display_groups(): void
    {
        $member = $this->createMember();
        $this->createSubscription($member->id, 'Current', 'active', SubscriptionType::DIGITAL->value, '+1 year');
        $this->createSubscription($member->id, 'Expired', 'expired', SubscriptionType::PRINTED->value, '-1 month');

        $summary = $this->service->getSubscriptionSummary($member->id, $this->siteId);

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['current']);
        $this->assertSame(1, $summary['previous']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $pauseService = $this->createMock(SubscriptionPauseService::class);
        $pauseService->method('canPauseSubscription')->willReturnCallback(
            static fn(Subscription $subscription, int $memberId): bool =>
                (int)$subscription->member_id === $memberId
                && $subscription->status === 'active'
                && !$subscription->isCancellationScheduled()
        );
        $pauseService->method('canResumeSubscription')->willReturnCallback(
            static fn(Subscription $subscription, int $memberId): bool =>
                (int)$subscription->member_id === $memberId
                && $subscription->status === 'paused'
        );

        $this->service = new SubscriptionListingService(
            new SubscriptionRepository(),
            new NewsletterRepository(),
            new SubscriptionAccountStateResolver(),
            new SubscriptionContinuationResolver(),
            new SubscriptionCancellationFlowProvider(),
            new SubscriptionPaymentRecoveryService(
                new PaymentRepository(),
                new SubscriptionInvoiceGateway()
            ),
            $pauseService,
        );
    }

    private function createSubscription(
        int $memberId,
        string $name,
        string $status,
        string $deliveryType,
        string $endModifier,
        bool $autoRenew = false,
    ): Subscription {
        return Subscription::create([
            'member_id' => $memberId,
            'site_id' => $this->siteId,
            'plan_name' => $name,
            'status' => $status,
            'delivery_type' => $deliveryType,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'end_date' => date('Y-m-d H:i:s', strtotime($endModifier)),
            'auto_renew' => $autoRenew,
            'cancel_at_period_end' => false,
            'price' => 25.00,
            'currency' => 'USD',
        ]);
    }
}
