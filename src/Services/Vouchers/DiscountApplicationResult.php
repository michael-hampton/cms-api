<?php

namespace App\Services\Vouchers;

class DiscountApplicationResult
{
    public function __construct(
        public int    $discountAmountCents,
        public array  $affectedItemIds,
        public bool   $stackable,
        public string $fundingSource, // 'merchant' | 'platform' | 'customer_credit' | 'mixed'
        public string $type, // 'offer' | 'voucher' | 'tiered' | 'reward' | 'store_credit'
        public array  $metadata = []
    )
    {
    }

    public static function none(): self
    {
        return new self(
            discountAmountCents: 0,
            affectedItemIds: [],
            stackable: true,
            fundingSource: 'none',
            type: 'none',
            metadata: []
        );
    }
}