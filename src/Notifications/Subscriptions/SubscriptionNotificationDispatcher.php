<?php

declare(strict_types=1);

namespace App\Notifications\Subscriptions;

use App\Models\Subscription;

/**
 * Contract for dispatching subscription-related member notifications.
 *
 * Listeners depend on this interface, not on any concrete mailer, so the
 * notification implementation (Mailgun, SES, queued jobs, etc.) can change
 * without touching the listeners.
 *
 * Implementations are responsible for:
 *   - Resolving the member's email from the subscription
 *   - Queuing or sending the notification
 *   - Handling their own failures (never throw back to the listener)
 *
 * Each method maps to one user-facing communication:
 *   notifyPaymentFailed      → "We couldn't process your payment, we'll retry"
 *   notifySubscriptionEnding → "Your access ends on {date}"
 *   notifyPaymentReceived    → Optional receipt-style confirmation
 */
interface SubscriptionNotificationDispatcher
{
    /**
     * Notifies the member that their payment failed and Stripe will retry.
     * Should communicate the grace period end date so the member knows
     * how long they retain access.
     *
     * @param \DateTimeImmutable $gracePeriodUntil Derived as current_period_end + grace days.
     */
    public function notifyPaymentFailed(
        Subscription       $subscription,
        \DateTimeImmutable $gracePeriodUntil,
        ?string            $failureReason,
    ): void;

    /**
     * Notifies the member that their subscription has been cancelled and
     * communicates the exact date their access expires.
     *
     * @param \DateTimeImmutable $accessUntil End of the last paid period.
     */
    public function notifySubscriptionCancelled(
        Subscription       $subscription,
        \DateTimeImmutable $accessUntil,
    ): void;

    /**
     * Optional receipt notification after a successful payment.
     * Implementations may no-op this if receipts are handled elsewhere
     * (e.g. Stripe's own receipt emails).
     */
    public function notifyPaymentReceived(
        Subscription $subscription,
    ): void;
}