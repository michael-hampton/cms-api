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
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;

final class SendSubscriptionLifecycleCommunicationListener
{
    private const KEY_SUBSCRIPTION_CREATED = 'acknowledgement_default';
    private const KEY_SUBSCRIPTION_CANCELLED = 'subscription_cancelled_default';
    private const KEY_SUBSCRIPTION_REACTIVATED = 'subscription_reactivated_default';
    private const KEY_SUBSCRIPTION_PAUSED = 'subscription_paused_default';
    private const KEY_SUBSCRIPTION_RESUMED = 'subscription_resumed_default';
    private const KEY_PAYMENT_SUCCEEDED = 'payment_succeeded_default';
    private const KEY_PAYMENT_FAILED = 'payment_failed_default';
    private const KEY_PAYMENT_REFUNDED = 'payment_refunded_default';

    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionCommunicationRepository $communications,
        private readonly SubscriptionCommunicationSender $sender,
        private readonly Logger $logger,
    ) {
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->sendForSubscriptionId(
            subscriptionId: $event->subscriptionId,
            communicationKey: self::KEY_SUBSCRIPTION_CREATED,
            metadata: [
                'event_type' => 'subscription.created',
                'plan_id' => $event->planId,
                'billing_period' => $event->billingPeriod,
                'amount' => $event->priceCents,
                'currency' => $event->currency,
            ],
        );
    }

    public function handleSubscriptionCancelled(SubscriptionCancelled $event): void
    {
        $this->sendForSubscriptionId(
            subscriptionId: $event->subscriptionId,
            communicationKey: self::KEY_SUBSCRIPTION_CANCELLED,
            metadata: [
                'event_type' => 'subscription.cancelled',
                'cancel_at_period_end' => $event->cancelAtPeriodEnd,
                'end_date' => $event->endDate,
            ],
        );
    }

    public function handleSubscriptionReactivated(SubscriptionReactivated $event): void
    {
        $this->sendForSubscriptionId(
            subscriptionId: $event->subscriptionId,
            communicationKey: self::KEY_SUBSCRIPTION_REACTIVATED,
            metadata: [
                'event_type' => 'subscription.reactivated',
                'days_remaining' => $event->daysRemaining,
            ],
        );
    }

    public function handleSubscriptionPaused(SubscriptionPaused $event): void
    {
        $this->sendForSubscription(
            subscription: $event->subscription,
            communicationKey: self::KEY_SUBSCRIPTION_PAUSED,
            metadata: [
                'event_type' => 'subscription.paused',
                'pause_start' => $event->pauseStart,
                'paused_until' => $event->pausedUntil,
                'duration_months' => $event->durationMonths,
                'reason' => $event->reason,
            ],
        );
    }

    public function handleSubscriptionResumed(SubscriptionResumed $event): void
    {
        $this->sendForSubscription(
            subscription: $event->subscription,
            communicationKey: self::KEY_SUBSCRIPTION_RESUMED,
            metadata: [
                'event_type' => 'subscription.resumed',
                'member_id' => $event->memberId,
            ],
        );
    }

    public function handlePaymentSucceeded(PaymentSucceeded $event): void
    {
        $this->sendForSubscriptionId(
            subscriptionId: $event->subscriptionId,
            communicationKey: self::KEY_PAYMENT_SUCCEEDED,
            metadata: [
                'event_type' => 'payment.succeeded',
                'payment_id' => $event->paymentId,
                'amount' => $event->amountCents,
                'currency' => $event->currency,
            ],
        );
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->sendForSubscriptionId(
            subscriptionId: $event->subscriptionId,
            communicationKey: self::KEY_PAYMENT_FAILED,
            metadata: [
                'event_type' => 'payment.failed',
                'payment_id' => $event->paymentId,
                'amount' => $event->amountCents,
                'currency' => $event->currency,
                'failure_reason' => $event->failureReason,
            ],
        );
    }

    public function handlePaymentRefunded(PaymentRefunded $event): void
    {
        $this->sendForSubscriptionId(
            subscriptionId: $event->subscriptionId,
            communicationKey: self::KEY_PAYMENT_REFUNDED,
            metadata: [
                'event_type' => 'payment.refunded',
                'payment_id' => $event->paymentId,
                'amount' => $event->amountCents,
                'currency' => $event->currency,
                'reason' => $event->reason,
            ],
        );
    }

    private function sendForSubscriptionId(int $subscriptionId, string $communicationKey, array $metadata = []): void
    {
        $subscription = $this->subscriptions->find($subscriptionId);

        if (!$subscription) {
            $this->logger->warning('SendSubscriptionLifecycleCommunicationListener: subscription not found', [
                'subscription_id' => $subscriptionId,
                'communication_key' => $communicationKey,
            ]);
            return;
        }

        $this->sendForSubscription($subscription, $communicationKey, $metadata);
    }

    private function sendForSubscription(Subscription $subscription, string $communicationKey, array $metadata = []): void
    {
        $communication = $this->communications->findActiveByKey($communicationKey);

        if (!$communication) {
            $this->logger->info('SendSubscriptionLifecycleCommunicationListener: no active communication configured for lifecycle event', [
                'subscription_id' => $subscription->id,
                'communication_key' => $communicationKey,
            ]);
            return;
        }

        try {
            $this->sender->send(
                subscription: $subscription,
                communication: $communication,
                schedule: null,
                metadata: $metadata,
                dedupeKey: $this->dedupeKey($subscription, $communicationKey, $metadata),
            );
        } catch (\Throwable $e) {
            $this->logger->error('SendSubscriptionLifecycleCommunicationListener: failed to send lifecycle communication', [
                'subscription_id' => $subscription->id,
                'communication_id' => $communication->id ?? null,
                'communication_key' => $communicationKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dedupeKey(Subscription $subscription, string $communicationKey, array $metadata): string
    {
        $eventType = $metadata['event_type'] ?? $communicationKey;
        $eventId = $metadata['payment_id'] ?? $metadata['end_date'] ?? $metadata['pause_start'] ?? now_datetime()->format('Y-m-d H:i:s');

        return sha1(implode('|', [
            $subscription->id,
            $communicationKey,
            $eventType,
            (string) $eventId,
        ]));
    }
}
