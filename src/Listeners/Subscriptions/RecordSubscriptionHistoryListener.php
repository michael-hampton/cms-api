<?php

namespace App\Listeners\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
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
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionHistoryService;

class RecordSubscriptionHistoryListener
{
    public function __construct(
        private readonly SubscriptionHistoryService $historyService,
        private readonly SubscriptionRepository $subscriptionRepository,
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

    public function handleSubscriptionRenewedAndReplaced(SubscriptionRenewedAndReplaced $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->oldSubscriptionId,
            eventType: 'subscription.replaced',
            metadata: [
                'replaced_by_subscription_id' => $event->newSubscriptionId,
                'reason' => 'renewal',
            ],
            occurredAt: $event->timestamp,
        );

        $this->historyService->record(
            subscriptionId: $event->newSubscriptionId,
            eventType: 'subscription.renewed',
            metadata: [
                'renewed_from_subscription_id' => $event->oldSubscriptionId,
                'product_id' => $event->productId,
                'plan_id' => $event->planId,
                'amount_paid' => $event->amountPaid,
                'agent_id' => $event->agentId,
            ],
            occurredAt: $event->timestamp,
        );
    }

    public function handleSubscriptionProductChanged(SubscriptionProductChanged $event): void
    {
        $this->historyService->record(
            subscriptionId: $event->oldSubscriptionId,
            eventType: 'subscription.replaced',
            metadata: [
                'replaced_by_subscription_id' => $event->newSubscriptionId,
                'reason' => 'product_change',
            ],
            occurredAt: $event->timestamp,
        );

        $this->historyService->record(
            subscriptionId: $event->newSubscriptionId,
            eventType: 'subscription.product_changed',
            metadata: [
                'switched_from_subscription_id' => $event->oldSubscriptionId,
                'old_plan_id' => $event->oldPlanId,
                'new_plan_id' => $event->newPlanId,
                'switch_mode' => $event->switchMode,
                'carried_over_credit' => $event->carriedOverCredit,
                'agent_id' => $event->agentId,
            ],
            occurredAt: $event->timestamp,
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

        $this->finaliseResubscribeLink($event->subscriptionId);
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

    private function finaliseResubscribeLink(int $subscriptionId): void
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);
        $sourceId = (int) ($subscription?->renewed_from_subscription_id ?? 0);

        if (!$subscription || $sourceId <= 0) {
            return;
        }

        $source = $this->subscriptionRepository->find($sourceId);

        if (!$source) {
            return;
        }

        if ((int) $source->member_id !== (int) $subscription->member_id) {
            return;
        }

        if ((int) $source->site_id !== (int) $subscription->site_id) {
            return;
        }

        if ((int) $source->plan_id !== (int) $subscription->plan_id) {
            return;
        }

        $this->subscriptionRepository->update($source->id, [
            'status' => SubscriptionStatus::REPLACED->value,
            'replaced_by_subscription_id' => $subscription->id,
        ]);
    }
}
