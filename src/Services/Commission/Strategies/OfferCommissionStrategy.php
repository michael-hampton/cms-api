<?php

namespace App\Services\Commission\Strategies;

use App\Models\Merchant;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Commission\Contracts\CommissionStrategyInterface;

class OfferCommissionStrategy implements CommissionStrategyInterface
{
    public function __construct(private readonly ProductRepository $productRepository)
    {

    }

    public function supports(Product $product): bool
    {
        return $this->productRepository->getActiveOffersForProduct($product->id)->count() > 0;
    }

    public function getRate(Product $product, Merchant $merchant): float
    {
        return config('commission.offer');
    }
}