<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

/**
 * Fired after a successful subscription product/publication switch.
 */
class SubscriptionProductChanged
{
    public function __construct(
        public readonly int    $memberId,
        public readonly int    $oldSubscriptionId,
        public readonly int    $newSubscriptionId,
        public readonly int    $oldPlanId,
        public readonly int    $newPlanId,
        public readonly string $switchMode,        // 'transfer' | 'fresh'
        public readonly float  $carriedOverCredit, // 0.00 for Mode B
        public readonly int    $agentId,
        public readonly string $timestamp,
    )
    {
    }
}