<?php

namespace App\DTO\Subscriptions;

final class SubscriptionPricing
{
    public function __construct(
        public readonly int    $subtotalCents,
        public readonly int    $discountCents,
        public readonly int    $shippingCents,
        public readonly int    $taxCents,
        public readonly int    $totalCents,
        public readonly string $deliveryType,
        public readonly ?int   $voucherId,
        public readonly ?array $shippingAddressSnapshot
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