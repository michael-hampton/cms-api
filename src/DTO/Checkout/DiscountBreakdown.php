<?php

namespace App\DTO\Checkout;

class DiscountBreakdown
{
    public float $offerTotal;
    public float $voucherTotal;
    public array $perItem;
    public ?string $voucherCode;
    public ?int $voucherId;
    public ?int $campaignId;
    public ?int $merchantId;
    public bool $isStackable;

    public function __construct(
        float   $offerTotal = 0,
        float   $voucherTotal = 0,
        array   $perItem = [],
        ?string $voucherCode = null,
        ?int    $voucherId = null,
        ?int    $campaignId = null,
        ?int    $merchantId = null,
        bool    $isStackable = false
    )
    {
        $this->offerTotal = $offerTotal;
        $this->voucherTotal = $voucherTotal;
        $this->perItem = $perItem;
        $this->voucherCode = $voucherCode;
        $this->voucherId = $voucherId;
        $this->campaignId = $campaignId;
        $this->merchantId = $merchantId;
        $this->isStackable = $isStackable;
    }

    public function toArray(): array
    {
        return [
            'offer_total' => $this->offerTotal,
            'voucher_total' => $this->voucherTotal,
            'total_discount' => $this->getTotalDiscount(),
            'per_item' => $this->perItem,
            'voucher_code' => $this->voucherCode,
            'voucher_id' => $this->voucherId,
            'campaign_id' => $this->campaignId,
            'merchant_id' => $this->merchantId,
            'is_stackable' => $this->isStackable
        ];
    }

    public function getTotalDiscount(): float
    {
        return $this->offerTotal + $this->voucherTotal;
    }
}