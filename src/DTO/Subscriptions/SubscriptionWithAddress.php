<?php

namespace App\DTO\Subscriptions;

use App\Models\Subscription;

class SubscriptionWithAddress
{
    public function __construct(
        public Subscription $subscription,
        public string       $postcode,
        public string       $type,
        public bool         $isDefault
    )
    {
    }
}