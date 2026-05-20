<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Subscriptions\SubscriptionPricingStrategyData;
use App\Enums\Subscriptions\SubscriptionStrategyType;
use App\Models\SubscriptionPlanPricing;
use App\Services\Billing\Stripe\Contracts\SubscriptionPricingStrategyInterface;

/**
 * Classifies a pricing tier into a billing strategy.
 *
 * This is pure classification — no Stripe SDK calls, no DB writes.
 * Downstream gateways use the returned DTO to decide which Stripe API to call.
 *
 * Composition rule:
 *   1. SubscriptionPricingResolver  → resolves WHAT price to charge
 *   2. SubscriptionPricingStrategyResolver → resolves HOW to bill it
 * These run sequentially; neither knows about the other.
 */
class SubscriptionPricingStrategyResolver implements SubscriptionPricingStrategyInterface
{
    public function resolve(SubscriptionPlanPricing $pricing, int $trialDaysOverride = 0): SubscriptionPricingStrategyData
    {
        $hasTrial = $trialDaysOverride > 0 && $pricing->hasTrial();

        $hasIntro = $pricing->hasIntroPricing();

        $type = match (true) {
            $hasTrial && $hasIntro => SubscriptionStrategyType::TRIAL_INTRO,
            $hasTrial              => SubscriptionStrategyType::TRIAL,
            $hasIntro              => SubscriptionStrategyType::INTRO,
            default                => SubscriptionStrategyType::STANDARD,
        };

        return new SubscriptionPricingStrategyData(
            type:            $type,
            hasTrial:        $hasTrial,
            trialDays:       $pricing->trial_days,
            hasIntroPricing: $hasIntro,
            introPrice:      $pricing->intro_price !== null ? (float) $pricing->intro_price : null,
            introCycles:     $pricing->intro_cycles,
        );
    }
}