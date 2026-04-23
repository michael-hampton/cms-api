<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Stripe\StripeSubscriptionDeletedEvent;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionCancelledByStripe;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Handles Stripe's customer.subscription.deleted webhook event.
 *
 * This event fires when Stripe definitively ends a subscription — either
 * because the user cancelled, payment retries were exhausted, or it was
 * cancelled via the Stripe dashboard.
 *
 * We mark the subscription cancelled and emit an event. Listeners decide
 * whether to revoke access immediately or honour the remaining paid period.
 * This handler does NOT revoke access directly.
 */
class SubscriptionCancellationHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EventDispatcher        $eventDispatcher,
        private readonly Logger                 $logger,
        private readonly Database               $database
    )
    {
    }

    public function handle(StripeSubscriptionDeletedEvent $event): void
    {
        $subscription = $this->subscriptionRepository->findSubscriptionByStripeId($event->stripeSubscriptionId);

        if (!$subscription) {
            // Log and return — not a hard failure. The subscription may have
            // been created before payment_subscription_id tracking was added,
            // or it may belong to a different product area.
            $this->logger->warning('customer.subscription.deleted: no matching subscription found', [
                'stripe_subscription_id' => $event->stripeSubscriptionId,
            ]);
            return;
        }

        // Idempotency: if already cancelled, there is nothing to do.
        if ($subscription->status === SubscriptionStatus::CANCELLED->value) {
            $this->logger->info('customer.subscription.deleted: already cancelled, skipping', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $event->stripeSubscriptionId,
            ]);
            return;
        }

        $cancelledAt = $event->cancelledAt();
        $accessUntil = $event->accessUntil();

        $this->database->transaction(function () use ($subscription, $cancelledAt, $accessUntil) {
            $subscription->update([
                'status' => SubscriptionStatus::CANCELLED->value,
                'cancelled_at' => $cancelledAt->format('Y-m-d H:i:s'),
                'auto_renew' => false,
                // end_date records when paid access expires; listeners use this
                // to honour "access until period end" without reading Stripe.
                'end_date' => $accessUntil?->format('Y-m-d H:i:s'),
            ]);

            return true;
        });

        $this->eventDispatcher->dispatch(
            new SubscriptionCancelledByStripe(
                subscription: $subscription,
                cancelledAt: $cancelledAt,
                accessUntil: $accessUntil,
            )
        );

        $this->logger->info('customer.subscription.deleted processed', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $event->stripeSubscriptionId,
            'cancelled_at' => $cancelledAt->format('Y-m-d H:i:s'),
            'access_until' => $accessUntil?->format('Y-m-d H:i:s'),
        ]);
    }
}