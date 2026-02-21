<?php

namespace App\Events\Subscriptions;

use App\Models\Subscription;

class SubscriptionResumed
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int          $memberId,
    )
    {
    }
}