<?php

namespace App\Services\Vouchers;

class ResolvedDiscounts
{
    public function __construct(
        public array $items,
        public int   $baseSubtotalCents,
        public int   $finalSubtotalCents,
        public int   $offerDiscountCents,
        public int   $tieredDiscountCents,
        public int   $voucherDiscountCents,
        public int   $rewardDiscountCents,
        public int   $storeCreditCents,
        public int   $merchantFundedCents,
        public int   $platformFundedCents,
        public int   $customerCreditCents,
        public array $metadata = []
    )
    {
    }

    public function toArray(): array
    {
        return [
            'base_subtotal_cents' => $this->baseSubtotalCents,
            'final_subtotal_cents' => $this->finalSubtotalCents,
            'offer_discount_cents' => $this->offerDiscountCents,
            'tiered_discount_cents' => $this->tieredDiscountCents,
            'voucher_discount_cents' => $this->voucherDiscountCents,
            'reward_discount_cents' => $this->rewardDiscountCents,
            'store_credit_cents' => $this->storeCreditCents,
            'total_discount_cents' => $this->getTotalDiscountCents(),
            'merchant_funded_cents' => $this->merchantFundedCents,
            'platform_funded_cents' => $this->platformFundedCents,
            'customer_credit_cents' => $this->customerCreditCents,
            'metadata' => $this->metadata
        ];
    }

    public function getTotalDiscountCents(): int
    {
        return $this->offerDiscountCents
            + $this->tieredDiscountCents
            + $this->voucherDiscountCents
            + $this->rewardDiscountCents
            + $this->storeCreditCents;
    }

    public function getStripeMetadata(): array
    {
        return [
            'discount_breakdown_json' => json_encode($this->toArray()),
            'first_cycle_discount_total' => $this->getTotalDiscountCents() / 100,
            'recurring_discount_total' => 0, // For future recurring discount implementation
            'offer_discount' => $this->offerDiscountCents / 100,
            'tiered_discount' => $this->tieredDiscountCents / 100,
            'voucher_discount' => $this->voucherDiscountCents / 100,
            'reward_discount' => $this->rewardDiscountCents / 100,
        ];
    }
}