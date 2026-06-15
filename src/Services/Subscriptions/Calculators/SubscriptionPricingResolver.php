<?php

namespace App\Services\Subscriptions\Calculators;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Services\Vouchers\VoucherService;

class SubscriptionPricingResolver
{
    public function __construct(
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly VoucherService                    $voucherService
    )
    {
    }

    /**
     * Resolve pricing for multiple items (batch processing)
     *
     * @param array $items Array of ['plan' => SubscriptionPlan, 'data' => array, 'member_id' => int]
     * @return array<ResolvedSubscriptionPrice>
     */
    public function resolveBatch(array $items): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $resolved[] = $this->resolve(
                $item['plan'],
                $item['data'],
                $item['member_id']
            );
        }

        return $resolved;
    }

    /**
     * Resolve subscription pricing based on plan, tier selection, variant, and voucher
     *
     * @param SubscriptionPlan $plan
     * @param array $data Contains: pricing_tier_id?, variant?, voucher_code?
     * @param int $memberId
     * @return ResolvedSubscriptionPrice
     * @throws \InvalidArgumentException
     */
    public function resolve(
        SubscriptionPlan $plan,
        array            $data,
        int              $memberId
    ): ResolvedSubscriptionPrice
    {
        $variant = $data['variant'] ?? SubscriptionType::PRINTED->value;
        $pricingTierId = $data['pricing_tier_id'] ?? null;
        $voucherCode = $data['voucher_code'] ?? null;

        // Validate variant
        if (!in_array($variant, [SubscriptionType::PRINTED->value, SubscriptionType::DIGITAL->value])) {
            throw new \InvalidArgumentException("Invalid variant: {$variant}. Must be 'print' or 'digital'.");
        }

        // Step 1: Resolve base pricing (tier or fallback to plan)
        if ($pricingTierId) {
            // Use specific pricing tier
            $pricingTier = $this->pricingRepository->find($pricingTierId);

            if (!$pricingTier || !$pricingTier->is_active) {
                throw new \InvalidArgumentException("Invalid or inactive pricing tier: {$pricingTierId}");
            }

            if ($pricingTier->plan_id !== $plan->id) {
                throw new \InvalidArgumentException("Pricing tier {$pricingTierId} does not belong to plan {$plan->id}");
            }

            // Get variant-specific pricing
            [$basePrice, $salePrice] = $this->extractPricesFromTier($pricingTier, $variant);

        } else {

            // Fallback: try to get default pricing tier
            $pricingTier = $this->pricingRepository->getDefaultForPlan($plan->id);

            if ($pricingTier) {
                [$basePrice, $salePrice] = $this->extractPricesFromTier($pricingTier, $variant);
            } else {
                // Ultimate fallback: use plan price
                $basePrice = $plan->price;
                $salePrice = null;
            }
        }

        // Step 2: Apply voucher discount if provided
        $discountAmount = 0;
        $voucherId = null;

        if ($voucherCode) {
            $voucherValidation = $this->voucherService->validateVoucherForSubscription(
                $voucherCode,
                $plan->id,
                $memberId,
                $pricingTierId,
                $variant
            );

            if ($voucherValidation->valid) {
                $voucherId = $voucherValidation->voucher->id;
                $discountAmount = $voucherValidation->discount;
            } else {
                throw new \InvalidArgumentException($voucherValidation->message);
            }
        }

        // Step 2a: No user voucher — check if the plan has an automatic promotion.
        // A promotion is a voucher attached to the plan via the pivot table that
        // requires no code entry. It is only applied when the member has not already
        // provided a voucher code (user codes take precedence).
        if ($voucherId === null) {
            $promotion = $this->voucherService->findActivePromotionForPlan($plan->id);

            if ($promotion !== null) {
                $effectivePrice = $salePrice ?? $basePrice;
                $discountAmount = $promotion->calculateSubscriptionDiscount($effectivePrice);
                $voucherId = $promotion->id;
            }
        }

        // Step 3: Build resolved price
        if ($pricingTier) {
            return ResolvedSubscriptionPrice::fromPricingTier(
                pricingTierId: $pricingTier->id,
                variant: $variant,
                basePrice: $basePrice,
                salePrice: $salePrice,
                currency: $plan->currency,
                discountAmount: $discountAmount,
                voucherId: $voucherId,
            );
        }

        return ResolvedSubscriptionPrice::fromPlanPrice(
            planPrice: $basePrice,
            currency: $plan->currency,
            variant: $variant,
            discountAmount: $discountAmount,
            voucherId: $voucherId
        );
    }

    /**
     * Extract appropriate prices from tier based on variant
     *
     * @return array{0: float, 1: float|null} [basePrice, salePrice]
     */
    private function extractPricesFromTier(SubscriptionPlanPricing $tier, string $variant): array
    {
        if ($variant === SubscriptionType::DIGITAL->value) {
            $basePrice = $tier->digital_price ?? $tier->price;
            $salePrice = $tier->digital_sale_price;
        } else {
            $basePrice = $tier->price;
            $salePrice = $tier->sale_price;
        }

        return [$basePrice, $salePrice];
    }

    /**
     * Find best matching pricing tier based on cart item options
     * Matches on duration_months and issue_count from cart options
     *
     * @param int $planId
     * @param array $options Cart item options containing duration_months and/or issue_count
     * @return SubscriptionPlanPricing|null
     */
    private function findMatchingTier(int $planId, array $options): ?SubscriptionPlanPricing
    {
        $durationMonths = $options['duration_months'] ?? null;
        $issueCount = $options['issue_count'] ?? null;

        // If neither duration nor issue count provided, return null to use default fallback
        if (!$durationMonths && !$issueCount) {
            return null;
        }

        // Build query to find matching tier
        $query = $this->pricingRepository->getActiveTiersForPlan($planId);

        // Match on both duration and issue count if both provided
        if ($durationMonths && $issueCount) {
            return $query->where('duration_months', $durationMonths)
                ->where('issue_count', $issueCount)
                ->first();
        }

        // Match on duration only
        if ($durationMonths) {
            return $query->where('duration_months', $durationMonths)
                ->first();
        }

        // Match on issue count only
        if ($issueCount) {
            return $query->where('issue_count', $issueCount)
                ->first();
        }

        return null;
    }
}