<?php

namespace App\Services\Shipping;

use App\Models\Product;
use App\Models\ProductVariant;

class PhysicalProductFulfilment implements FulfilmentTypeInterface
{
    public function __construct(
        private readonly Product|ProductVariant $purchasable
    )
    {
    }

    public function requiresShipping(): bool
    {
        return true;
    }

    public function dispatchDays(): int
    {
        return $this->product->dispatch_days ?? config('shipping.default_dispatch_days', 2);
    }
}