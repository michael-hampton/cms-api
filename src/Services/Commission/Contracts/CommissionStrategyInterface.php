<?php

namespace App\Services\Commission\Contracts;

use App\Models\Merchant;
use App\Models\Product;

interface CommissionStrategyInterface
{
    public function supports(Product $product): bool;

    public function getRate(Product $product, Merchant $merchant): float;
}