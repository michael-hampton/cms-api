<?php

namespace App\Actions\Subscriptions;

use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use App\Services\Subscriptions\PlanPricingDomainGuard;
use App\Services\Subscriptions\Validators\PricingCurrencyValidator;

/**
 * Adds a price to an existing plan and creates the corresponding Stripe Price.
 *
 * Rules:
 *   - The plan must already have a stripe_product_id before a price can be added.
 *   - A new PlanPricing row is created (stripe_price_id null initially).
 *   - Stripe Price is created once and the ID is stored locally.
 *   - Stripe Prices are never modified in-place; use ReplacePlanPriceAction instead.
 *   - Currency must be on the supported whitelist.
 *   - Domain invariants (default conflict, sort_order uniqueness) are enforced
 *     before any writes occur.
 *
 * Flow:
 *   1. Validate currency.
 *   2. Enforce domain invariants.
 *   3. Verify plan has stripe_product_id.
 *   4. Persist PlanPricing without stripe_price_id.
 *   5. Create Stripe Price.
 *   6. Store stripe_price_id on the PlanPricing row.
 */
class AddPlanPriceAction
{
    public function __construct(
        private readonly SubscriptionPlanRepository        $planRepository,
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly StripePriceGatewayInterface       $stripePriceGateway,
        private readonly PricingCurrencyValidator          $currencyValidator,
        private readonly PlanPricingDomainGuard            $domainGuard,
    )
    {
    }

    /**
     * @param int $planId
     * @param array $pricingData Required: amount_cents (int), currency (string), interval (string).
     *                            Optional: is_default (bool), sort_order (int), and any other
     *                            SubscriptionPlanPricing fillable fields.
     */
    public function execute(int $planId, array $pricingData): SubscriptionPlanPricing
    {
        // 1. Validate currency before touching the DB or Stripe.
        $currency = $this->currencyValidator->validate($pricingData['currency']);

        // 2. Enforce domain invariants.
        $this->domainGuard->assertNoDefaultConflict(
            $planId,
            (bool)($pricingData['is_default'] ?? false),
        );

        if (isset($pricingData['sort_order'])) {
            $this->domainGuard->assertUniqueSortOrder($planId, (int)$pricingData['sort_order']);
        }

        // 3. Retrieve plan and assert it has a Stripe product.
        $plan = $this->planRepository->find($planId);

        if (!$plan) {
            throw new \RuntimeException("Plan {$planId} not found.");
        }

        if (!$plan->stripe_product_id) {
            throw new \RuntimeException(
                "Plan {$planId} does not have a stripe_product_id. Create the Stripe product first."
            );
        }

        // 4. Persist PlanPricing without stripe_price_id.
        $pricing = $this->pricingRepository->create(array_merge(
            $pricingData,
            ['plan_id' => $planId, 'currency' => $currency, 'stripe_price_id' => null]
        ));

        // 5. Create Stripe Price — throws on failure; pricing row exists but stripe_price_id stays null.
        $stripePriceId = $this->stripePriceGateway->createRecurringPrice(
            $plan->stripe_product_id,
            $pricingData['amount_cents'],
            $currency,
            $pricingData['interval'] ?? 'month',
        );

        // 6. Store the Stripe Price ID.
        $this->pricingRepository->update($pricing->id, ['stripe_price_id' => $stripePriceId]);

        if (!$plan->stripe_price_id) {
            $this->planRepository->update($plan->id, ['stripe_price_id' => $stripePriceId]);
        }

        $pricing->stripe_price_id = $stripePriceId;

        return $pricing;
    }
}