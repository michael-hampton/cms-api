<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * SubscriptionPauseService
 *
 * Billing-level pause: freezes renewal charging while the subscription
 * remains logically "paused". This is distinct from SubscriptionDeliveryService
 * which manages physical delivery windows within an active subscription.
 *
 * A paused subscription:
 *   - Has status = 'paused' (stored in DB)
 *   - Cannot be billed by the renewal job (job must check status !== 'paused')
 *   - Retains premium access until pause_until (if set) or until cancelled
 *   - Can be resumed by the member at any time up to 90 days
 *   - Auto-resumes when pause_until passes (handled by a scheduled job)
 *
 * NOTE: If the subscription has a Stripe subscription ID, the caller is
 * responsible for syncing Stripe (e.g. pause_collection). This service
 * only manages DB state — the same pattern used by SubscriptionCancellationService.
 */
class SubscriptionPauseService
{
    private const CANCELLABLE_FROM_PAUSED = true;
    private const MAX_PAUSE_DAYS = 90;
    private const PAUSABLE_STATUSES = ['active'];
    private const RESUMABLE_STATUSES = ['paused'];

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EventDispatcher        $eventDispatcher,
        private readonly Database               $database,
    )
    {
    }

    /**
     * Pause an active subscription.
     *
     * @param string|null $pauseUntil ISO-8601 date. Null = indefinite (member must resume manually).
     *
     * @throws \RuntimeException  If subscription not found, wrong member, or not pausable.
     */
    public function pause(int $subscriptionId, int $memberId, ?string $pauseUntil = null): Subscription
    {
        return $this->database->transaction(function () use ($subscriptionId, $memberId, $pauseUntil) {
            $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

            if (!in_array($subscription->status, self::PAUSABLE_STATUSES, true)) {
                throw new \RuntimeException(
                    "Subscription cannot be paused from status: {$subscription->status}"
                );
            }

            $resolvedPauseUntil = $this->resolvePauseUntil($pauseUntil);

            $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'paused',
                'auto_renew' => false,
                'paused_at' => date('Y-m-d H:i:s'),
                'pause_until' => $resolvedPauseUntil,
            ]);

            $subscription = $this->subscriptionRepository->find($subscriptionId);

            Logger::info('Subscription paused', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'pause_until' => $resolvedPauseUntil,
            ]);

            $this->eventDispatcher->dispatch(
                new SubscriptionPaused($subscription, $resolvedPauseUntil, $memberId)
            );

            return $subscription;
        });
    }

    private function loadAndAuthorize(int $subscriptionId, int $memberId): Subscription
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \RuntimeException("Subscription not found: {$subscriptionId}");
        }

        if ((int)$subscription->member_id !== $memberId) {
            // Intentionally same message — don't leak existence to wrong member
            throw new \RuntimeException("Subscription not found: {$subscriptionId}");
        }

        return $subscription;
    }

    /**
     * Validate and cap the requested pause_until date.
     */
    private function resolvePauseUntil(?string $pauseUntil): ?string
    {
        if ($pauseUntil === null) {
            return null;
        }

        $requested = new \DateTimeImmutable($pauseUntil);
        $maxDate = new \DateTimeImmutable('+' . self::MAX_PAUSE_DAYS . ' days');
        $resolved = $requested > $maxDate ? $maxDate : $requested;

        return $resolved->format('Y-m-d');
    }

    /**
     * Resume a paused subscription.
     *
     * Restores auto_renew to true and extends next_billing_date by the number
     * of days the subscription was paused, so the member is not charged immediately
     * for a period they could not use.
     *
     * @throws \RuntimeException If subscription not found, wrong member, or not paused.
     */
    public function resume(int $subscriptionId, int $memberId): Subscription
    {
        return $this->database->transaction(function () use ($subscriptionId, $memberId) {
            $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

            if (!in_array($subscription->status, self::RESUMABLE_STATUSES, true)) {
                throw new \RuntimeException(
                    "Subscription cannot be resumed from status: {$subscription->status}"
                );
            }

            $newNextBillingDate = $this->calculateResumedBillingDate($subscription);

            $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'active',
                'auto_renew' => true,
                'paused_at' => null,
                'pause_until' => null,
                'next_billing_date' => $newNextBillingDate,
                'resumed_at' => date('Y-m-d H:i:s'),
            ]);

            $subscription = $this->subscriptionRepository->find($subscriptionId);

            Logger::info('Subscription resumed', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'next_billing_date' => $newNextBillingDate,
            ]);

            $this->eventDispatcher->dispatch(
                new SubscriptionResumed($subscription, $memberId)
            );

            return $subscription;
        });
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * When resuming, push the next billing date forward by the number of days
     * the subscription was paused, so the member isn't charged for unused time.
     */
    private function calculateResumedBillingDate(Subscription $subscription): string
    {
        $now = new \DateTimeImmutable();
        $pausedAt = !empty($subscription->paused_at)
            ? new \DateTimeImmutable((string)$subscription->paused_at)
            : $now;

        $pausedDays = (int)$pausedAt->diff($now)->days;
        $pausedDays = max(0, min($pausedDays, self::MAX_PAUSE_DAYS));

        $base = !empty($subscription->next_billing_date)
            ? new \DateTimeImmutable((string)$subscription->next_billing_date)
            : $now;

        return $base->modify("+{$pausedDays} days")->format('Y-m-d H:i:s');
    }

    /**
     * Check whether a subscription can currently be paused by this member.
     * Used by the controller and view to gate the pause button.
     */
    public function canPause(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || (int)$subscription->member_id !== $memberId) {
            return false;
        }

        return in_array($subscription->status, self::PAUSABLE_STATUSES, true);
    }

    /**
     * Check whether a subscription can currently be resumed by this member.
     */
    public function canResume(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || (int)$subscription->member_id !== $memberId) {
            return false;
        }

        return in_array($subscription->status, self::RESUMABLE_STATUSES, true);
    }
}