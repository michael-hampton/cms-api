<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use DateTimeImmutable;
use DateTimeInterface;
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
        private readonly StripeSubscriptionGateway $stripeSubscriptionGateway,
    ) {
    }

    public function pause(int $subscriptionId, int $memberId, ?string $pauseUntil = null): Subscription
    {
        $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

        if (!in_array($subscription->status, self::PAUSABLE_STATUSES, true)) {
            throw new RuntimeException(
                "Subscription cannot be paused from status: {$subscription->status}",
            );
        }

        $resolvedPauseUntil = $this->resolvePauseUntil($pauseUntil);
        $autoRenewBeforePause = (bool) $subscription->auto_renew;
        $stripeSubscriptionId = $subscription->getStripeSubscriptionId();

        if ($stripeSubscriptionId) {
            $this->stripeSubscriptionGateway->pauseCollection($stripeSubscriptionId);
        }

        try {
            return $this->database->transaction(function () use (
                $subscriptionId,
                $memberId,
                $resolvedPauseUntil,
                $autoRenewBeforePause,
            ) {
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
        } catch (\Throwable $exception) {
            if ($stripeSubscriptionId) {
                try {
                    $this->stripeSubscriptionGateway->resumeCollection($stripeSubscriptionId);
                } catch (\Throwable $compensationFailure) {
                    Logger::error('Failed to compensate Stripe subscription pause', [
                        'subscription_id' => $subscriptionId,
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'exception' => $compensationFailure,
                    ]);
                }
            }

            throw $exception;
        }
    }

    public function resume(int $subscriptionId, int $memberId): Subscription
    {
        $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

        if (!in_array($subscription->status, self::RESUMABLE_STATUSES, true)) {
            throw new RuntimeException(
                "Subscription cannot be resumed from status: {$subscription->status}",
            );
        }

        $newNextBillingDate = $this->calculateResumedBillingDate($subscription);
        $storedRenewalPreference = $subscription->getAttribute('auto_renew_before_pause');
        $restoredAutoRenew = $storedRenewalPreference === null
            ? true
            : (bool) $storedRenewalPreference;
        $stripeSubscriptionId = $subscription->getStripeSubscriptionId();

        if ($stripeSubscriptionId) {
            $this->stripeSubscriptionGateway->resumeCollection($stripeSubscriptionId);
        }

        try {
            return $this->database->transaction(function () use (
                $subscriptionId,
                $memberId,
                $newNextBillingDate,
                $restoredAutoRenew,
            ) {
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
        } catch (\Throwable $exception) {
            if ($stripeSubscriptionId) {
                try {
                    $this->stripeSubscriptionGateway->pauseCollection($stripeSubscriptionId);
                } catch (\Throwable $compensationFailure) {
                    Logger::error('Failed to compensate Stripe subscription resume', [
                        'subscription_id' => $subscriptionId,
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'exception' => $compensationFailure,
                    ]);
                }
            }

            throw $exception;
        }
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

        try {
            $requested = new DateTimeImmutable($pauseUntil);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Pause date is invalid.', previous: $exception);
        }

        $today = new DateTimeImmutable('today');
        if ($requested <= $today) {
            throw new RuntimeException('Pause date must be after today.');
        }

        $maxDate = $today->modify('+' . self::MAX_PAUSE_DAYS . ' days');
        $resolved = $requested > $maxDate ? $maxDate : $requested;

        return $resolved->format('Y-m-d');
    }

    private function calculateResumedBillingDate(Subscription $subscription): string
    {
        $now = new DateTimeImmutable();
        $pausedAt = $this->toImmutableDate($subscription->paused_at ?? null, $now);
        $base = $this->toImmutableDate($subscription->next_billing_date ?? null, $now);

        $pausedDays = (int) $pausedAt->diff($now)->days;
        $pausedDays = max(0, min($pausedDays, self::MAX_PAUSE_DAYS));

        return $base->modify("+{$pausedDays} days")->format('Y-m-d H:i:s');
    }

    private function toImmutableDate(mixed $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }

        return $fallback;
    }
}
