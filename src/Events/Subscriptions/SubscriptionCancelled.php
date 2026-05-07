<?php

namespace App\Events\Subscriptions;

class SubscriptionCancelled
{
    public function __construct(
        public readonly int     $subscriptionId,
        public readonly bool    $cancelAtPeriodEnd,
        public readonly ?string $endDate,
    )
    {
    }
}