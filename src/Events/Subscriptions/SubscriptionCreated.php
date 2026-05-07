<?php

namespace App\Events\Subscriptions;

class SubscriptionCreated
{
    public function __construct(
        public readonly int    $subscriptionId,
        public readonly int    $planId,
        public readonly string $billingPeriod,
        public readonly int    $priceCents,
        public readonly string $currency,
    )
    {
    }
}