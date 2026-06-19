<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Models\SubscriptionPremiumAccess;
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

    public function test_get_grouped_subscriptions_uses_current_action_required_and_previous_groups(): void
    {
        $member = $this->createMember();

        $this->createSubscription($member->id, 'Current Print', 'active', SubscriptionType::PRINTED->value, '+6 months');
        $this->createSubscription($member->id, 'Payment Due', 'past_due', SubscriptionType::DIGITAL->value, '+1 month');
        $this->createSubscription($member->id, 'Old Plan', 'expired', SubscriptionType::PRINTED->value, '-1 month');

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertArrayHasKey('current', $grouped);
        $this->assertArrayHasKey('action_required', $grouped);
        $this->assertArrayHasKey('previous', $grouped);
        $this->assertCount(1, $grouped['current']);
        $this->assertCount(1, $grouped['action_required']);
        $this->assertCount(1, $grouped['previous']);
        $this->assertArrayNotHasKey('active', $grouped);
        $this->assertArrayNotHasKey('expired', $grouped);
    }

    public function test_formatted_subscription_exposes_backend_driven_contract_without_legacy_flags(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Print Annual',
            'active',
            SubscriptionType::PRINTED->value,
            '+1 year'
        );

        $formatted = $this->service->formatSubscriptionForListing($subscription);
        $actionKeys = array_column($formatted['actions'], 'key');

        $this->assertSame('active', $formatted['display_state']['key']);
        $this->assertSame('current', $formatted['display_state']['group']);
        $this->assertTrue($formatted['is_current']);
        $this->assertArrayNotHasKey('is_active', $formatted);
        $this->assertArrayNotHasKey('can_renew', $formatted);
        $this->assertArrayNotHasKey('should_show_renew', $formatted);
        $this->assertContains('pause', $actionKeys);
        $this->assertContains('cancel', $actionKeys);
    }

    public function test_active_non_stripe_subscription_gets_pause_action(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Pausable Plan',
            'active',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );

        $pause = $this->action($this->service->formatSubscriptionForListing($subscription), 'pause');

        $this->assertSame('Pause', $pause['label']);
        $this->assertSame('api', $pause['type']);
        $this->assertStringEndsWith('/pause', $pause['endpoint']);
    }

    public function test_stripe_backed_subscription_does_not_get_unsafe_pause_action(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Stripe Plan',
            'active',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );
        $subscription->stripe_subscription_id = 'sub_123';

        $formatted = $this->service->formatSubscriptionForListing($subscription);

        $this->assertNotContains('pause', array_column($formatted['actions'], 'key'));
    }

    public function test_paused_subscription_gets_resume_action_and_paused_state(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Paused Plan',
            'paused',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );
        $subscription->update([
            'paused_at' => date('Y-m-d H:i:s'),
            'pause_until' => date('Y-m-d', strtotime('+30 days')),
        ]);
        $subscription = Subscription::find($subscription->id);

        $formatted = $this->service->formatSubscriptionForListing($subscription);
        $resume = $this->action($formatted, 'resume');

        $this->assertSame('paused', $formatted['display_state']['key']);
        $this->assertSame('current', $formatted['display_state']['group']);
        $this->assertSame('Resume now', $resume['label']);
        $this->assertSame('api', $resume['type']);
        $this->assertStringEndsWith('/resume', $resume['endpoint']);
        $this->assertNotContains('pause', array_column($formatted['actions'], 'key'));
        $this->assertContains('cancel', array_column($formatted['actions'], 'key'));
    }

    public function test_expiring_subscription_gets_renew_action_from_continuation_resolver(): void
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
    }

    public function test_replaced_subscription_is_previous_and_not_renewal_offer_accepted(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(
            $member->id,
            'Previous Annual',
            'replaced',
            SubscriptionType::DIGITAL->value,
            '-1 day'
        );

        $formatted = $this->service->formatSubscriptionForListing($subscription);

        $this->assertSame('replaced', $formatted['display_state']['key']);
        $this->assertSame('previous', $formatted['display_state']['group']);
    }

    public function test_formatted_subscription_includes_newsletter_and_archive_benefits(): void
    {
        $member = $this->createMember();

        Newsletter::create([
            'title' => 'Insider Newsletter',
            'slug' => 'insider',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}',
        ]);

        $subscription = $this->createSubscription(
            $member->id,
            'Premium Digital',
            'active',
            SubscriptionType::DIGITAL->value,
            '+1 year'
        );

        SubscriptionPremiumAccess::create([
            'subscription_id' => $subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'is_active' => true,
            'granted_at' => date('Y-m-d H:i:s'),
        ]);

        $formatted = $this->service->formatSubscriptionForListing($subscription);

        $this->assertCount(1, $formatted['newsletters']);
        $this->assertSame('Insider Newsletter', $formatted['newsletters'][0]['title']);
        $this->assertNotNull($formatted['archive_url']);
        $this->assertContains('Archive access', array_column($formatted['benefits'], 'label'));
    }

    public function test_summary_counts_display_groups(): void
    {
        $member = $this->createMember();
        $this->createSubscription($member->id, 'Current', 'active', SubscriptionType::DIGITAL->value, '+1 year');
        $this->createSubscription($member->id, 'Payment Due', 'past_due', SubscriptionType::DIGITAL->value, '+1 month');
        $this->createSubscription($member->id, 'Expired', 'expired', SubscriptionType::PRINTED->value, '-1 month');

        $summary = $this->service->getSubscriptionSummary($member->id, $this->siteId);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['current']);
        $this->assertSame(1, $summary['action_required']);
        $this->assertSame(1, $summary['previous']);
        $this->assertSame(1, $summary['expired']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $paymentRepository = new PaymentRepository();
        $pauseService = $this->createMock(SubscriptionPauseService::class);
        $pauseService->method('canPauseSubscription')->willReturnCallback(
            static fn(Subscription $subscription, int $memberId): bool =>
                (int)$subscription->member_id === $memberId
                && $subscription->status === 'active'
                && !$subscription->hasStripeSubscription()
        );
        $pauseService->method('canResumeSubscription')->willReturnCallback(
            static fn(Subscription $subscription, int $memberId): bool =>
                (int)$subscription->member_id === $memberId
                && $subscription->status === 'paused'
                && !$subscription->hasStripeSubscription()
        );

        $this->service = new SubscriptionListingService(
            new SubscriptionRepository(),
            new NewsletterRepository(),
            new SubscriptionAccountStateResolver(),
            new SubscriptionContinuationResolver(),
            new SubscriptionCancellationFlowProvider(),
            new SubscriptionPaymentRecoveryService(
                $paymentRepository,
                new SubscriptionInvoiceGateway()
            ),
            $pauseService,
        );
    }

    private function action(array $formatted, string $key): array
    {
        foreach ($formatted['actions'] as $action) {
            if (($action['key'] ?? null) === $key) {
                return $action;
            }
        }

        $this->fail("Action {$key} was not found.");
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
