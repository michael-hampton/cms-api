<?php

namespace App\Actions\Stock;

use App\Exceptions\Stock\StockException;
use App\Models\Product;
use App\Services\Stock\StockService;

/**
 * High-level use case: allocate stock for a single physical product purchase.
 *
 * Called by CheckoutService inside its existing order-creation transaction.
 * The transaction boundary is owned by CheckoutService — this action must not
 * open its own.
 */
class PurchaseProductAction
{
    public function __construct(
        private readonly StockService $stockService,
    )
    {
    }

    /**
     * @throws StockException if the product has insufficient stock.
     */
    public function execute(Product $product, int $quantity, int $lowStockThreshold = 5): void
    {
        $this->stockService->allocate($product, $quantity, $lowStockThreshold);
    }
}