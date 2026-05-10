<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionSuspended;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Suspends a subscription as an admin/system enforcement action.
 *
 * Distinct from pause:
 *   - Suspension is irreversible without admin intervention.
 *   - Entitlement is revoked immediately (premium access removed).
 *   - Billing is NOT automatically paused — that is a separate billing-layer
 *     concern handled outside this action.
 *
 * Allowed source statuses: active, paused, trialing, past_due.
 * (Suspended = enforcement; can come from any non-terminal state.)
 *
 * Emits SubscriptionSuspended for listeners (audit log, billing sync, etc.).
 *
 * This action does NOT:
 *   - Call Stripe directly (billing policy is handled by a listener).
 *   - Send notifications (handled by a listener).
 *   - Access sessions or request globals.
 */
class SuspendSubscriptionAction
{
    private const SUSPENDABLE_STATUSES = [
        'active',
        'trialing',
        'paused',
        'past_due',
    ];

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Database               $database,
    )
    {
    }

    /**
     * Execute the suspension.
     *
     * @param int $subscriptionId The subscription to suspend.
     * @param int $memberId Used to verify ownership.
     * @param int $agentId Acting admin/agent.
     * @param string $reason Mandatory suspension reason for audit trail.
     * @param int $siteId Scopes the subscription lookup to this site.
     *
     * @return object  The refreshed Subscription record after suspension.
     *
     * @throws \InvalidArgumentException  For business-rule violations.
     */
    public function execute(
        int    $subscriptionId,
        int    $memberId,
        int    $agentId,
        string $reason,
        int    $siteId,
    ): object
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required to suspend a subscription.');
        }

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

        if ($subscription->status === SubscriptionStatus::SUSPENDED->value ?? 'suspended') {
            throw new \InvalidArgumentException("Subscription is already suspended.");
        }

        if (!in_array($subscription->status, self::SUSPENDABLE_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Subscription cannot be suspended from status: {$subscription->status}."
            );
        }

        return $this->database->transaction(function () use (
            $subscription,
            $agentId,
            $reason,
        ): object {
            $now = now_datetime()->format('Y-m-d H:i:s');

            // Suspend and revoke entitlement immediately
            $this->subscriptionRepository->update($subscription->id, [
                'status' => 'suspended',
                'suspended_at' => $now,
                'auto_renew' => false,
            ]);

            // Revoke all premium access immediately (entitlement removal)
            $this->subscriptionRepository->revokeAllPremiumAccess($subscription->id);

            $refreshed = $this->subscriptionRepository->find($subscription->id);

            event(new SubscriptionSuspended(
                subscriptionId: $subscription->id,
                memberId: (int)$subscription->member_id,
                agentId: $agentId,
                reason: $reason,
                timestamp: $now,
            ));

            Logger::info('Subscription suspended', [
                'subscription_id' => $subscription->id,
                'agent_id' => $agentId,
                'reason' => $reason,
            ]);

            return $refreshed;
        });
    }
}