<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use DateTimeImmutable;
use RuntimeException;

class SubscriptionPauseService
{
    private const MAX_PAUSE_DAYS = 90;
    private const PAUSABLE_STATUSES = ['active'];
    private const RESUMABLE_STATUSES = ['paused'];

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EventDispatcher $eventDispatcher,
        private readonly Database $database,
    ) {
    }

    public function pause(int $subscriptionId, int $memberId, ?string $pauseUntil = null): Subscription
    {
        return $this->database->transaction(function () use ($subscriptionId, $memberId, $pauseUntil) {
            $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

            if (!in_array($subscription->status, self::PAUSABLE_STATUSES, true)) {
                throw new RuntimeException(
                    "Subscription cannot be paused from status: {$subscription->status}",
                );
            }

            $resolvedPauseUntil = $this->resolvePauseUntil($pauseUntil);
            $autoRenewBeforePause = (bool) $subscription->auto_renew;

            $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'paused',
                'auto_renew_before_pause' => $autoRenewBeforePause,
                'auto_renew' => false,
                'paused_at' => date('Y-m-d H:i:s'),
                'pause_until' => $resolvedPauseUntil,
            ]);

            $subscription = $this->subscriptionRepository->find($subscriptionId);

            Logger::info('Subscription paused', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'pause_until' => $resolvedPauseUntil,
                'auto_renew_before_pause' => $autoRenewBeforePause,
            ]);

            $this->eventDispatcher->dispatch(
                new SubscriptionPaused($subscription, $resolvedPauseUntil, $memberId),
            );

            return $subscription;
        });
    }

    public function resume(int $subscriptionId, int $memberId): Subscription
    {
        return $this->database->transaction(function () use ($subscriptionId, $memberId) {
            $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

            if (!in_array($subscription->status, self::RESUMABLE_STATUSES, true)) {
                throw new RuntimeException(
                    "Subscription cannot be resumed from status: {$subscription->status}",
                );
            }

            $newNextBillingDate = $this->calculateResumedBillingDate($subscription);
            $storedRenewalPreference = $subscription->getAttribute('auto_renew_before_pause');

            // Rows paused before auto_renew_before_pause existed have no
            // snapshot. Preserve the old resume behaviour for those rows.
            $restoredAutoRenew = $storedRenewalPreference === null
                ? true
                : (bool) $storedRenewalPreference;

            $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'active',
                'auto_renew' => $restoredAutoRenew,
                'auto_renew_before_pause' => null,
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
                'auto_renew' => $restoredAutoRenew,
            ]);

            $this->eventDispatcher->dispatch(
                new SubscriptionResumed($subscription, $memberId),
            );

            return $subscription;
        });
    }

    public function canPause(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        return $subscription
            && (int) $subscription->member_id === $memberId
            && in_array($subscription->status, self::PAUSABLE_STATUSES, true);
    }

    public function canResume(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        return $subscription
            && (int) $subscription->member_id === $memberId
            && in_array($subscription->status, self::RESUMABLE_STATUSES, true);
    }

    private function loadAndAuthorize(int $subscriptionId, int $memberId): Subscription
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || (int) $subscription->member_id !== $memberId) {
            throw new RuntimeException("Subscription not found: {$subscriptionId}");
        }

        return $subscription;
    }

    private function resolvePauseUntil(?string $pauseUntil): ?string
    {
        if ($pauseUntil === null) {
            return null;
        }

        $requested = new DateTimeImmutable($pauseUntil);
        $maxDate = new DateTimeImmutable('+' . self::MAX_PAUSE_DAYS . ' days');
        $resolved = $requested > $maxDate ? $maxDate : $requested;

        return $resolved->format('Y-m-d');
    }

    private function calculateResumedBillingDate(Subscription $subscription): string
    {
        $now = new DateTimeImmutable();
        $pausedAt = !empty($subscription->paused_at)
            ? new DateTimeImmutable((string) $subscription->paused_at)
            : $now;

        $pausedDays = (int) $pausedAt->diff($now)->days;
        $pausedDays = max(0, min($pausedDays, self::MAX_PAUSE_DAYS));

        $base = $subscription->next_billing_date
            ? DateTimeImmutable::createFromInterface($subscription->next_billing_date)
            : $now;

        return $base->modify("+{$pausedDays} days")->format('Y-m-d H:i:s');
    }
}
