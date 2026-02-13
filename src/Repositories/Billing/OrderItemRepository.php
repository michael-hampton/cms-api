<?php

namespace App\Repositories\Billing;

use App\Enums\Orders\OrderLineStatus;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\OrderItem;
use App\Repositories\Repository;

class OrderItemRepository extends Repository
{
    protected function getModelClass(): string
    {
        return OrderItem::class;
    }

    public function getByOrderId(int $orderId): Collection
    {
        return OrderItem::where('order_id', $orderId)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function getByProductSku(string $sku): Collection
    {
        return OrderItem::where('product_sku', $sku)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function deleteByOrderId(int $orderId): bool
    {
        $items = $this->getByOrderId($orderId);

        foreach ($items as $item) {
            $item->delete();
        }

        return true;
    }

    public function getPendingPreorderQuantity(int $productId): int
    {
        return OrderItem::where('product_id', $productId)
            ->where('status', OrderLineStatus::PENDING_PREORDER->value)
            ->sum(Database::raw('quantity - quantity_allocated'));
    }

    public function getPendingPreorders(int $productId): Collection
    {
        return OrderItem::where('product_id', $productId)
            ->where('status', OrderLineStatus::PENDING_PREORDER->value)
            ->whereColumn('quantity_allocated', '<', 'quantity')
            ->orderBy('created_at', 'asc')
            //->lockForUpdate()
            ->get();
    }
}