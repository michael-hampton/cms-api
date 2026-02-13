<?php

namespace App\Services\Shipping;

use App\Models\SubscriptionPlan;

class DigitalSubscriptionFulfilment implements FulfilmentTypeInterface
{
    public function __construct(
        private readonly SubscriptionPlan $plan
    )
    {
    }

    public function requiresShipping(): bool
    {
        return false;
    }

    public function dispatchDays(): int
    {
        return 0;
    }
}