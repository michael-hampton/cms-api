<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\OrderItem;

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
}