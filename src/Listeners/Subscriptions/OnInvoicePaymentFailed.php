<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Framework\Support\Logger;
use App\Notifications\Subscriptions\SubscriptionNotificationDispatcher;

/**
 * Handles post-failure member notification.
 *
 * Triggered by: InvoicePaymentFailed
 *
 * Responsibilities:
 *   1. Compute the grace period end date from existing subscription dates.
 *   2. Notify the member that payment failed and they retain access until
 *      the grace period expires.
 *
 * Explicitly NOT responsible for:
 *   - Setting subscription status to PAST_DUE (done by SubscriptionInvoiceHandler)
 *   - Revoking access (done by ExpireSubscriptionAccessJob when grace lapses)
 *   - Touching any entitlement records
 *
 * Access contract:
 *   On payment failure, access is NOT touched. Stripe retries automatically.
 *   The grace period is current_period_end + GRACE_PERIOD_DAYS. When that
 *   date passes without a successful payment, ExpireSubscriptionAccessJob
 *   handles revocation.
 *
 * Failure contract:
 *   Notification failure: catch and log — never let a failed email block
 *   the event pipeline or cause a queue retry that re-sends the email.
 */
class OnInvoicePaymentFailed
{
    /**
     * Days of access granted beyond current_period_end after a failed payment.
     * Adjust to match your product's retry window and support policy.
     */
    private const GRACE_PERIOD_DAYS = 7;

    public function __construct(
        private readonly SubscriptionNotificationDispatcher $notifications,
        private readonly Logger                             $logger,
    )
    {
    }

    public function handle(InvoicePaymentFailed $event): void
    {
        $subscription = $event->subscription;

        $gracePeriodUntil = $this->computeGracePeriodEnd($subscription);

        $this->logger->info('OnInvoicePaymentFailed: payment failed, access retained during grace period', [
            'subscription_id' => $subscription->id,
            'grace_period_until' => $gracePeriodUntil->format('Y-m-d H:i:s'),
            'failure_reason' => $event->failureReason,
        ]);

        try {
            $this->notifications->notifyPaymentFailed(
                subscription: $subscription,
                gracePeriodUntil: $gracePeriodUntil,
                failureReason: $event->failureReason,
            );
        } catch (\Throwable $e) {
            $this->logger->error('OnInvoicePaymentFailed: failed payment notification failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Grace period = end of the current paid period + GRACE_PERIOD_DAYS.
     *
     * If current_period_end is missing (edge case: subscription was never
     * successfully billed), fall back to now + GRACE_PERIOD_DAYS so the
     * member still gets a short window rather than immediate cutoff.
     */
    private function computeGracePeriodEnd(mixed $subscription): \DateTimeImmutable
    {
        $base = $subscription->current_period_end
            ? \DateTimeImmutable::createFromMutable(
                \DateTime::createFromInterface($subscription->current_period_end)
            )
            : new \DateTimeImmutable();

        return $base->modify(sprintf('+%d days', self::GRACE_PERIOD_DAYS));
    }
}