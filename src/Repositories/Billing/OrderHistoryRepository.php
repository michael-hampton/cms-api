<?php

namespace App\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\OrderHistory;
use App\Repositories\Repository;

class OrderHistoryRepository extends Repository
{
    protected function getModelClass(): string
    {
        return OrderHistory::class;
    }

    public function getHistoryForOrder(int $orderId): Collection
    {
        return OrderHistory::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getRecentHistory(int $limit = 50): Collection
    {
        return OrderHistory::with(['order', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getHistoryByAction(string $action, ?int $limit = null): Collection
    {
        $query = OrderHistory::where('action', $action)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }
}