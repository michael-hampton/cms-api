<?php

namespace App\Actions\Subscriptions;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\DTO\Subscriptions\SubscriptionPricingStrategyData;
use App\Enums\Subscriptions\SubscriptionStrategyType;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Services\Billing\Stripe\SubscriptionPricingStrategyResolver;
use App\Services\Subscriptions\Calculators\SubscriptionPricingResolver;

/**
 * Orchestrates the two-phase pricing pipeline:
 *
 *   Phase 1 — SubscriptionPricingResolver
 *             "What is the correct price for this tier/variant/voucher?"
 *             → ResolvedSubscriptionPrice
 *
 *   Phase 2 — SubscriptionPricingStrategyResolver
 *             "How should Stripe bill this?"
 *             → SubscriptionPricingStrategyData
 *
 * The action then dispatches to the correct gateway based on strategy type.
 * Gateway calls live in Ticket 3.
 */
class ResolveBillingStrategyAction
{
    public function __construct(
        private readonly SubscriptionPricingResolver         $pricingResolver,
        private readonly SubscriptionPricingStrategyResolver $strategyResolver,
    ) {}

    /**
     * @return array{price: ResolvedSubscriptionPrice, strategy: SubscriptionPricingStrategyData}
     */
    public function resolve(
        SubscriptionPlan        $plan,
        SubscriptionPlanPricing $pricingTier,
        array                   $data,
        int                     $memberId,
    ): array {
        $price    = $this->pricingResolver->resolve($plan, $data, $memberId);
        $strategy = $this->strategyResolver->resolve($pricingTier);

        return compact('price', 'strategy');
    }
}