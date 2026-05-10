<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

/**
 * Fired when an admin/agent suspends a subscription.
 *
 * Distinct from SubscriptionPaused — suspension is an enforcement action
 * that revokes entitlement immediately. Listeners handle billing sync,
 * audit logging, and any notification requirements.
 */
class SubscriptionSuspended
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