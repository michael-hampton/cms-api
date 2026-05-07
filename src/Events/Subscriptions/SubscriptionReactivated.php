<?php

namespace App\Events\Subscriptions;

class SubscriptionReactivated
{
    public function __construct(
        public readonly int  $subscriptionId,
        public readonly ?int $daysRemaining,
    )
    {
    }
}