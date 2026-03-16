<?php

namespace App\Actions\Stock;

use App\Exceptions\Stock\StockException;
use App\Models\Product;

/**
 * High-level use case: allocate stock for every product in a bundle.
 *
 * Allocation is all-or-nothing — if any product has insufficient stock the
 * exception propagates and the caller's transaction rolls back, restoring all
 * previously decremented items automatically.
 *
 * MUST be called inside the caller's open transaction.
 *
 * A "bundle" is any object (or array) that exposes a collection of Products.
 * The action accepts an explicit product list so it is decoupled from whatever
 * Bundle model shape the application uses.
 *
 * @example
 *   $products = $bundle->products;  // Collection<Product>
 *   $this->allocateBundleAction->execute($products, $quantity);
 */
class AllocateBundleAction
{
    public function __construct(
        private readonly PurchaseProductAction $purchaseProductAction,
    )
    {
    }

    /**
     * @param iterable<Product> $products Products that make up the bundle.
     * @param int $quantity How many bundles are being purchased.
     * @param int $lowStockThreshold Per-product low-stock alert threshold.
     *
     * @throws StockException if any product in the bundle has insufficient stock.
     */
    public function execute(iterable $products, int $quantity = 1, int $lowStockThreshold = 5): void
    {
        foreach ($products as $product) {
            // PurchaseProductAction::execute() throws StockException on failure.
            // Because we are inside the caller's transaction, the DB rolls back
            // all prior decrements automatically — no manual compensation needed.
            $this->purchaseProductAction->execute($product, $quantity, $lowStockThreshold);
        }
    }
}