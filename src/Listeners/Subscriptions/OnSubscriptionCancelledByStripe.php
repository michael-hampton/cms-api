<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionCancelledByStripe;
use App\Framework\Support\Logger;
use App\Notifications\Subscriptions\SubscriptionNotificationDispatcher;

/**
 * Handles post-cancellation member notification.
 *
 * Triggered by: SubscriptionCancelledByStripe
 *
 * Responsibilities:
 *   1. Notify the member their subscription is cancelled and state the
 *      exact date their access expires.
 *
 * Explicitly NOT responsible for:
 *   - Setting status to CANCELLED (done by SubscriptionCancellationHandler)
 *   - Revoking access (done by ExpireSubscriptionAccessJob)
 *   - Touching any entitlement records
 *
 * Access contract:
 *   The member retains access until $event->accessUntil (end of last paid
 *   period). Access is revoked by ExpireSubscriptionAccessJob when that
 *   date passes — never immediately in this listener.
 *
 * Failure contract:
 *   Notification failure: catch and log — the cancellation is already
 *   recorded in the DB. A failed email must not cause a rollback or retry
 *   that re-processes the cancellation.
 */
class OnSubscriptionCancelledByStripe
{
    public function __construct(
        private readonly SubscriptionNotificationDispatcher $notifications,
        private readonly Logger                             $logger,
    )
    {
    }

    public function handle(SubscriptionCancelledByStripe $event): void
    {
        $subscription = $event->subscription;
        $accessUntil = $event->accessUntil;

        if (!$accessUntil) {
            // Stripe sent no period end — access ended immediately per Stripe.
            // Log it; the expiry job will handle cleanup on its next run.
            $this->logger->warning('OnSubscriptionCancelledByStripe: no accessUntil date, member may have lost access immediately', [
                'subscription_id' => $subscription->id,
            ]);

            // Still notify — the member should know their subscription is cancelled
            // even if we can't state a precise access-until date.
            $accessUntil = new \DateTimeImmutable();
        }

        $this->logger->info('OnSubscriptionCancelledByStripe: subscription cancelled, access retained until period end', [
            'subscription_id' => $subscription->id,
            'access_until' => $accessUntil->format('Y-m-d H:i:s'),
            'cancelled_at' => $event->cancelledAt->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->notifications->notifySubscriptionCancelled(
                subscription: $subscription,
                accessUntil: $accessUntil,
            );
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionCancelledByStripe: cancellation notification failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}