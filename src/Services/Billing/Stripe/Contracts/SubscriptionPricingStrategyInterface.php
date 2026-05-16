<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Subscriptions\SubscriptionPricingStrategyData;
use App\Models\SubscriptionPlanPricing;

interface SubscriptionPricingStrategyInterface
{
    public function resolve(SubscriptionPlanPricing $pricing): SubscriptionPricingStrategyData;
}