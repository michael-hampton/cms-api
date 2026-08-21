<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionOfferData;
use App\DTO\Subscriptions\SubscriptionOfferFilters;
use App\Enums\Subscriptions\OfferType;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionOfferRepository;

/**
 * Derives virtual CRM offer records from active pricing tiers.
 *
 * A single pricing tier may produce zero, one, or many offers:
 *   - Print offer   when sale_price < price
 *   - Digital offer when digital_sale_price < digital_price
 *   - Intro offer   when intro_price and intro_cycles > 0
 *   - Voucher offer for each voucher linked to the plan
 *
 * No persistence occurs here. All data is derived on-the-fly from the
 * SubscriptionOfferRepository and returned as paginated SubscriptionOfferData.
 */
class SubscriptionOfferSearchService
{
    public function __construct(
        private readonly SubscriptionOfferRepository $repository,
        private readonly ?SubscriptionEntitlementResolver $entitlementResolver = null,
    ) {}

    /**
     * @return array{
     *   items: SubscriptionOfferData[],
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   last_page: int,
     * }
     */
    public function search(SubscriptionOfferFilters $filters): array
    {
        $result = $this->repository->findPricingTiersForOffers($filters);

        $offers = [];

        $offerType = $filters->offerType;

        if ($filters->hasVoucher === true && $offerType === null) {
            $offerType = OfferType::VOUCHER;
        }

        foreach ($result['items'] as $tier) {
            $derived = $this->deriveOffersFromTier($tier, $offerType);

            foreach ($derived as $offer) {
                if (!$this->offerMatchesPriceRange($offer, $filters)) {
                    continue;
                }

                $offers[] = $offer;
            }
        }

        $lastPage = max(1, (int) ceil($result['total'] / $filters->perPage));

        return [
            'items'     => $offers,
            'total'     => $result['total'],
            'page'      => $filters->page,
            'per_page'  => $filters->perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Derive all applicable offers from one pricing tier.
     *
     * @return SubscriptionOfferData[]
     */
    private function deriveOffersFromTier(
        SubscriptionPlanPricing $tier,
        ?OfferType              $typeFilter,
    ): array
    {
        $offers = [];
        $plan   = $tier->plan;

        if ($plan === null) {
            return [];
        }

        $currency = $tier->currency ?? $plan->currency ?? null;

        // ── Print offer ────────────────────────────────────────────────────
        if ($typeFilter === null || $typeFilter === OfferType::PRINT) {
            $printOffer = $this->buildPrintOffer($tier, $plan->id, $plan->name, $currency);
            if ($printOffer !== null) {
                $offers[] = $printOffer;
            }
        }

        // ── Digital offer ──────────────────────────────────────────────────
        if ($typeFilter === null || $typeFilter === OfferType::DIGITAL) {
            $digitalOffer = $this->buildDigitalOffer($tier, $plan->id, $plan->name, $currency);
            if ($digitalOffer !== null) {
                $offers[] = $digitalOffer;
            }
        }

        // ── Intro offer ────────────────────────────────────────────────────
        if ($typeFilter === null || $typeFilter === OfferType::INTRO) {
            $introOffer = $this->buildIntroOffer($tier, $plan->id, $plan->name, $currency);
            if ($introOffer !== null) {
                $offers[] = $introOffer;
            }
        }

        // ── Voucher offer(s) ───────────────────────────────────────────────
        if ($typeFilter === null || $typeFilter === OfferType::VOUCHER) {
            $voucherOffers = $this->buildVoucherOffers($tier, $plan->id, $plan->name, $currency);
            foreach ($voucherOffers as $voucher) {
                $offers[] = $voucher;
            }
        }

        return $offers;
    }

    private function buildPrintOffer(
        SubscriptionPlanPricing $tier,
        int    $planId,
        string $planName,
        ?string $currency,
    ): ?SubscriptionOfferData
    {
        $price = is_numeric($tier->price) ? (float) $tier->price : null;
        $sale  = is_numeric($tier->sale_price) ? (float) $tier->sale_price : null;

        if ($price === null || $sale === null || $sale <= 0 || $sale >= $price) {
            return null;
        }

        return $this->buildOffer(
            planId:     $planId,
            planName:   $planName,
            tier:       $tier,
            offerType:  OfferType::PRINT,
            original:   $price,
            discounted: $sale,
            currency:   $currency,
        );
    }

    private function buildDigitalOffer(
        SubscriptionPlanPricing $tier,
        int    $planId,
        string $planName,
        ?string $currency,
    ): ?SubscriptionOfferData
    {
        $price = is_numeric($tier->digital_price) ? (float) $tier->digital_price : null;
        $sale  = is_numeric($tier->digital_sale_price) ? (float) $tier->digital_sale_price : null;

        if ($price === null || $sale === null || $sale <= 0 || $sale >= $price) {
            return null;
        }

        return $this->buildOffer(
            planId:     $planId,
            planName:   $planName,
            tier:       $tier,
            offerType:  OfferType::DIGITAL,
            original:   $price,
            discounted: $sale,
            currency:   $currency,
        );
    }

    private function buildIntroOffer(
        SubscriptionPlanPricing $tier,
        int    $planId,
        string $planName,
        ?string $currency,
    ): ?SubscriptionOfferData
    {
        $introPrice  = is_numeric($tier->intro_price) ? (float) $tier->intro_price : null;
        $introCycles = is_numeric($tier->intro_cycles) ? (int) $tier->intro_cycles : null;

        if ($introPrice === null || $introCycles === null || $introCycles <= 0) {
            return null;
        }

        // Base price for savings calculation — use print as the reference
        $basePrice = is_numeric($tier->price) ? (float) $tier->price : 0.0;

        return new SubscriptionOfferData(
            planId:           $planId,
            planName:         $planName,
            pricingId:        (int) $tier->id,
            offerType:        OfferType::INTRO,
            originalPrice:    $basePrice,
            offerPrice:       $introPrice,
            savingAmount:     $this->savingAmount($basePrice, $introPrice),
            savingPercentage: $this->savingPercentage($basePrice, $introPrice),
            pricingLabel:     $tier->label,
            entitlementType:  $tier->entitlement_type,
            effectiveEntitlementType: $this->resolveEffectiveEntitlementType($tier),
            introCycles:      $introCycles,
            currency:         $currency,
            createdAt:        $tier->created_at?->format('Y-m-d H:i:s'),
            updatedAt:        $tier->updated_at?->format('Y-m-d H:i:s'),
        );
    }

    /**
     * @return SubscriptionOfferData[]
     */
    private function buildVoucherOffers(
        SubscriptionPlanPricing $tier,
        int    $planId,
        string $planName,
        ?string $currency,
    ): array
    {
        $vouchers = $this->vouchersForTier($tier);

        $offers   = [];

        foreach ($vouchers as $voucher) {
            $basePrice = is_numeric($tier->price) ? (float) $tier->price : 0.0;

            // Derive the effective discounted price from the voucher for display
            $discountedPrice = $basePrice - $voucher->calculateSubscriptionDiscount($basePrice);
            $discountedPrice = max(0.0, $discountedPrice);

            $offers[] = new SubscriptionOfferData(
                planId:           $planId,
                planName:         $planName,
                pricingId:        (int) $tier->id,
                offerType:        OfferType::VOUCHER,
                originalPrice:    $basePrice,
                offerPrice:       $discountedPrice,
                savingAmount:     $this->savingAmount($basePrice, $discountedPrice),
                savingPercentage: $this->savingPercentage($basePrice, $discountedPrice),
                pricingLabel:     $tier->label,
                entitlementType:  $tier->entitlement_type,
                effectiveEntitlementType: $this->resolveEffectiveEntitlementType($tier),
                voucherCode:      $voucher->code,
                currency:         $currency,
                createdAt:        $tier->created_at?->format('Y-m-d H:i:s'),
                updatedAt:        $tier->updated_at?->format('Y-m-d H:i:s'),
            );
        }

        return $offers;
    }

    private function vouchersForTier(SubscriptionPlanPricing $tier): mixed
    {
        if (isset($tier->vouchers)) {
            return $tier->vouchers;
        }

        if ($tier->plan && isset($tier->plan->promotion)) {
            return $tier->plan->promotion;
        }

        if ($tier->plan && method_exists($tier->plan, 'promotion')) {
            return $tier->plan->promotion();
        }

        return collect();
    }

    private function buildOffer(
        int    $planId,
        string $planName,
        SubscriptionPlanPricing $tier,
        OfferType $offerType,
        float  $original,
        float  $discounted,
        ?string $currency,
    ): SubscriptionOfferData
    {
        return new SubscriptionOfferData(
            planId:           $planId,
            planName:         $planName,
            pricingId:        (int) $tier->id,
            offerType:        $offerType,
            originalPrice:    $original,
            offerPrice:       $discounted,
            savingAmount:     $this->savingAmount($original, $discounted),
            savingPercentage: $this->savingPercentage($original, $discounted),
            pricingLabel:     $tier->label,
            entitlementType:  $tier->entitlement_type,
            effectiveEntitlementType: $this->resolveEffectiveEntitlementType($tier),
            currency:         $currency,
            createdAt:        $tier->created_at?->format('Y-m-d H:i:s'),
            updatedAt:        $tier->updated_at?->format('Y-m-d H:i:s'),
        );
    }

    private function resolveEffectiveEntitlementType(SubscriptionPlanPricing $tier): ?string
    {
        if (!$tier->plan) {
            return $tier->entitlement_type;
        }

        return ($this->entitlementResolver ?? new SubscriptionEntitlementResolver())
            ->resolve($tier->plan, $tier)
            ->value;
    }

    private function savingAmount(float $original, float $discounted): float
    {
        return round(max(0.0, $original - $discounted), 2);
    }

    private function savingPercentage(float $original, float $discounted): int
    {
        if ($original <= 0) {
            return 0;
        }

        return (int) round((($original - $discounted) / $original) * 100);
    }

    private function offerMatchesPriceRange(
        SubscriptionOfferData $offer,
        SubscriptionOfferFilters $filters,
    ): bool {
        if ($filters->minPrice !== null && $offer->offerPrice < $filters->minPrice) {
            return false;
        }

        if ($filters->maxPrice !== null && $offer->offerPrice > $filters->maxPrice) {
            return false;
        }

        return true;
    }
}
