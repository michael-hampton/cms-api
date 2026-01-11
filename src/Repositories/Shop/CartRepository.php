<?php

namespace App\Repositories\Shop;

use App\Framework\Support\Collection;
use App\Models\CartItem;
use App\Models\Model;
use App\Repositories\Repository;

class CartRepository extends Repository
{
    protected function getModelClass(): string
    {
        return CartItem::class;
    }

    public function findBySessionOrUser(?int $userId, string $sessionId): Collection
    {
        $query = $this->model->query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->with(['product'])->get();
    }

    public function findItemByProduct(int $productId, ?int $userId, string $sessionId): ?Model
    {
        $query = $this->model->query()
            ->where('product_id', $productId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->first();
    }

    public function deleteBySessionOrUser(?int $userId, string $sessionId): bool
    {
        $query = $this->model->query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->delete() > 0;
    }

    public function getCountBySessionOrUser(?int $userId, string $sessionId): int
    {
        $query = $this->model->query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $sum = $query->sum('quantity');
        return $sum !== null ? (int)$sum : 0; // Handle null case
    }

    public function findById(int $id, ?int $userId, string $sessionId): ?Model
    {
        $query = $this->model->query()
            ->where('id', $id);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->first();
    }

    public function findBySubscriptionPlan(?int $planId, ?int $userId, string $sessionId)
    {
        $query = CartItem::where('subscription_plan_id', $planId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->first();
    }
}