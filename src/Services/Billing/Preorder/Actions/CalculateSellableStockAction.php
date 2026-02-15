<?php

namespace App\Services\Billing\Preorder\Actions;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Billing\OrderItemRepository;

class CalculateSellableStockAction
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository
    )
    {
    }

    public function execute(Product|ProductVariant $purchasable): int
    {
        // Calculate reserved quantity for pending preorders
        $reservedQuantity = $this->orderItemRepository
            ->getPendingPreorderQuantity($purchasable->id);

        // Sellable stock = actual stock - reserved for preorders
        return max(0, $purchasable->stock_quantity - $reservedQuantity);
    }
}