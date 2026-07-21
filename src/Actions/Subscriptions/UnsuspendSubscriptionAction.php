<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionUnsuspended;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\MemberAccessService;

/**
 * Reverses an admin/system enforcement suspension (SuspendSubscriptionAction).
 *
 * Restores the subscription to active, restores entitlement/premium access
 * up to its existing end_date, and re-enables auto-renew. Only ever
 * transitions a subscription out of SUSPENDED — it is not a general-purpose
 * reactivation (that is SubscriptionCancellationService::reactivateSubscription,
 * which only handles CANCELLED subscriptions).
 *
 * Emits SubscriptionUnsuspended for listeners (fulfilment release, audit
 * log, billing sync, notifications, etc.).
 *
 * This action does NOT:
 *   - Call Stripe directly.
 *   - Send notifications (handled by a listener).
 *   - Access sessions or request globals.
 */
class UnsuspendSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly MemberAccessService     $accessService,
        private readonly Database                $database,
    )
    {
    }

    /**
     * Execute the unsuspension.
     *
     * @param int $subscriptionId The subscription to unsuspend.
     * @param int $memberId Used to verify ownership.
     * @param int $agentId Acting admin/agent.
     * @param string|null $reason Optional note for the audit trail.
     * @param int $siteId Scopes the subscription lookup to this site.
     *
     * @return object The refreshed Subscription record after unsuspension.
     *
     * @throws \InvalidArgumentException For business-rule violations.
     */
    public function execute(
        int     $subscriptionId,
        int     $memberId,
        int     $agentId,
        ?string $reason,
        int     $siteId,
    ): object
    {
        $reason = trim((string) $reason);

        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        if ($subscription->site_id !== $siteId) {
            throw new \InvalidArgumentException("Subscription does not belong to this site.");
        }

        if ($subscription->member_id !== $memberId) {
            throw new \InvalidArgumentException("Subscription does not belong to this member.");
        }

        if ($subscription->status !== SubscriptionStatus::SUSPENDED->value) {
            throw new \InvalidArgumentException("Subscription is not suspended.");
        }

        $now = new \DateTime();

        if ($subscription->end_date && $subscription->end_date < $now) {
            throw new \InvalidArgumentException(
                'Subscription entitlement period has ended. Please purchase a new subscription.'
            );
        }

        return $this->database->transaction(function () use (
            $subscription,
            $agentId,
            $reason,
        ): object {
            $timestamp = now_datetime()->format('Y-m-d H:i:s');

            $this->subscriptionRepository->update($subscription->id, [
                'status' => SubscriptionStatus::ACTIVE->value,
                'suspended_at' => null,
                'auto_renew' => true,
            ]);

            $refreshed = $this->subscriptionRepository->find($subscription->id);

            if ($refreshed->end_date) {
                $accessUntil = \DateTimeImmutable::createFromInterface(
                    \DateTime::createFromInterface($refreshed->end_date)
                );

                $this->accessService->refreshSubscriptionAccess($refreshed, $accessUntil);
            }

            event(new SubscriptionUnsuspended(
                subscriptionId: $subscription->id,
                memberId: (int) $subscription->member_id,
                agentId: $agentId,
                reason: $reason,
                timestamp: $timestamp,
            ));

            Logger::info('Subscription unsuspended', [
                'subscription_id' => $subscription->id,
                'agent_id' => $agentId,
                'reason' => $reason,
            ]);

            return $refreshed;
        });
    }
}
