<?php

namespace App\Actions\Subscriptions;

use App\Framework\Database\Database;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use App\Services\Subscriptions\PlanPricingDomainGuard;
use App\Services\Subscriptions\Validators\PricingCurrencyValidator;

/**
 * Replaces a plan price by versioning:
 *
 *   1. Validate currency and domain invariants upfront (no writes yet).
 *   2. Create a new Stripe Price (before the transaction — Stripe failure
 *      leaves DB untouched; the old price stays active).
 *   3. Inside a single transaction:
 *      a. Insert new PlanPricing row with the new stripe_price_id.
 *      b. Deactivate the old row and record replaced_by_price_id.
 *
 * Old Stripe Prices are never modified — existing subscriptions keep their
 * original stripe_price_id indefinitely.
 */
class ReplacePlanPriceAction
{
    public function __construct(
        private readonly SubscriptionPlanRepository        $planRepository,
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly StripePriceGatewayInterface       $stripePriceGateway,
        private readonly Database                          $database,
        private readonly PricingCurrencyValidator          $currencyValidator,
        private readonly PlanPricingDomainGuard            $domainGuard,
    )
    {
    }

    /**
     * @param int $currentPricingId ID of the active PlanPricing row to replace.
     * @param array $newPricingData Required: amount_cents (int), currency (string), interval (string).
     *                                 Optional overrides for any copied field (price, label, is_default, etc.).
     *                                 Fields not provided are copied from the current row.
     */
    public function execute(int $currentPricingId, array $newPricingData): SubscriptionPlanPricing
    {
        $currentPricing = $this->pricingRepository->find($currentPricingId);

        if (!$currentPricing) {
            throw new \RuntimeException("PlanPricing {$currentPricingId} not found.");
        }

        if (!$currentPricing->is_active) {
            throw new \RuntimeException(
                "PlanPricing {$currentPricingId} is already inactive and cannot be replaced."
            );
        }

        $plan = $this->planRepository->find($currentPricing->plan_id);

        if (!$plan || !$plan->stripe_product_id) {
            throw new \RuntimeException(
                "Plan {$currentPricing->plan_id} does not have a stripe_product_id."
            );
        }

        // 1. Validate currency before touching Stripe or the DB.
        $currency = $this->currencyValidator->validate($newPricingData['currency']);

        // 2. Enforce domain invariants.
        //    Exclude the current (soon-to-be-inactive) row from conflict checks.
        $newIsDefault = (bool)($newPricingData['is_default'] ?? $currentPricing->is_default);

        $this->domainGuard->assertNoDefaultConflict(
            $currentPricing->plan_id,
            $newIsDefault,
            excludePricingId: $currentPricingId,
        );

        $newSortOrder = isset($newPricingData['sort_order'])
            ? (int)$newPricingData['sort_order']
            : $currentPricing->sort_order;

        $this->domainGuard->assertUniqueSortOrder(
            $currentPricing->plan_id,
            $newSortOrder,
            excludePricingId: $currentPricingId,
        );

        // 3. Create the new Stripe Price before the transaction.
        //    Stripe failure → DB is untouched; old price stays active.
        //    DB failure → orphaned Stripe price (acceptable; it is unused and immutable).
        $effectivePricing = $this->buildEffectivePricing($currentPricing, $newPricingData);

        $stripePriceId = $this->stripePriceGateway->createRecurringPrice(
            $plan->stripe_product_id,
            $this->toAmountCents($effectivePricing->getStripeBillingPriceForPlan($plan)),
            $currency,
            $newPricingData['interval'] ?? 'month',
        );

        // 4. Both DB writes in one transaction.
        return $this->database->transaction(function () use (
            $currentPricing,
            $newPricingData,
            $stripePriceId,
            $currency,
        ): SubscriptionPlanPricing {
            $newRow = $this->buildNewPricingRow($currentPricing, $newPricingData, $stripePriceId, $currency);

            $newPricing = $this->pricingRepository->create($newRow);

            $this->pricingRepository->update($currentPricing->id, [
                'is_active' => false,
                'replaced_by_price_id' => $newPricing->id,
            ]);

            return $newPricing;
        });
    }

    private function buildNewPricingRow(
        SubscriptionPlanPricing $current,
        array                   $overrides,
        string                  $stripePriceId,
        string                  $normalisedCurrency,
    ): array {
        return array_merge(
        // Copy all logical properties from the current row.
            [
                'plan_id'            => $current->plan_id,
                'entitlement_type'   => $current->entitlement_type,
                'duration_months'    => $current->duration_months,
                'issue_count'        => $current->issue_count,
                'price'              => $current->price,
                'sale_price'         => $current->sale_price,
                'digital_price'      => $current->digital_price,
                'digital_sale_price' => $current->digital_sale_price,
                'currency'           => $current->currency,
                'label'              => $current->label,
                'period_description' => $current->period_description,
                'is_default'         => $current->is_default,
                'sort_order'         => $current->sort_order,
                'discount_percentage'=> $current->discount_percentage,

                // Copy intro/trial fields from the current row — overrides may change them
                'trial_days'         => $current->trial_days,
                'intro_price'        => $current->intro_price,
                'intro_cycles'       => $current->intro_cycles,
            ],
            // Apply caller-supplied overrides.
            $overrides,
            // Always enforce these — callers cannot override them.
            [
                'currency'              => $normalisedCurrency,
                'stripe_price_id'       => $stripePriceId,
                'stripe_intro_price_id' => null, // must re-sync after replacement
                'is_active'             => true,
            ]
        );
    }

    private function buildEffectivePricing(SubscriptionPlanPricing $current, array $overrides): SubscriptionPlanPricing
    {
        $pricing = clone $current;

        foreach ([
            'price',
            'sale_price',
            'digital_price',
            'digital_sale_price',
        ] as $field) {
            $pricing->{$field} = array_key_exists($field, $overrides)
                ? $overrides[$field]
                : $current->{$field};
        }

        return $pricing;
    }

    private function toAmountCents(float $amount): int
    {
        return (int)round($amount * 100);
    }
}
