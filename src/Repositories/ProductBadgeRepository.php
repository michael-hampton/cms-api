<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\ProductBadge;

class ProductBadgeRepository extends Repository
{
    public function getActiveProductBadges(int $productId): Collection
    {
        $now = now();

        return $this->where('product_id', $productId)
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $now);
            })
            ->orderBy('sort_order')
            ->get();
    }

    protected function getModelClass(): string
    {
        return ProductBadge::class;
    }
}