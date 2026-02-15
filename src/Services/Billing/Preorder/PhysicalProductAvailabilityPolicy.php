<?php

namespace App\Services\Billing\Preorder;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Billing\OrderItemRepository;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;

class PhysicalProductAvailabilityPolicy implements AvailabilityPolicyInterface
{
    public function __construct(
        private readonly Product|ProductVariant $product,
        private readonly ?OrderItemRepository $orderItemRepository = null,
    )
    {
    }

    public function canPurchase(): bool
    {
        // In stock
        if ($this->product->stock_quantity > 0) {
            return true;
        }

        // Pre-order available
        if ($this->product->preorder_enabled && $this->product->preorder_restock_date) {
            return true;
        }

        return false;
    }

    public function isPreRelease(): bool
    {
        return false; // N/A for physical products
    }

    public function getAvailabilityMessage(): string
    {
        if ($this->product->stock_quantity > 0) {
            return 'In Stock';
        }

        if ($this->isPreOrder()) {
            $date = $this->product->preorder_restock_date->format('M j, Y');
            return "Available for Pre-order (Expected: {$date})";
        }

        return 'Out of Stock';
    }

    public function isPreOrder(): bool
    {
        $pendingPreorderQty = $this->getPendingPreorderQuantity();

        return ($this->product->stock_quantity - $pendingPreorderQty) <= 0
            && $this->product->preorder_enabled
            && $this->product->preorder_restock_date !== null;
    }

    private function getPendingPreorderQuantity(): int
    {
        $orderItemRepository = $this->orderItemRepository ?? app(OrderItemRepository::class);
        // You already have this repository/helper method
        return $orderItemRepository->getPendingPreorderQuantity($this->product->id);
    }

    public function getExpectedShipDate(): ?\DateTime
    {
        return $this->isPreOrder() ? $this->product->preorder_restock_date : null;
    }
}