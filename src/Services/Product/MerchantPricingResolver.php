<?php

namespace App\Services\Product;

use App\Framework\Support\Collection;
use App\Models\ProductVariant;

class MerchantPricingResolver
{
    /**
     * Resolve effective prices for a merchant considering overrides and variant prices
     */
    public function resolve(array $merchantData, ?Collection $variants = null): array
    {
        $price = null;
        $salePrice = null;

        // Get variant if specified
        $variant = null;
        if (!empty($merchantData['variant_id']) && $variants) {
            $variant = $this->findVariant($variants, $merchantData['variant_id']);
        }

        // Determine effective regular price
        if (!empty($merchantData['override_price'])) {
            $price = $merchantData['price'] ?? null;
        } elseif ($variant) {
            $price = $variant->price ?? null;
        } else {
            $price = $merchantData['price'] ?? null;
        }

        // Determine effective sale price
        if (!empty($merchantData['override_sale_price'])) {
            $salePrice = $merchantData['sale_price'] ?? null;
        } elseif ($variant) {
            $salePrice = $variant->sale_price ?? null;
        } else {
            $salePrice = $merchantData['sale_price'] ?? null;
        }

        return [
            'price' => $price,
            'sale_price' => $salePrice
        ];
    }

    private function findVariant(Collection $variants, int $variantId): ?ProductVariant
    {
        return $variants->first(function ($v) use ($variantId) {
            return $v->id == $variantId;
        });
    }
}