<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\InvoicePaymentSucceeded;
use App\Framework\Support\Logger;
use App\Notifications\Subscriptions\SubscriptionNotificationDispatcher;
use App\Services\Subscriptions\MemberAccessService;

/**
 * Handles post-payment access refresh and optional receipt notification.
 *
 * Triggered by: InvoicePaymentSucceeded
 *
 * Responsibilities (in order):
 *   1. Refresh subscription access to the new period end — idempotent.
 *   2. Dispatch optional receipt notification.
 *
 * Explicitly NOT responsible for:
 *   - Updating subscription status or billing dates (done by SubscriptionInvoiceHandler)
 *   - Any Stripe API calls
 *   - Emitting further domain events
 *
 * Failure contract:
 *   - Access refresh failure: throw — this is critical, the job/queue should retry.
 *   - Notification failure: catch and log — non-critical, never block access.
 */
class OnInvoicePaymentSucceeded
{
    public function __construct(
        private readonly MemberAccessService                $accessService,
        private readonly SubscriptionNotificationDispatcher $notifications,
        private readonly Logger                             $logger,
    )
    {
    }

    public function handle(InvoicePaymentSucceeded $event): void
    {
        $subscription = $event->subscription;

        // current_period_end was updated by SubscriptionInvoiceHandler inside
        // its transaction before this event fired. Read it fresh from the model.
        $accessUntil = $subscription->current_period_end
            ? \DateTimeImmutable::createFromMutable(
                \DateTime::createFromInterface($subscription->current_period_end)
            )
            : null;

        if (!$accessUntil) {
            $this->logger->warning('OnInvoicePaymentSucceeded: no current_period_end on subscription, skipping access refresh', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        // Critical — throw on failure so the queue retries.
        $this->accessService->refreshSubscriptionAccess($subscription, $accessUntil);

        // Non-critical — log and continue if notification fails.
        try {
            $this->notifications->notifyPaymentReceived($subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnInvoicePaymentSucceeded: receipt notification failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}