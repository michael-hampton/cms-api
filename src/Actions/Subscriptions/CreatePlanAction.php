<?php

namespace App\Actions\Subscriptions;

use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;

/**
 * Creates a SubscriptionPlan and a corresponding Stripe Product.
 *
 * Stripe Products map 1-to-1 with logical plans. Plan variants do NOT
 * create separate Stripe Products — they share the same stripe_product_id.
 *
 * Flow:
 *   1. Persist the plan (stripe_product_id = null).
 *   2. Create Stripe Product.
 *   3. Store stripe_product_id on the plan.
 *      → If the DB update fails, compensate by deleting the Stripe product
 *        so we never leave orphans in Stripe.
 *
 * Failure states:
 *   - Stripe fails before DB update  → plan exists locally without stripe_product_id;
 *     exception re-thrown; caller may retry.
 *   - DB update fails after Stripe   → Stripe product is deleted (compensation),
 *     exception re-thrown.
 */
class CreatePlanAction
{
    public function __construct(
        private readonly SubscriptionPlanRepository    $planRepository,
        private readonly StripeProductGatewayInterface $stripeProductGateway,
    )
    {
    }

    public function execute(array $planData): SubscriptionPlan
    {
        // 1. Persist plan without stripe_product_id.
        $plan = $this->planRepository->create($planData);

        // 2. Create Stripe Product — throws on Stripe failure; plan persists
        //    without a product ID so the caller knows to retry.
        $stripeProductId = $this->stripeProductGateway->createProduct($plan->name);

        // 3. Store the Stripe product ID. If the DB update fails, compensate
        //    by deleting the Stripe product to avoid orphaned Stripe objects.
        try {
            $this->planRepository->update($plan->id, ['stripe_product_id' => $stripeProductId]);
        } catch (\Throwable $e) {
            $this->stripeProductGateway->deleteProduct($stripeProductId);
            throw $e;
        }

        $plan->stripe_product_id = $stripeProductId;

        return $plan;
    }
}