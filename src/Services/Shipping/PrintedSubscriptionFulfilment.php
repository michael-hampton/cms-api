<?php

namespace App\Services\Shipping;

use App\Models\SubscriptionPlan;

class PrintedSubscriptionFulfilment implements FulfilmentTypeInterface
{
    public function __construct(
        private readonly SubscriptionPlan $plan
    )
    {
    }

    public function requiresShipping(): bool
    {
        return true;
    }

    public function dispatchDays(): int
    {
        return $this->plan->dispatch_days ?? 3;
    }
}