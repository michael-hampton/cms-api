<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\PaymentFailed;
use App\Events\Subscriptions\PaymentRefunded;
use App\Events\Subscriptions\PaymentSucceeded;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionCreated;
use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionProductChanged;
use App\Events\Subscriptions\SubscriptionReactivated;
use App\Events\Subscriptions\SubscriptionRenewedAndReplaced;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Listeners\Subscriptions\RecordSubscriptionHistoryListener;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionHistoryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class RecordSubscriptionHistoryListenerTest extends TestCase
{
    private $historyService;
    private $subscriptionRepository;
    private RecordSubscriptionHistoryListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->historyService = Mockery::mock(SubscriptionHistoryService::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->listener = new RecordSubscriptionHistoryListener(
            $this->historyService,
            $this->subscriptionRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_subscription_created_records_history(): void
    {
        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                42,
                'subscription.created',
                [
                    'plan_id' => 7,
                    'billing_period' => 'annual',
                    'amount' => 4999,
                    'currency' => 'GBP',
                ],
            );

        $this->listener->handleSubscriptionCreated(new SubscriptionCreated(
            subscriptionId: 42,
            planId: 7,
            billingPeriod: 'annual',
            priceCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }

    public function test_subscription_cancelled_records_history(): void
    {
        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                 42,
                 'subscription.cancelled',
                 [
                    'cancel_at_period_end' => true,
                    'end_date' => '2026-06-01',
                ],
            );

        $this->listener->handleSubscriptionCancelled(new SubscriptionCancelled(
            subscriptionId: 42,
            cancelAtPeriodEnd: true,
            endDate: '2026-06-01',
        ));

        $this->assertTrue(true);
    }

    public function test_subscription_renewed_and_replaced_records_history_for_both_subscriptions(): void
    {
        // Regression coverage: this event was previously dispatched but had
        // no listener registered anywhere, so it never actually did
        // anything — no history was recorded for the renewal at all.
        $event = new SubscriptionRenewedAndReplaced(
            memberId: 10,
            oldSubscriptionId: 42,
            newSubscriptionId: 55,
            productId: 3,
            planId: 7,
            amountPaid: 49.99,
            timestamp: '2026-01-01 12:00:00',
            agentId: 99,
        );

        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                 42,
                 'subscription.replaced',
                 [
                    'replaced_by_subscription_id' => 55,
                    'reason' => 'renewal',
                ],
                 '2026-01-01 12:00:00',
            );

        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                 55,
                 'subscription.renewed',
                 [
                    'renewed_from_subscription_id' => 42,
                    'product_id' => 3,
                    'plan_id' => 7,
                    'amount_paid' => 49.99,
                    'agent_id' => 99,
                ],
                 '2026-01-01 12:00:00',
            );

        $this->listener->handleSubscriptionRenewedAndReplaced($event);

        $this->assertTrue(true);
    }

    public function test_subscription_product_changed_records_history_for_both_subscriptions(): void
    {
        // Regression coverage: same as SubscriptionRenewedAndReplaced —
        // this event was dispatched but never had a listener.
        $event = new SubscriptionProductChanged(
            memberId: 10,
            oldSubscriptionId: 44,
            newSubscriptionId: 66,
            oldPlanId: 5,
            newPlanId: 8,
            switchMode: 'transfer',
            carriedOverCredit: 4.50,
            agentId: 1,
            timestamp: '2026-02-01 09:30:00',
        );

        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                 44,
                 'subscription.replaced',
                 [
                    'replaced_by_subscription_id' => 66,
                    'reason' => 'product_change',
                ],
                 '2026-02-01 09:30:00',
            );

        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                 66,
                 'subscription.product_changed',
                 [
                    'switched_from_subscription_id' => 44,
                    'old_plan_id' => 5,
                    'new_plan_id' => 8,
                    'switch_mode' => 'transfer',
                    'carried_over_credit' => 4.50,
                    'agent_id' => 1,
                ],
                 '2026-02-01 09:30:00',
            );

        $this->listener->handleSubscriptionProductChanged($event);

        $this->assertTrue(true);
    }

    public function test_payment_failed_records_history(): void
    {
        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                 42,
                 'payment.failed',
                 [
                    'payment_id' => 900,
                    'amount' => 4999,
                    'currency' => 'GBP',
                    'failure_reason' => 'card_declined',
                ],
            );

        $this->listener->handlePaymentFailed(new PaymentFailed(
            subscriptionId: 42,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
            failureReason: 'card_declined',
        ));

        $this->assertTrue(true);
    }

    public function test_payment_refunded_records_history(): void
    {
        $this->historyService->shouldReceive('record')
            ->once()
            ->with(
                 42,
                 'payment.refunded',
                 [
                    'payment_id' => 900,
                    'amount' => 4999,
                    'currency' => 'GBP',
                    'reason' => 'customer_request',
                ],
            );

        $this->listener->handlePaymentRefunded(new PaymentRefunded(
            subscriptionId: 42,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
            reason: 'customer_request',
        ));

        $this->assertTrue(true);
    }

    private function subscription(array $attrs = []): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $attrs['id'] ?? 1;
        $subscription->member_id = $attrs['member_id'] ?? 10;
        $subscription->site_id = $attrs['site_id'] ?? 1;
        $subscription->plan_id = $attrs['plan_id'] ?? 7;
        $subscription->renewed_from_subscription_id = $attrs['renewed_from_subscription_id'] ?? null;

        return $subscription;
    }

    public function test_payment_succeeded_records_history_and_finalises_resubscribe_link(): void
    {
        // Regression coverage: finaliseResubscribeLink() previously used
        // static Subscription::find()/->update() calls, which meant this
        // whole method was untestable at the unit level (only via a real
        // DB / functional test). It's now injected via SubscriptionRepository.
        $newSubscription = $this->subscription(['id' => 55, 'renewed_from_subscription_id' => 42]);
        $oldSubscription = $this->subscription(['id' => 42]);

        $this->historyService->shouldReceive('record')->once();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(55)->andReturn($newSubscription);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(42)->andReturn($oldSubscription);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(42, [
                'status' => \App\Enums\Subscriptions\SubscriptionStatus::REPLACED->value,
                'replaced_by_subscription_id' => 55,
            ]);

        $this->listener->handlePaymentSucceeded(new PaymentSucceeded(
            subscriptionId: 55,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }

    public function test_finalise_resubscribe_link_does_nothing_when_subscription_not_found(): void
    {
        $this->historyService->shouldReceive('record')->once();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(55)->andReturn(null);
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->listener->handlePaymentSucceeded(new PaymentSucceeded(
            subscriptionId: 55,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }

    public function test_finalise_resubscribe_link_does_nothing_when_not_a_renewal(): void
    {
        $newSubscription = $this->subscription(['id' => 55, 'renewed_from_subscription_id' => null]);

        $this->historyService->shouldReceive('record')->once();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(55)->andReturn($newSubscription);
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->listener->handlePaymentSucceeded(new PaymentSucceeded(
            subscriptionId: 55,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }

    public function test_finalise_resubscribe_link_does_nothing_when_source_subscription_not_found(): void
    {
        $newSubscription = $this->subscription(['id' => 55, 'renewed_from_subscription_id' => 42]);

        $this->historyService->shouldReceive('record')->once();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(55)->andReturn($newSubscription);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(42)->andReturn(null);
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->listener->handlePaymentSucceeded(new PaymentSucceeded(
            subscriptionId: 55,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }

    public function test_finalise_resubscribe_link_does_nothing_when_source_belongs_to_different_member(): void
    {
        $newSubscription = $this->subscription(['id' => 55, 'member_id' => 10, 'renewed_from_subscription_id' => 42]);
        $oldSubscription = $this->subscription(['id' => 42, 'member_id' => 999]);

        $this->historyService->shouldReceive('record')->once();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(55)->andReturn($newSubscription);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(42)->andReturn($oldSubscription);
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->listener->handlePaymentSucceeded(new PaymentSucceeded(
            subscriptionId: 55,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }

    public function test_finalise_resubscribe_link_does_nothing_when_source_belongs_to_different_site(): void
    {
        $newSubscription = $this->subscription(['id' => 55, 'site_id' => 1, 'renewed_from_subscription_id' => 42]);
        $oldSubscription = $this->subscription(['id' => 42, 'site_id' => 2]);

        $this->historyService->shouldReceive('record')->once();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(55)->andReturn($newSubscription);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(42)->andReturn($oldSubscription);
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->listener->handlePaymentSucceeded(new PaymentSucceeded(
            subscriptionId: 55,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }

    public function test_finalise_resubscribe_link_does_nothing_when_source_belongs_to_different_plan(): void
    {
        $newSubscription = $this->subscription(['id' => 55, 'plan_id' => 7, 'renewed_from_subscription_id' => 42]);
        $oldSubscription = $this->subscription(['id' => 42, 'plan_id' => 8]);

        $this->historyService->shouldReceive('record')->once();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(55)->andReturn($newSubscription);
        $this->subscriptionRepository->shouldReceive('find')->once()->with(42)->andReturn($oldSubscription);
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->listener->handlePaymentSucceeded(new PaymentSucceeded(
            subscriptionId: 55,
            paymentId: 900,
            amountCents: 4999,
            currency: 'GBP',
        ));

        $this->assertTrue(true);
    }
}
