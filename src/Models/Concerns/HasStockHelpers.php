<?php

namespace App\Models\Concerns;

/**
 * Lightweight model helpers for stock management.
 *
 * Intended for use on models that carry a `stock_quantity` column
 * (currently: Product, IssueDelivery).
 *
 * Responsibilities deliberately kept minimal — heavy logic lives in StockService.
 */
trait HasStockHelpers
{
    /**
     * Whether the current stock level is at or below the alert threshold.
     */
    public function isLowStock(int $threshold = 5): bool
    {
        return $this->stock_quantity !== null
            && $this->stock_quantity <= $threshold;
    }

    /**
     * Whether any stock remains (including zero = out of stock).
     */
    public function hasStock(int $quantity = 1): bool
    {
        return $this->stock_quantity !== null
            && $this->stock_quantity >= $quantity;
    }

    /**
     * Decrement stock by $quantity in-place and persist.
     * Intended to be called only from within a database transaction managed
     * by StockService — callers must not call this directly.
     *
     * @internal Use StockService::allocate() instead.
     */
    public function decrementStock(int $quantity): void
    {
        $this->decrement('stock_quantity', $quantity);
        $this->refresh();
    }

    /**
     * Increment stock by $quantity in-place and persist.
     * Intended to be called only from within a database transaction managed
     * by StockService — callers must not call this directly.
     *
     * @internal Use StockService::release() instead.
     */
    public function incrementStock(int $quantity): void
    {
        $this->increment('stock_quantity', $quantity);
        $this->refresh();
    }
}