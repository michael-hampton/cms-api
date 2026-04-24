<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Centralises all subscription access mutations.
 *
 * This service is the single place that touches premium access records on
 * behalf of billing events. Keeping it separate from the listeners means:
 *   - access logic is testable in isolation
 *   - listeners stay thin event-routers
 *   - when tier/bundle rules change, one class changes
 *
 * Design contract:
 *   - Every public method is idempotent — safe to call multiple times with
 *     the same arguments, will not duplicate entitlements.
 *   - This service NEVER calls Stripe.
 *   - This service NEVER sends notifications.
 *   - This service NEVER emits domain events.
 */
class MemberAccessService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger                 $logger,
    )
    {
    }

    /**
     * Idempotently ensures all access entitlements defined by the
     * subscription's plan are present and extended to $accessUntil.
     *
     * This is a "refresh", not a "rebuild":
     *   - Grants that already exist have their expiry extended.
     *   - Grants that are missing are created.
     *   - Grants that are not in the plan are left untouched.
     *     (Removing stale grants is a separate, deliberate operation.)
     *
     * Safe to call on every invoice.payment_succeeded — will not stack or
     * duplicate access records.
     */
    public function refreshSubscriptionAccess(
        Subscription       $subscription,
        \DateTimeImmutable $accessUntil,
    ): void
    {
        $plan = $subscription->plan;

        if (!$plan) {
            $this->logger->warning('MemberAccessService: subscription has no plan, skipping access refresh', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        $grants = $plan->getPremiumAccessGrants();

        if (empty($grants)) {
            $this->logger->info('MemberAccessService: plan defines no premium access grants', [
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
            ]);
            return;
        }

        foreach ($grants as $grant) {
            $subscription->grantPremiumAccess(
                $grant['type'],
                $grant['identifier'],
                \DateTime::createFromImmutable($accessUntil),
            );
        }

        // Backward-compat flag kept in sync with premium access state.
        if ($plan->grantsPremiumAccess('newsletter', 'insider')) {
            $subscription->update(['includes_digital_access' => true]);
        }

        $this->logger->info('MemberAccessService: access refreshed', [
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'grant_count' => count($grants),
            'access_until' => $accessUntil->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Revokes all premium access for a subscription.
     *
     * Called by the expiry job — never directly from a webhook listener.
     * Delegates to the existing repository method to keep revocation
     * logic in one place.
     */
    public function revokeSubscriptionAccess(Subscription $subscription): void
    {
        $this->subscriptionRepository->revokeAllPremiumAccess($subscription->id);

        $this->logger->info('MemberAccessService: access revoked', [
            'subscription_id' => $subscription->id,
        ]);
    }
}