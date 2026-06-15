<?php

namespace App\DTO\Subscriptions;

class SubscriptionPricing
{
    public function __construct(
        public int    $subtotalCents,
        public int    $discountCents,
        public int    $shippingCents,
        public int    $taxCents,
        public int    $totalCents,
        public string $deliveryType,
        public ?int   $voucherId = null,
        public ?array $shippingAddressSnapshot = [],
        public ?float $originalAmount = 0,
        public ?int   $pricingTierId = null,
        public ?string $currency = null,
    )
    {
    }

    public function getSubtotal(): float
    {
        return $this->subtotalCents / 100;
    }

    public function getDiscount(): float
    {
        return $this->discountCents / 100;
    }

    public function getShipping(): float
    {
        return $this->shippingCents / 100;
    }

    public function getTax(): float
    {
        return $this->taxCents / 100;
    }

    public function getTotal(): float
    {
        return $this->totalCents / 100;
    }
}
