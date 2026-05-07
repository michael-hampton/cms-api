<?php

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\PaymentFailed;
use App\Events\Subscriptions\PaymentRefunded;
use App\Events\Subscriptions\PaymentSucceeded;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionCreated;
use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionReactivated;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Services\Subscriptions\SubscriptionHistoryService;

/**
 * Writes a history entry to subscription_events for every subscription
 * lifecycle event fired in the domain.
 *
 * One listener handles all event types so the history recording concern
 * stays in one place. Each handle* method maps an event to an event_type
 * string and a metadata array consumed by the frontend timeline.
 *
 * Register in your EventServiceProvider:
 *
 *   SubscriptionCreated::class    => [RecordSubscriptionHistoryListener::class],
 *   SubscriptionCancelled::class  => [RecordSubscriptionHistoryListener::class],
 *   SubscriptionReactivated::class => [RecordSubscriptionHistoryListener::class],
 *   SubscriptionPaused::class     => [RecordSubscriptionHistoryListener::class],
 *   SubscriptionResumed::class    => [RecordSubscriptionHistoryListener::class],
 *   PaymentSucceeded::class       => [RecordSubscriptionHistoryListener::class],
 *   PaymentFailed::class          => [RecordSubscriptionHistoryListener::class],
 *   PaymentRefunded::class        => [RecordSubscriptionHistoryListener::class],
 */
class RecordSubscriptionHistoryListener
{
    public function __construct(
        private readonly SubscriptionHistoryService $historyService,
    )
    {
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscriptionId,
            eventType: 'subscription.created',
            metadata: [
                'plan_id' => $event->planId,
                'billing_period' => $event->billingPeriod,
                'amount' => $event->priceCents,
                'currency' => $event->currency,
            ],
        );
    }

    public function handleSubscriptionCancelled(SubscriptionCancelled $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscriptionId,
            eventType: 'subscription.cancelled',
            metadata: [
                'cancel_at_period_end' => $event->cancelAtPeriodEnd,
                'end_date' => $event->endDate,
            ],
        );
    }

    public function handleSubscriptionReactivated(SubscriptionReactivated $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscriptionId,
            eventType: 'subscription.reactivated',
            metadata: [
                'days_remaining' => $event->daysRemaining,
            ],
        );
    }

    public function handleSubscriptionPaused(SubscriptionPaused $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscription->id,
            eventType: 'subscription.paused',
            metadata: [
                'pause_start' => $event->pauseStart,
                'pause_end' => $event->pausedUntil,
                'duration_months' => $event->durationMonths,
                'reason' => $event->reason,
            ],
        );
    }

    public function handleSubscriptionResumed(SubscriptionResumed $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscription->id,
            eventType: 'subscription.resumed',
        );
    }

    public function handlePaymentSucceeded(PaymentSucceeded $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscriptionId,
            eventType: 'payment.succeeded',
            metadata: [
                'payment_id' => $event->paymentId,
                'amount' => $event->amountCents,
                'currency' => $event->currency,
            ],
        );
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscriptionId,
            eventType: 'payment.failed',
            metadata: [
                'payment_id' => $event->paymentId,
                'amount' => $event->amountCents,
                'currency' => $event->currency,
                'failure_reason' => $event->failureReason,
            ],
        );
    }

    public function handlePaymentRefunded(PaymentRefunded $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->subscriptionId,
            eventType: 'payment.refunded',
            metadata: [
                'payment_id' => $event->paymentId,
                'amount' => $event->amountCents,
                'currency' => $event->currency,
                'reason' => $event->reason,
            ],
        );
    }
}