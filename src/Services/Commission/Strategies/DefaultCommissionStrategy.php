<?php

namespace App\Services\Commission\Strategies;

use App\Models\Merchant;
use App\Models\Product;
use App\Services\Commission\Contracts\CommissionStrategyInterface;

class DefaultCommissionStrategy implements CommissionStrategyInterface
{
    public function supports(Product $product): bool
    {
        return true;
    }

    public function getRate(Product $product, Merchant $merchant): float
    {
        return config('commission.default');
    }
}