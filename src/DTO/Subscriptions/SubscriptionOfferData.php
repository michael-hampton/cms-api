<?php

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\OfferType;

/**
 * Immutable value object representing a single CRM offer derived from a
 * pricing tier.  Not a persisted entity — identity is (pricing_id, offer_type).
 */
final class SubscriptionOfferData
{
    public function __construct(
        public readonly int       $planId,
        public readonly string    $planName,
        public readonly int       $pricingId,
        public readonly OfferType $offerType,
        public readonly float     $originalPrice,
        public readonly float     $offerPrice,
        public readonly float     $savingAmount,
        public readonly int       $savingPercentage,
        // Optional enrichment fields
        public readonly ?string   $pricingLabel       = null,
        public readonly ?int      $introCycles        = null,
        public readonly ?string   $voucherCode        = null,
        public readonly ?string   $currency           = null,
    ) {}

    /**
     * Unique virtual ID used by the CRM to identify this specific offer.
     */
    public function virtualId(): string
    {
        return $this->pricingId . ':' . $this->offerType->value;
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->virtualId(),
            'plan_id'           => $this->planId,
            'plan_name'         => $this->planName,
            'pricing_id'        => $this->pricingId,
            'offer_type'        => $this->offerType->value,
            'original_price'    => $this->originalPrice,
            'offer_price'       => $this->offerPrice,
            'saving_amount'     => $this->savingAmount,
            'saving_percentage' => $this->savingPercentage,
            'pricing_label'     => $this->pricingLabel,
            'intro_cycles'      => $this->introCycles,
            'voucher_code'      => $this->voucherCode,
            'currency'          => $this->currency,
        ];
    }
}