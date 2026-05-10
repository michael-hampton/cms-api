<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionEndReason;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionProductChanged;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;

/**
 * Handles switching an active subscription from one product/plan to another.
 *
 * Two modes:
 *   Mode A — transfer: pro-rated credit from the old subscription is applied
 *             to the new one (carried_over_credit). Agent still pays the
 *             balance via Stripe before any DB mutations occur.
 *
 *   Mode B — fresh: full price charged, no credit carried over.
 *             Functionally the same as renew but to a different plan.
 *
 * In both modes:
 *   - Payment must succeed before any DB mutation.
 *   - Old subscription is ended (status=replaced, end_reason=product_change).
 *   - New subscription is created and linked to old via replaced_by / renewed_from.
 *
 * Credit calculation (Mode A):
 *   carried_over_credit = (price / total_days) × remaining_days
 *   The caller (controller) provides the already-calculated credit; this
 *   service accepts it as a parameter so calculation logic lives in a
 *   dedicated collaborator (SubscriptionCreditCalculator — future ticket).
 *   For now the controller derives it from the old subscription's dates.
 */
class SubscriptionProductSwitchService
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly StripePaymentProcessor     $stripeProcessor,
        private readonly SubscriptionDateCalculator $dateCalculator,
        private readonly Database                   $database,
    )
    {
    }

    /**
     * Switch an active subscription to a new plan.
     *
     * @param int $subscriptionId The subscription being switched.
     * @param int $newPlanId The target plan.
     * @param string $switchMode 'transfer' | 'fresh'
     * @param string $paymentMethodId Stripe pm_xxx — charged before DB mutations.
     * @param float $amountToCharge Amount due after any credit deduction.
     * @param float $carriedOverCredit Monetary credit carried from old sub (0 for Mode B).
     * @param int $agentId Acting CRM agent.
     * @param int $siteId Current site.
     *
     * @return array{ old_subscription: object, new_subscription: object }
     *
     * @throws \InvalidArgumentException  For business-rule violations.
     * @throws \RuntimeException          For payment or persistence failures.
     */
    public function switch(
        int    $subscriptionId,
        int    $newPlanId,
        string $switchMode,
        string $paymentMethodId,
        float  $amountToCharge,
        float  $carriedOverCredit,
        int    $agentId,
        int    $siteId,
    ): array
    {
        if (!in_array($switchMode, ['transfer', 'fresh'], true)) {
            throw new \InvalidArgumentException("switchMode must be 'transfer' or 'fresh'.");
        }

        // ── Validate ───────────────────────────────────────────────────────
        $oldSubscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$oldSubscription) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        if ($oldSubscription->site_id !== $siteId) {
            throw new \InvalidArgumentException("Subscription does not belong to this site.");
        }

        if ($oldSubscription->status !== SubscriptionStatus::ACTIVE->value) {
            throw new \InvalidArgumentException(
                "Only active subscriptions can be switched. Current status: {$oldSubscription->status}."
            );
        }

        $newPlan = $this->planRepository->find($newPlanId);

        if (!$newPlan || !$newPlan->is_active) {
            throw new \InvalidArgumentException("Target plan #{$newPlanId} not found or inactive.");
        }

        if ($newPlan->site_id !== $siteId) {
            throw new \InvalidArgumentException("Target plan does not belong to this site.");
        }

        if ($newPlanId === (int)$oldSubscription->plan_id) {
            throw new \InvalidArgumentException("Target plan is the same as the current plan.");
        }

        // ── Charge payment BEFORE DB mutations ────────────────────────────
        $paymentResult = $this->stripeProcessor->processSubscriptionPayment(
            $oldSubscription,
            $newPlan,
            [
                'payment_method_id' => $paymentMethodId,
                'amount' => $amountToCharge,
                'metadata' => [
                    'type' => 'subscription_product_switch',
                    'old_sub_id' => $subscriptionId,
                    'switch_mode' => $switchMode,
                    'credit_applied' => $carriedOverCredit,
                    'agent_id' => $agentId,
                ],
            ],
        );

        if (!($paymentResult['success'] ?? false)) {
            throw new \RuntimeException(
                'Payment failed: ' . ($paymentResult['message'] ?? 'Unknown error')
            );
        }

        // ── Atomic DB transaction ─────────────────────────────────────────
        return $this->database->transaction(function () use (
            $oldSubscription,
            $newPlan,
            $switchMode,
            $paymentResult,
            $amountToCharge,
            $carriedOverCredit,
            $agentId,
        ): array {
            $now = now_datetime();

            // Step 1 — End old subscription
            $this->subscriptionRepository->update($oldSubscription->id, [
                'status' => 'replaced',
                'ended_at' => $now->format('Y-m-d H:i:s'),
                'end_reason' => SubscriptionEndReason::PRODUCT_CHANGE->value,
                'auto_renew' => false,
            ]);

            // Step 2 — Dates for new subscription
            $startDate = new \DateTimeImmutable($now->format('Y-m-d H:i:s'));
            $endDate = $newPlan->billing_period !== 'lifetime'
                ? $this->dateCalculator->calculateEndDate(
                    $startDate,
                    \App\Enums\Subscriptions\BillingPeriod::from($newPlan->billing_period)
                )
                : null;

            // Step 3 — Create new subscription
            $newSubscription = $this->subscriptionRepository->createSubscription(
                memberId: (int)$oldSubscription->member_id,
                planId: $newPlan->id,
                siteId: (int)$oldSubscription->site_id,
                additionalData: [
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date' => $endDate?->format('Y-m-d H:i:s'),
                    'next_billing_date' => $endDate?->format('Y-m-d H:i:s'),
                    'price' => $amountToCharge,
                    'delivery_type' => $oldSubscription->delivery_type,
                    'delivery_address_id' => $oldSubscription->delivery_address_id ?? null,
                    'payment_subscription_id' => $paymentResult['subscription_id'] ?? null,
                    'renewed_from_subscription_id' => $oldSubscription->id,
                    'replacement_reason' => SubscriptionEndReason::PRODUCT_CHANGE->value,
                    'carried_over_credit' => $carriedOverCredit,
                    'status' => SubscriptionStatus::ACTIVE->value,
                ],
            );

            // Step 4 — Back-link old to new
            $this->subscriptionRepository->update($oldSubscription->id, [
                'replaced_by_subscription_id' => $newSubscription->id,
            ]);

            // Step 5 — Event
            event(new SubscriptionProductChanged(
                memberId: (int)$oldSubscription->member_id,
                oldSubscriptionId: $oldSubscription->id,
                newSubscriptionId: $newSubscription->id,
                oldPlanId: (int)$oldSubscription->plan_id,
                newPlanId: $newPlan->id,
                switchMode: $switchMode,
                carriedOverCredit: $carriedOverCredit,
                agentId: $agentId,
                timestamp: $now->format('Y-m-d H:i:s'),
            ));

            Logger::info('Subscription product switched', [
                'old_subscription_id' => $oldSubscription->id,
                'new_subscription_id' => $newSubscription->id,
                'switch_mode' => $switchMode,
                'agent_id' => $agentId,
            ]);

            return [
                'old_subscription' => $this->subscriptionRepository->find($oldSubscription->id),
                'new_subscription' => $newSubscription,
            ];
        });
    }

    /**
     * Calculate the pro-rated monetary credit remaining on a subscription.
     *
     * Formula: (price / total_days) × remaining_days
     * Returns 0.00 when dates are missing or subscription has already expired.
     */
    public function calculateCarriedOverCredit(object $subscription): float
    {
        $price = (float)($subscription->price ?? 0);
        $startDate = $subscription->start_date;
        $endDate = $subscription->end_date;

        if ($price <= 0 || !$startDate || !$endDate) {
            return 0.00;
        }

        // assume these are already DateTimeInterface
        $start = $startDate;
        $end = $endDate;
        $now = new \DateTimeImmutable();

        if ($now >= $end) {
            return 0.00;
        }

        $totalDays = max(1, (int)$start->diff($end)->days);
        $remainingDays = max(0, (int)$now->diff($end)->days);

        $dailyRate = $price / $totalDays;
        $credit = $dailyRate * $remainingDays;

        return round($credit, 2);
    }
}