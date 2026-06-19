<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;

class SubscriptionPauseService
{
    private const MAX_PAUSE_DAYS = 90;
    private const PAUSABLE_STATUSES = ['active'];
    private const RESUMABLE_STATUSES = ['paused'];

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EventDispatcher $eventDispatcher,
        private readonly Database $database,
        private readonly StripeSubscriptionLifecycleService $stripeLifecycleService,
    ) {
    }

    public function pause(
        int $subscriptionId,
        int $memberId,
        ?string $pauseUntil = null
    ): Subscription {
        $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

        if (!$this->canPauseSubscription($subscription, $memberId)) {
            throw new RuntimeException('This subscription cannot be paused.');
        }

        $resolvedPauseUntil = $this->resolvePauseUntil($pauseUntil);
        $stripeChanged = false;

        try {
            if ($subscription->hasStripeSubscription()) {
                $this->assertStripeSuccess(
                    $this->stripeLifecycleService->pause(
                        (string)$subscription->getStripeSubscriptionId()
                    ),
                    'Stripe billing could not be paused.'
                );
                $stripeChanged = true;
            }

            return $this->database->transaction(function () use (
                $subscriptionId,
                $memberId,
                $resolvedPauseUntil
            ): Subscription {
                $this->subscriptionRepository->update($subscriptionId, [
                    'status' => 'paused',
                    'paused_at' => date('Y-m-d H:i:s'),
                    'pause_until' => $resolvedPauseUntil,
                ]);

                $updated = $this->requireUpdatedSubscription($subscriptionId);

                Logger::info('Subscription paused', [
                    'subscription_id' => $subscriptionId,
                    'member_id' => $memberId,
                    'pause_until' => $resolvedPauseUntil,
                ]);

                $this->eventDispatcher->dispatch(
                    new SubscriptionPaused($updated, $resolvedPauseUntil, $memberId)
                );

                return $updated;
            });
        } catch (Throwable $e) {
            if ($stripeChanged) {
                $this->compensateStripePauseFailure($subscription);
            }

            throw $e;
        }
    }

    public function resume(int $subscriptionId, int $memberId): Subscription
    {
        $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

        if (!$this->canResumeSubscription($subscription, $memberId)) {
            throw new RuntimeException('This subscription cannot be resumed.');
        }

        $stripeChanged = false;
        $stripeResult = null;

        try {
            if ($subscription->hasStripeSubscription()) {
                $stripeResult = $this->stripeLifecycleService->resume(
                    (string)$subscription->getStripeSubscriptionId()
                );
                $this->assertStripeSuccess(
                    $stripeResult,
                    'Stripe billing could not be resumed.'
                );
                $stripeChanged = true;
            }

            $nextBillingDate = $subscription->hasStripeSubscription()
                ? $this->stripeNextBillingDate($stripeResult, $subscription)
                : $this->calculateLocalResumedBillingDate($subscription);

            return $this->database->transaction(function () use (
                $subscriptionId,
                $memberId,
                $nextBillingDate
            ): Subscription {
                $this->subscriptionRepository->update($subscriptionId, [
                    'status' => 'active',
                    'paused_at' => null,
                    'pause_until' => null,
                    'next_billing_date' => $nextBillingDate,
                    'resumed_at' => date('Y-m-d H:i:s'),
                ]);

                $updated = $this->requireUpdatedSubscription($subscriptionId);

                Logger::info('Subscription resumed', [
                    'subscription_id' => $subscriptionId,
                    'member_id' => $memberId,
                    'next_billing_date' => $nextBillingDate,
                ]);

                $this->eventDispatcher->dispatch(
                    new SubscriptionResumed($updated, $memberId)
                );

                return $updated;
            });
        } catch (Throwable $e) {
            if ($stripeChanged) {
                $this->compensateStripeResumeFailure($subscription);
            }

            throw $e;
        }
    }

    public function canPause(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        return $subscription
            ? $this->canPauseSubscription($subscription, $memberId)
            : false;
    }

    public function canPauseSubscription(
        Subscription $subscription,
        int $memberId
    ): bool {
        return (int)$subscription->member_id === $memberId
            && in_array((string)$subscription->status, self::PAUSABLE_STATUSES, true)
            && !$subscription->isCancellationScheduled();
    }

    public function canResume(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        return $subscription
            ? $this->canResumeSubscription($subscription, $memberId)
            : false;
    }

    public function canResumeSubscription(
        Subscription $subscription,
        int $memberId
    ): bool {
        return (int)$subscription->member_id === $memberId
            && in_array((string)$subscription->status, self::RESUMABLE_STATUSES, true);
    }

    private function loadAndAuthorize(
        int $subscriptionId,
        int $memberId
    ): Subscription {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || (int)$subscription->member_id !== $memberId) {
            throw new RuntimeException("Subscription not found: {$subscriptionId}");
        }

        return $subscription;
    }

    private function requireUpdatedSubscription(int $subscriptionId): Subscription
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new RuntimeException(
                "Subscription could not be reloaded after update: {$subscriptionId}"
            );
        }

        return $subscription;
    }

    private function resolvePauseUntil(?string $pauseUntil): ?string
    {
        if ($pauseUntil === null || trim($pauseUntil) === '') {
            return null;
        }

        try {
            $requested = new DateTimeImmutable($pauseUntil);
        } catch (Throwable) {
            throw new RuntimeException('Invalid pause date.');
        }

        $today = new DateTimeImmutable('today');

        if ($requested <= $today) {
            throw new RuntimeException('Pause date must be in the future.');
        }

        $maximum = $today->modify('+' . self::MAX_PAUSE_DAYS . ' days');
        $resolved = $requested > $maximum ? $maximum : $requested;

        return $resolved->format('Y-m-d');
    }

    private function calculateLocalResumedBillingDate(
        Subscription $subscription
    ): string {
        $now = new DateTimeImmutable();
        $pausedAt = $this->date($subscription->paused_at) ?? $now;
        $pausedDays = max(
            0,
            min((int)$pausedAt->diff($now)->days, self::MAX_PAUSE_DAYS)
        );
        $base = $this->date($subscription->next_billing_date) ?? $now;

        return $base->modify("+{$pausedDays} days")->format('Y-m-d H:i:s');
    }

    private function stripeNextBillingDate(
        ?array $stripeResult,
        Subscription $subscription
    ): string {
        $currentPeriodEnd = $stripeResult['current_period_end'] ?? null;

        if (is_numeric($currentPeriodEnd)) {
            return (new DateTimeImmutable('@' . (int)$currentPeriodEnd))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        }

        $existing = $this->date($subscription->next_billing_date);

        if ($existing) {
            return $existing->format('Y-m-d H:i:s');
        }

        throw new RuntimeException(
            'Stripe resumed the subscription but did not return a next billing date.'
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function assertStripeSuccess(
        array $result,
        string $fallbackMessage
    ): void {
        if (!($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? $fallbackMessage);
        }
    }

    private function compensateStripePauseFailure(
        Subscription $subscription
    ): void {
        $result = $this->stripeLifecycleService->resume(
            (string)$subscription->getStripeSubscriptionId()
        );

        if (!($result['success'] ?? false)) {
            Logger::error('Failed to compensate Stripe pause after local failure', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
                'stripe_error' => $result['message'] ?? null,
            ]);
        }
    }

    private function compensateStripeResumeFailure(
        Subscription $subscription
    ): void {
        $result = $this->stripeLifecycleService->pause(
            (string)$subscription->getStripeSubscriptionId()
        );

        if (!($result['success'] ?? false)) {
            Logger::error('Failed to compensate Stripe resume after local failure', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
                'stripe_error' => $result['message'] ?? null,
            ]);
        }
    }
}
