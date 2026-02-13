<?php

namespace App\Services\Billing\Preorder\Actions;

use App\Enums\Orders\OrderLineStatus;
use App\Framework\Database\Database;
use App\Models\Product;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Product\ProductRepository;

class AllocatePreorderStockAction
{
    public function __construct(
        private readonly ProductRepository   $productRepository,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly Database            $database
    )
    {
    }

    public function execute(Product $product): int
    {
        return $this->database->transaction(function () use ($product) {
            // Lock product row
            $product = $this->productRepository->lockForUpdate($product->id);

            if ($product->stock_quantity <= 0) {
                return 0; // No stock to allocate
            }

            // Fetch pending preorder lines (oldest first)
            $pendingLines = $this->orderItemRepository
                ->getPendingPreorders($product->id);

            $allocatedCount = 0;
            $remainingStock = $product->stock_quantity;

            foreach ($pendingLines as $line) {
                if ($remainingStock <= 0) {
                    break;
                }

                $remainingForLine = $line->quantity - $line->quantity_allocated;
                $allocateAmount = min($remainingStock, $remainingForLine);

                // Update order line
                $newAllocated = $line->quantity_allocated + $allocateAmount;
                $newStatus = ($newAllocated === $line->quantity)
                    ? OrderLineStatus::READY_TO_SHIP->value
                    : OrderLineStatus::PENDING_PREORDER->value;

                $this->orderItemRepository->update($line->id, [
                    'quantity_allocated' => $newAllocated,
                    'status' => $newStatus,
                ]);

                $remainingStock -= $allocateAmount;
                $allocatedCount += $allocateAmount;
            }

            // Update product stock
            $this->productRepository->update($product->id, [
                'stock_quantity' => $remainingStock,
            ]);

            return $allocatedCount;
        });
    }
}