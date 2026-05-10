<?php

namespace App\Events\Subscriptions;

final class SubscriptionRenewedAndReplaced
{
    public function __construct(
        public readonly int    $memberId,
        public readonly int    $oldSubscriptionId,
        public readonly int    $newSubscriptionId,
        public readonly int    $productId,
        public readonly int    $planId,
        public readonly float  $amountPaid,
        public readonly int    $agentId,
        public readonly string $timestamp,
    )
    {
    }
}
