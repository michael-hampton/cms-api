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
        private readonly StripeSubscriptionLifecycleService $stripeLifecycleService,
    ) {
    }

    public function pause(int $subscriptionId, int $memberId, ?string $pauseUntil = null): Subscription
    {
        $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

        if (!$this->canPauseSubscription($subscription, $memberId)) {
            throw new RuntimeException('This subscription cannot be paused.');
        }

        $resolvedPauseUntil = $this->resolvePauseUntil($pauseUntil);
        $this->pauseRemoteBilling($subscription);

        return $this->database->transaction(function () use ($subscriptionId, $memberId, $resolvedPauseUntil) {
            $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'paused',
                'paused_at' => date('Y-m-d H:i:s'),
                'pause_until' => $resolvedPauseUntil,
            ]);

            $subscription = $this->subscriptionRepository->find($subscriptionId);

            Logger::info('Subscription paused', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'pause_until' => $resolvedPauseUntil,
            ]);

            $this->eventDispatcher->dispatch(new SubscriptionPaused($subscription, $resolvedPauseUntil, $memberId));

            return $subscription;
        });
    }

    public function resume(int $subscriptionId, int $memberId): Subscription
    {
        $subscription = $this->loadAndAuthorize($subscriptionId, $memberId);

        if (!$this->canResumeSubscription($subscription, $memberId)) {
            throw new RuntimeException('This subscription cannot be resumed.');
        }

        $newNextBillingDate = $this->calculateResumedBillingDate($subscription);
        $this->resumeRemoteBilling($subscription);

        return $this->database->transaction(function () use ($subscriptionId, $memberId, $newNextBillingDate) {
            $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'active',
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

            $this->eventDispatcher->dispatch(new SubscriptionResumed($subscription, $memberId));

            return $subscription;
        });
    }

    public function canPause(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        return $subscription ? $this->canPauseSubscription($subscription, $memberId) : false;
    }

    public function canPauseSubscription(Subscription $subscription, int $memberId): bool
    {
        return (int)$subscription->member_id === $memberId
            && in_array((string)$subscription->status, self::PAUSABLE_STATUSES, true)
            && !$subscription->isCancellationScheduled();
    }

    public function canResume(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        return $subscription ? $this->canResumeSubscription($subscription, $memberId) : false;
    }

    public function canResumeSubscription(Subscription $subscription, int $memberId): bool
    {
        return (int)$subscription->member_id === $memberId
            && in_array((string)$subscription->status, self::RESUMABLE_STATUSES, true);
    }

    private function loadAndAuthorize(int $subscriptionId, int $memberId): Subscription
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || (int)$subscription->member_id !== $memberId) {
            throw new RuntimeException("Subscription not found: {$subscriptionId}");
        }

        return $subscription;
    }

    private function pauseRemoteBilling(Subscription $subscription): void
    {
        if (!$subscription->hasStripeSubscription()) {
            return;
        }

        $result = $this->stripeLifecycleService->pause((string)$subscription->getStripeSubscriptionId());

        if (!($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Stripe billing could not be paused.');
        }
    }

    private function resumeRemoteBilling(Subscription $subscription): void
    {
        if (!$subscription->hasStripeSubscription()) {
            return;
        }

        $result = $this->stripeLifecycleService->resume((string)$subscription->getStripeSubscriptionId());

        if (!($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Stripe billing could not be resumed.');
        }
    }

    private function resolvePauseUntil(?string $pauseUntil): ?string
    {
        if ($pauseUntil === null || trim($pauseUntil) === '') {
            return null;
        }

        try {
            $requested = new DateTimeImmutable($pauseUntil);
        } catch (\Throwable) {
            throw new RuntimeException('Invalid pause date.');
        }

        $today = new DateTimeImmutable('today');
        if ($requested <= $today) {
            throw new RuntimeException('Pause date must be in the future.');
        }

        $maxDate = $today->modify('+' . self::MAX_PAUSE_DAYS . ' days');
        $resolved = $requested > $maxDate ? $maxDate : $requested;

        return $resolved->format('Y-m-d');
    }

    private function calculateResumedBillingDate(Subscription $subscription): string
    {
        $now = new DateTimeImmutable();
        $pausedAt = !empty($subscription->paused_at)
            ? new DateTimeImmutable((string)$subscription->paused_at)
            : $now;

        $pausedDays = max(0, min((int)$pausedAt->diff($now)->days, self::MAX_PAUSE_DAYS));
        $base = $subscription->next_billing_date
            ? DateTimeImmutable::createFromInterface($subscription->next_billing_date)
            : $now;

        return $base->modify("+{$pausedDays} days")->format('Y-m-d H:i:s');
    }
}
