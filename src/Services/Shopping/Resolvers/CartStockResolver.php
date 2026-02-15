<?php

namespace App\Services\Shopping\Resolvers;

use App\DTO\Cart\StockAvailability;
use App\Exceptions\Cart\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductVariant;

class CartStockResolver
{
    public function getAvailableStock(Product $product, ?ProductVariant $variant): ?int
    {
        return $this->getAvailability($product, $variant)->available;
    }

    public function getAvailability(Product $product, ?ProductVariant $variant): StockAvailability
    {
        if ($variant !== null) {
            return StockAvailability::fromStockQuantity($variant->stock_quantity);
        }

        return StockAvailability::fromStockQuantity($product->stock_quantity);
    }

    public function assertCanAdd(Product $product, ?ProductVariant $variant, int $requestedQuantity): void
    {
        $availability = $this->getAvailability($product, $variant);

        if (!$availability->hasSufficientStock($requestedQuantity)) {
            throw new InsufficientStockException(
                requestedQuantity: $requestedQuantity,
                availableQuantity: $availability->available,
                productId: $product->id,
                variantId: $variant?->id,
            );
        }
    }

    public function assertCanUpdate(Product $product, ?ProductVariant $variant, int $newQuantity): void
    {
        $availability = $this->getAvailability($product, $variant);

        if (!$availability->hasSufficientStock($newQuantity)) {
            throw new InsufficientStockException(
                requestedQuantity: $newQuantity,
                availableQuantity: $availability->available,
                productId: $product->id,
                variantId: $variant?->id,
            );
        }
    }
}
