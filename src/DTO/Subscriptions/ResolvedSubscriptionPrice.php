<?php

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;

class ResolvedSubscriptionPrice
{
    public function __construct(
        public readonly ?int   $pricingTierId,
        public readonly string $variant, // 'print' or 'digital'
        public readonly float  $basePrice,
        public readonly ?float $salePrice,
        public readonly float  $finalPrice,
        public readonly string $currency,
        public readonly float  $discountAmount,
        public readonly ?int   $voucherId
    )
    {
    }

    /**
     * Create instance for fallback to plan price (no pricing tier)
     */
    public static function fromPlanPrice(
        float  $planPrice,
        string $currency,
        string $variant = SubscriptionType::PRINTED->value,
        float  $discountAmount = 0,
        ?int   $voucherId = null
    ): self
    {
        return new self(
            pricingTierId: null,
            variant: $variant,
            basePrice: $planPrice,
            salePrice: null,
            finalPrice: $planPrice - $discountAmount,
            currency: $currency,
            discountAmount: $discountAmount,
            voucherId: $voucherId
        );
    }

    /**
     * Create instance from pricing tier
     */
    public static function fromPricingTier(
        int    $pricingTierId,
        string $variant,
        float  $basePrice,
        ?float $salePrice,
        string $currency,
        float  $discountAmount = 0,
        ?int   $voucherId = null
    ): self
    {
        $effectivePrice = $salePrice ?? $basePrice;

        return new self(
            pricingTierId: $pricingTierId,
            variant: $variant,
            basePrice: $basePrice,
            salePrice: $salePrice,
            finalPrice: $effectivePrice - $discountAmount,
            currency: $currency,
            discountAmount: $discountAmount,
            voucherId: $voucherId
        );
    }

    /**
     * Was a voucher discount applied?
     */
    public function hasVoucherDiscount(): bool
    {
        return $this->voucherId !== null && $this->discountAmount > 0;
    }

    /**
     * Get the effective base price (sale price if available, otherwise base price)
     */
    public function getEffectiveBasePrice(): float
    {
        return $this->salePrice ?? $this->basePrice;
    }

    /**
     * Convert to cents for payment processing
     */
    public function getFinalPriceCents(): int
    {
        return (int)round($this->finalPrice * 100);
    }

    /**
     * Get original price before any discounts
     */
    public function getOriginalPrice(): float
    {
        return $this->basePrice;
    }

    /**
     * Total savings (sale discount + voucher discount)
     */
    public function getTotalSavings(): float
    {
        $saleDiscount = $this->hasSalePrice() ? ($this->basePrice - $this->salePrice) : 0;
        return $saleDiscount + $this->discountAmount;
    }

    /**
     * Was a sale price applied?
     */
    public function hasSalePrice(): bool
    {
        return $this->salePrice !== null && $this->salePrice < $this->basePrice;
    }

    /**
     * Convert to array for storage
     */
    public function toArray(): array
    {
        return [
            'pricing_tier_id' => $this->pricingTierId,
            'variant' => $this->variant,
            'price' => $this->finalPrice,
            'original_price' => $this->basePrice,
            'sale_price' => $this->salePrice,
            'discount_amount' => $this->discountAmount,
            'voucher_id' => $this->voucherId,
            'currency' => $this->currency
        ];
    }
}