<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

/**
 * Fired when an admin/agent reverses an enforcement suspension via
 * UnsuspendSubscriptionAction.
 *
 * Distinct from SubscriptionReactivated — that event is for reactivating a
 * CANCELLED subscription (SubscriptionCancellationService::reactivateSubscription).
 * This one is specifically for lifting a SUSPENDED enforcement state.
 */
class SubscriptionUnsuspended
{
    public function __construct(
        public readonly int    $subscriptionId,
        public readonly int    $memberId,
        public readonly int    $agentId,
        public readonly string $reason,
        public readonly string $timestamp,
    )
    {
    }
}
