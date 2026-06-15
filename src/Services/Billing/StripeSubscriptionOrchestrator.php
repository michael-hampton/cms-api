<?php

namespace App\Services\Billing;

use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
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
        private readonly SubscriptionRepository $subscriptionRepository,
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
        $currency = $this->resolveCurrency($subscription, $pricingTier, $plan);

        $this->assertCustomerCurrencyIsCompatible($subscription, $member, $currency);

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

        $trialDays = $plan->trial_days ?? 0;

        if ($trialDays > 0) {
            if ($this->subscriptionRepository->memberHadTrialOnPlan($member->id, $plan->id)) {
                $trialDays = 0;
            }
        }

        // 4. Create the Stripe subscription via billing service
        $result = $this->billingService->createSubscription(
            $subscription,
            $plan,
            $pricingTier,
            $customerId,
            $trialDays
        );

        // 5. Persist Stripe IDs back to the local subscription record
        $this->persistResult($subscription, $result, $trialDays > 0);

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

    private function resolveCurrency(
        Subscription $subscription,
        SubscriptionPlanPricing $pricingTier,
        SubscriptionPlan $plan,
    ): string {
        return strtoupper(
            trim((string)($pricingTier->currency ?: $subscription->currency ?: $plan->currency ?: 'GBP'))
        );
    }

    private function assertCustomerCurrencyIsCompatible(
        Subscription $subscription,
        Member $member,
        string $currency,
    ): void {
        if (empty($member->stripe_customer_id)) {
            return;
        }

        $existing = $this->subscriptionRepository->findActiveWithDifferentCurrency(
            $member->id,
            $currency,
            $subscription->id,
        );

        if ($existing === null) {
            return;
        }

        throw new \RuntimeException(
            "Cannot create a {$currency} Stripe subscription for this member because their existing Stripe customer has an active {$existing->currency} subscription."
        );
    }

    private function persistResult(
        Subscription              $subscription,
        StripeSubscriptionResultDto $result,
        bool $hasTrial = false
    ): void {
        $subscription->update([
            'payment_subscription_id' => $result->stripeSubscriptionId,
            'stripe_schedule_id'      => $result->stripeScheduleId,
            'stripe_customer_id'      => $result->stripeCustomerId,
            'stripe_subscription_item_id' => $result->stripeSubscriptionItemId,
            'status'                  => $result->status,
            'current_period_start'    => $result->currentPeriodStart
                ? date('Y-m-d H:i:s', $result->currentPeriodStart)
                : null,
            'current_period_end'      => $result->currentPeriodEnd
                ? date('Y-m-d H:i:s', $result->currentPeriodEnd)
                : null,
            'trial_used_at' => $hasTrial ? now() : null,
            'type' => 'paid'
        ]);
    }
}
