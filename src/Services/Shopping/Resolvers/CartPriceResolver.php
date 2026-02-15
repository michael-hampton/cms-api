<?php

namespace App\Services\Shopping\Resolvers;

use App\Models\Product;
use App\Models\ProductVariant;

class CartPriceResolver
{
    public function getDiscountPercentage(Product $product, ?ProductVariant $variant): float
    {
        $base = $this->getBasePrice($product, $variant);

        if ($base <= 0) {
            return 0.0;
        }

        $discount = $this->getDiscount($product, $variant);

        return $discount > 0
            ? ($discount / $base) * 100
            : 0.0;
    }

    public function getBasePrice(Product $product, ?ProductVariant $variant): float
    {
        if ($variant !== null && $variant->price !== null) {
            return $variant->price;
        }

        return $product->price;
    }

    public function getDiscount(Product $product, ?ProductVariant $variant): float
    {
        $base = $this->getBasePrice($product, $variant);
        $current = $this->resolve($product, $variant);

        return max(0.0, $base - $current);
    }

    public function resolve(Product $product, ?ProductVariant $variant): float
    {
        if ($variant !== null && $this->hasSalePrice($variant)) {
            return $variant->sale_price;
        }

        if ($variant !== null && $variant->price !== null) {
            return $variant->price;
        }

        if ($this->hasSalePrice($product)) {
            return $product->sale_price;
        }

        return $product->price;
    }

    private function hasSalePrice(Product|ProductVariant $model): bool
    {
        $salePrice = $model->sale_price;

        if ($salePrice === null) {
            return false;
        }

        if ($salePrice <= 0) {
            return false;
        }

        if ($salePrice >= $model->price) {
            return false;
        }

        return true;
    }
}
