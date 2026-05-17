<?php

namespace App\Services\Billing;

use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Subscriptions\SubscriptionBillingService;

/**
 * Orchestrates the full Stripe subscription creation workflow.
 *
 * Responsibilities:
 *   1. Resolve the correct pricing tier
 *   2. Resolve or create the Stripe customer
 *   3. Attach the payment method when provided
 *   4. Delegate to SubscriptionBillingService for strategy + gateway dispatch
 *   5. Persist Stripe IDs back to the local subscription record
 *
 * Call sites pass in the local Subscription, Plan, Member, and payment data.
 * They get back a StripeSubscriptionResultDto and a fully updated Subscription.
 *
 * Does NOT:
 *   - Contain Stripe SDK calls (delegated to gateways)
 *   - Contain billing strategy logic (delegated to SubscriptionBillingService)
 *   - Write orders or payments (caller's responsibility)
 */
class StripeSubscriptionOrchestrator
{
    public function __construct(
        private readonly StripeCustomerGateway              $customerGateway,
        private readonly SubscriptionBillingService         $billingService,
        private readonly SubscriptionPlanPricingRepository  $pricingRepository,
    ) {}

    /**
     * Execute the full subscription creation workflow.
     *
     * @param array $data Supported keys:
     *                    - payment_method_id (string|null)
     *                    - pricing_tier_id   (int|null)  — falls back to plan default
     *
     * @throws \RuntimeException When no usable pricing tier exists.
     * @throws \RuntimeException When the Stripe API call fails.
     */
    public function create(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        Member           $member,
        array            $data = [],
    ): StripeSubscriptionResultDto {
        // 1. Resolve pricing tier
        $pricingTier = $this->resolvePricingTier($plan, $data);

        // 2. Resolve customer address and get or create Stripe customer
        $address    = $member->resolveBillingAddress();
        $customerId = $this->customerGateway->getOrCreate($member, $address);

        // 3. Attach payment method when provided
        if (!empty($data['payment_method_id'])) {
            $this->customerGateway->attachPaymentMethod(
                $customerId,
                $data['payment_method_id'],
            );
        }

        // 4. Create the Stripe subscription via billing service
        $result = $this->billingService->createSubscription(
            $subscription,
            $plan,
            $pricingTier,
            $customerId,
        );

        // 5. Persist Stripe IDs back to the local subscription record
        $this->persistResult($subscription, $result);

        return $result;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function resolvePricingTier(SubscriptionPlan $plan, array $data): \App\Models\SubscriptionPlanPricing
    {
        // Explicit tier ID takes precedence — used when the caller knows
        // exactly which tier was selected (e.g. from the cart)
        if (!empty($data['pricing_tier_id'])) {
            $tier = $this->pricingRepository->find((int) $data['pricing_tier_id']);

            if ($tier && $tier->plan_id === $plan->id && $tier->is_active) {
                return $tier;
            }
        }

        // Fall back to the plan's default pricing tier
        $tier = $plan->getDefaultPricing();

        if ($tier === null) {
            throw new \RuntimeException(
                "Plan #{$plan->id} has no active pricing tier. " .
                "Ensure at least one tier is active and marked as default."
            );
        }

        return $tier;
    }

    private function persistResult(
        Subscription              $subscription,
        StripeSubscriptionResultDto $result,
    ): void {
        $subscription->update([
            'payment_subscription_id' => $result->stripeSubscriptionId,
            'stripe_schedule_id'      => $result->stripeScheduleId,
            'stripe_customer_id'      => $result->stripeCustomerId,
            'status'                  => $result->status,
            'current_period_start'    => $result->currentPeriodStart
                ? date('Y-m-d H:i:s', $result->currentPeriodStart)
                : null,
            'current_period_end'      => $result->currentPeriodEnd
                ? date('Y-m-d H:i:s', $result->currentPeriodEnd)
                : null,
        ]);
    }
}