<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\PricingEntitlementType;
use App\Enums\Subscriptions\SubscriptionEntitlementType;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;

class SubscriptionEntitlementResolver
{
    public function resolve(
        SubscriptionPlan $plan,
        SubscriptionPlanPricing $pricing
    ): SubscriptionEntitlementType {
        $planType = SubscriptionEntitlementType::tryFrom((string)($plan->entitlement_type ?? 'time'));

        if (!$planType) {
            throw new \InvalidArgumentException('Plan entitlement_type is invalid.');
        }

        $pricingType = $pricing->entitlement_type !== null
            ? PricingEntitlementType::tryFrom((string)$pricing->entitlement_type)
            : null;

        if ($pricing->entitlement_type !== null && !$pricingType) {
            throw new \InvalidArgumentException('Pricing entitlement_type must be time or issues.');
        }

        if ($planType === SubscriptionEntitlementType::MIXED) {
            if (!$pricingType) {
                throw new \InvalidArgumentException('Mixed plans require pricing entitlement_type.');
            }

            return SubscriptionEntitlementType::from($pricingType->value);
        }

        if ($pricingType && $pricingType->value !== $planType->value) {
            throw new \InvalidArgumentException('Pricing entitlement_type must match the plan entitlement_type.');
        }

        return $planType;
    }
}
