<?php

namespace App\Events\Subscriptions;

use App\Models\SubscriptionSegment;

class SubscriptionSegmentAssigned
{
    public function __construct(
        public readonly SubscriptionSegment $subscriptionSegment,
    ) {
    }
}