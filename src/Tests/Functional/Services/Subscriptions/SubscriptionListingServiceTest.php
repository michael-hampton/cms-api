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
    private SubscriptionPauseService $pauseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pauseService = $this->createMock(SubscriptionPauseService::class);
        $this->pauseService
            ->method('canPauseSubscription')
            ->willReturnCallback(
                static fn(Subscription $subscription, int $memberId): bool =>
                    (int)$subscription->member_id === $memberId
                    && $subscription->status === 'active'
                    && !$subscription->isCancellationScheduled()
            );
        $this->pauseService
            ->method('canResumeSubscription')
            ->willReturnCallback(
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
            $this->pauseService,
        );
    }

    public function test_groups_subscriptions_by_new_and_legacy_contracts(): void
    {
        $member = $this->createMember();

        $this->createSubscription(
            $member->id,
            'Current',
            'active',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );
        $this->createSubscription(
            $member->id,
            'Payment Due',
            'past_due',
            SubscriptionType::DIGITAL->value,
            '+1 month'
        );
        $this->createSubscription(
            $member->id,
            'Expired',
            'expired',
            SubscriptionType::PRINTED->value,
            '-1 month'
        );

        $grouped = $this->service->getGroupedSubscriptions(
            $member->id,
            $this->siteId
        );

        $this->assertCount(1, $grouped['current']);
        $this->assertCount(1, $grouped['action_required']);
        $this->assertCount(1, $grouped['previous']);

        $this->assertCount(
            2,
            $grouped['active'][SubscriptionType::DIGITAL->value]
        );
        $this->assertCount(
            1,
            $grouped['expired'][SubscriptionType::PRINTED->value]
        );
    }

    public function test_active_subscription_preserves_compatibility_fields(): void
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

        $this->assertTrue($formatted['is_current']);
        $this->assertTrue($formatted['is_active']);
        $this->assertFalse($formatted['can_renew']);
        $this->assertFalse($formatted['should_show_renew']);
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

    public function test_paused_subscription_exposes_resume_and_cancel_actions(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Paused Digital',
            'paused',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );

        $formatted = $this->service->formatSubscriptionForListing($subscription);
        $actionKeys = array_column($formatted['actions'], 'key');

        $this->assertContains('resume', $actionKeys);
        $this->assertContains('cancel', $actionKeys);
        $this->assertNotContains('pause', $actionKeys);
    }

    public function test_expiring_subscription_exposes_renew_action_and_legacy_flags(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Manual Renewal',
            'active',
            SubscriptionType::PRINTED->value,
            '+20 days',
            false
        );

        $formatted = $this->service->formatSubscriptionForListing($subscription);

        $this->assertSame('expiring_soon', $formatted['display_state']['key']);
        $this->assertContains('renew', array_column($formatted['actions'], 'key'));
        $this->assertTrue($formatted['can_renew']);
        $this->assertTrue($formatted['should_show_renew']);
    }

    public function test_expired_subscription_exposes_resubscribe_action(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Expired Plan',
            'expired',
            SubscriptionType::DIGITAL->value,
            '-1 month'
        );

        $formatted = $this->service->formatSubscriptionForListing($subscription);

        $this->assertContains(
            'resubscribe',
            array_column($formatted['actions'], 'key')
        );
        $this->assertTrue($formatted['can_renew']);
        $this->assertFalse($formatted['should_show_renew']);
    }

    public function test_summary_returns_current_and_active_alias(): void
    {
        $member = $this->createMember();

        $this->createSubscription(
            $member->id,
            'Current',
            'active',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );
        $this->createSubscription(
            $member->id,
            'Expired',
            'expired',
            SubscriptionType::PRINTED->value,
            '-1 month'
        );

        $summary = $this->service->getSubscriptionSummary(
            $member->id,
            $this->siteId
        );

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['current']);
        $this->assertSame(1, $summary['active']);
        $this->assertSame(1, $summary['previous']);
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
