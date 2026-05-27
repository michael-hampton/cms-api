<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\Models\SubscriptionPlan;

class SubscriptionCheckoutPreparationResult
{
    public function __construct(
        public readonly SubscriptionPlan $plan,
        public readonly object $paymentMethod,
        public readonly ResolvedSubscriptionPrice $resolvedPrice,
    ) {
    }
}
