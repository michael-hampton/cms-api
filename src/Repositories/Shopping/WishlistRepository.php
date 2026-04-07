<?php

namespace App\Repositories\Shopping;

use App\Framework\Support\Collection;
use App\Models\Wishlist;
use App\Repositories\Repository;

class WishlistRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Wishlist::class;
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

    public function existsByProduct(int $productId, ?int $userId, string $sessionId): bool
    {
        $query = $this->model->query()
            ->where('product_id', $productId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->exists();
    }

    public function deleteByProduct(int $productId, ?int $userId, string $sessionId): bool
    {
        $query = $this->model->query()
            ->where('product_id', $productId);

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

        return $query->count();
    }

    /**
     * Returns the product_ids in the wishlist for the given identity.
     *
     * Used by the wishlist index endpoint so the frontend can stamp `.active`
     * on product card wishlist buttons without a per-card API call.
     *
     * @return int[]
     */
    public function getProductIdsBySessionOrUser(?int $userId, string $sessionId): array
    {
        $query = $this->model->query()->select('product_id');

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->get()
            ->pluck('product_id')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    public function getBundles(int $bundleId, ?int $userId = null, ?string $sessionId = null): Collection
    {
        return $this->model->where('item_type', 'bundle')
            ->where('item_id', $bundleId)
            ->where($userId ? 'user_id' : 'session_id', $userId ?? $sessionId)
            ->get();
    }

    public function getOffers(int $offerId, ?int $userId = null, ?string $sessionId = null): Collection
    {
        return $this->model->where('item_type', 'offer')
            ->where('item_id', $offerId)
            ->where($userId ? 'user_id' : 'session_id', $userId ?? $sessionId)
            ->get();
    }
}