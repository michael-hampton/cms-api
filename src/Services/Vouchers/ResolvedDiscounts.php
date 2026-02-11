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
}