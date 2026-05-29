<?php

namespace App\Services\MemberInsights\Segmentation;

final class RenewalOfferFilter
{
    public function __construct(
        public readonly ?string $edition     = null,
        public readonly ?string $region      = null,
        public readonly ?string $paymentType = null,
        public readonly ?string $activeDate  = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            edition:     $data['edition']      ?? null,
            region:      $data['region']        ?? null,
            paymentType: $data['payment_type']  ?? null,
            activeDate:  $data['active_date']   ?? null,
        );
    }
}