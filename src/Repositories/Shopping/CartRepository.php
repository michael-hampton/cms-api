<?php

namespace App\Repositories\Shopping;

use App\Framework\Support\Collection;
use App\Models\CartItem;
use App\Models\Model;
use App\Repositories\Repository;

class CartRepository extends Repository
{
    public function updateQuantity(mixed $cartItemId, int $quantity)
    {
        $item = $this->model->query()->where('id', $cartItemId)->first();

        if (!$item) {
            return false;
        }

        return $item->update(['quantity' => $quantity]);
    }

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

        return $query->with(['product', 'merchant'])->get();
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

    /**
     * Find cart item by product and variant combination
     */
    public function findItemByProductAndVariant(
        int    $productId,
        ?int   $variantId,
        ?int   $userId,
        string $sessionId
    ): ?Model
    {
        $query = $this->model->query()
            ->where('product_id', $productId);

        if ($variantId) {
            $query->where('variant_id', $variantId);
        } else {
            $query->whereNull('variant_id');
        }

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

    public function findById(int $id, ?int $userId, ?string $sessionId = null): ?Model
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