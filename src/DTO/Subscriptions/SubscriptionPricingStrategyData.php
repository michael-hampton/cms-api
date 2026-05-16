<?php

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStrategyType;

final class SubscriptionPricingStrategyData
{
    public function __construct(
        public readonly SubscriptionStrategyType $type,
        public readonly bool                     $hasTrial,
        public readonly ?int                     $trialDays,
        public readonly bool                     $hasIntroPricing,
        public readonly ?float                   $introPrice,
        public readonly ?int                     $introCycles,
    ) {}

    public function isStandard(): bool
    {
        return $this->type === SubscriptionStrategyType::STANDARD;
    }

    public function requiresSchedule(): bool
    {
        return $this->hasIntroPricing;
    }
}