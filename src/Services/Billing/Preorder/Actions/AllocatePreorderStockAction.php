<?php

namespace App\Services\Billing\Preorder\Actions;

use App\Enums\Orders\OrderLineStatus;
use App\Framework\Database\Database;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductVariantRepository;

class AllocatePreorderStockAction
{
    public function __construct(
        private readonly ProductRepository        $productRepository,
        private readonly ProductVariantRepository $variantRepository,
        private readonly OrderItemRepository      $orderItemRepository,
        private readonly Database                 $database
    )
    {
    }

    public function execute(Product|ProductVariant $purchasable): int
    {
        return $this->database->transaction(function () use ($purchasable) {

            $isVariant = $purchasable instanceof ProductVariant;

            $lock = fn() => $isVariant
                ? $this->variantRepository->lockForUpdate($purchasable->id)
                : $this->productRepository->lockForUpdate($purchasable->id);

            $updateStock = fn(int $qty) => $isVariant
                ? $this->variantRepository->update($purchasable->id, ['stock_quantity' => $qty])
                : $this->productRepository->update($purchasable->id, ['stock_quantity' => $qty]);

            $purchasable = $lock();

            if ($purchasable->stock_quantity <= 0) {
                return 0;
            }

            $pendingLines = $this->orderItemRepository
                ->getPendingPreorders($purchasable->id);

            $remainingStock = $purchasable->stock_quantity;
            $allocated = 0;

            foreach ($pendingLines as $line) {
                if ($remainingStock <= 0) {
                    break;
                }

                $remainingForLine = $line->quantity - $line->quantity_allocated;
                $allocate = min($remainingStock, $remainingForLine);

                $this->orderItemRepository->update($line->id, [
                    'quantity_allocated' => $line->quantity_allocated + $allocate,
                    'status' => $allocate === $remainingForLine
                        ? OrderLineStatus::READY_TO_SHIP->value
                        : OrderLineStatus::PENDING_PREORDER->value,
                ]);

                $remainingStock -= $allocate;
                $allocated += $allocate;
            }

            $updateStock($remainingStock);

            return $allocated;
        });
    }
}