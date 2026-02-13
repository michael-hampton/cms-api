<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\ProductStockAlert;
use App\Repositories\Repository;

class ProductStockAlertRepository extends Repository
{
    public function getPendingAlerts(int $productId): Collection
    {
        return ProductStockAlert::where('product_id', $productId)
            ->whereNull('notified_at')
            ->get();
    }

    public function markAsNotified(int $alertId): bool
    {
        return ProductStockAlert::where('id', $alertId)
            ->update(['notified_at' => now()]);
    }

    public function findByProductAndUser(int $productId, ?int $userId, ?string $email): ?ProductStockAlert
    {
        $query = ProductStockAlert::where('product_id', $productId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('email', $email);
        }

        return $query->first();
    }

    protected function getModelClass(): string
    {
        return ProductStockAlert::class;
    }
}