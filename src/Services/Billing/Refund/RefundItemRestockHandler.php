<?php

namespace App\Services\Billing\Refund;

use App\Repositories\Billing\RefundRepository;
use App\Repositories\Product\ProductRepository;

class RefundItemRestockHandler
{
    public function __construct(
        private readonly RefundRepository  $refundRepository,
        private readonly ProductRepository $productRepository
    )
    {
    }

    public function restockItems(int $refundId): void
    {
        $items = $this->refundRepository->getRefundItems($refundId);

        foreach ($items as $item) {
            if (!$item->product_id || $item->refund_quantity <= 0) {
                continue;
            }

            $product = $this->productRepository->find($item->product_id);
            if (!$product) {
                continue;
            }

            $newQuantity = $product->stock_quantity + $item->refund_quantity;
            $this->productRepository->update($item->product_id, [
                'stock_quantity' => $newQuantity
            ]);
        }
    }
}