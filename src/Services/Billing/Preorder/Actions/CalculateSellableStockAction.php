<?php

namespace App\Services\Billing\Preorder\Actions;

use App\Models\Product;
use App\Repositories\Billing\OrderItemRepository;

class CalculateSellableStockAction
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository
    )
    {
    }

    public function execute(Product $product): int
    {
        // Calculate reserved quantity for pending preorders
        $reservedQuantity = $this->orderItemRepository
            ->getPendingPreorderQuantity($product->id);

        // Sellable stock = actual stock - reserved for preorders
        return max(0, $product->stock_quantity - $reservedQuantity);
    }
}