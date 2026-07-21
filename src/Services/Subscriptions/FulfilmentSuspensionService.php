<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Suspends (or defers suspending) a subscription's pending fulfilments in
 * response to a payment failure or a subscription-level suspension, per the
 * subscription's resolved FulfilmentSuspensionRule.
 *
 * Two trigger reasons feed this service, both applying the exact same
 * suspension mechanism:
 *   - 'payment_failed'         — InvoicePaymentFailed
 *   - 'subscription_suspended' — SubscriptionSuspended
 *
 * When the rule resolves to something other than immediate and is not yet
 * satisfied, the subscription is flagged fulfilment_suspension_pending so
 * ProcessPendingFulfilmentSuspensionsJob can re-check and apply it later.
 */
class FulfilmentSuspensionService
{
    public const REASON_PAYMENT_FAILED = 'payment_failed';
    public const REASON_SUBSCRIPTION_SUSPENDED = 'subscription_suspended';

    public function __construct(
        private readonly FulfilmentSuspensionPolicyResolver $policyResolver,
        private readonly SubscriptionIssueFulfilmentRepository $fulfilmentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Entry point for both trigger events. Applies suspension immediately
     * when due, otherwise defers it for later re-evaluation.
     */
    public function handleTrigger(Subscription $subscription, string $reason): void
    {
        $rule = $this->policyResolver->resolveForPlan((int) $subscription->plan_id);

        if ($this->policyResolver->isSuspensionDue((int) $subscription->id, $rule, new \DateTimeImmutable())) {
            $this->suspendNow($subscription, $reason);

            return;
        }

        $this->subscriptionRepository->update((int) $subscription->id, [
            'fulfilment_suspension_pending' => true,
            'fulfilment_suspension_reason' => $reason,
            'fulfilment_suspension_triggered_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->logger->info('FulfilmentSuspensionService: suspension deferred by policy', [
            'subscription_id' => $subscription->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Re-checks a subscription with a deferred suspension and applies it if
     * the rule is now satisfied. Called by ProcessPendingFulfilmentSuspensionsJob.
     */
    public function reevaluatePending(Subscription $subscription): bool
    {
        if (!$subscription->fulfilment_suspension_pending) {
            return false;
        }

        $rule = $this->policyResolver->resolveForPlan((int) $subscription->plan_id);

        if (!$this->policyResolver->isSuspensionDue((int) $subscription->id, $rule, new \DateTimeImmutable())) {
            return false;
        }

        $this->suspendNow($subscription, (string) $subscription->fulfilment_suspension_reason);

        return true;
    }

    /**
     * Reverses a suspension once the underlying problem clears (payment
     * recovered, subscription reactivated).
     */
    public function release(Subscription $subscription): int
    {
        $released = $this->fulfilmentRepository->releaseSuspendedForSubscription((int) $subscription->id);

        $this->subscriptionRepository->update((int) $subscription->id, [
            'fulfilment_suspension_pending' => false,
            'fulfilment_suspension_reason' => null,
        ]);

        $this->logger->info('FulfilmentSuspensionService: fulfilments released', [
            'subscription_id' => $subscription->id,
            'count' => $released,
        ]);

        return $released;
    }

    private function suspendNow(Subscription $subscription, string $reason): int
    {
        $count = $this->fulfilmentRepository->suspendPendingForSubscription((int) $subscription->id, $reason);

        $this->subscriptionRepository->update((int) $subscription->id, [
            'fulfilment_suspension_pending' => false,
        ]);

        $this->logger->info('FulfilmentSuspensionService: pending fulfilments suspended', [
            'subscription_id' => $subscription->id,
            'reason' => $reason,
            'count' => $count,
        ]);

        return $count;
    }
}
