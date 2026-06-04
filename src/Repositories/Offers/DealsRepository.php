<?php

namespace App\Repositories\Offers;

use App\Models\FeaturedDeal;
use App\Models\Product;

class DealsRepository
{
    public function getFeaturedDealsByDate(int $siteId, string $date, int $limit): array
    {
        return FeaturedDeal::where('site_id', $siteId)
            ->where('featured_date', $date)
            ->where('is_active', true)
            ->orderBy('position')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function deactivateOldFeaturedDeals(int $siteId, string $beforeDate): int
    {
        return FeaturedDeal::where('site_id', $siteId)
            ->where('featured_date', '<', $beforeDate)
            ->update(['is_active' => 0]);
    }

    public function deactivateFeaturedDealsByDate(int $siteId, string $date): int
    {
        return FeaturedDeal::where('site_id', $siteId)
            ->where('featured_date', $date)
            ->update(['is_active' => 0]);
    }

    public function createFeaturedDeal(array $data): FeaturedDeal
    {
        return FeaturedDeal::create($data);
    }

    public function getProductsForDeals(int $siteId, ?float $minPrice = null, ?float $maxPrice = null): array
    {
        $query = Product::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('sale_price', '>', 0);

        if ($minPrice !== null || $maxPrice !== null) {
            $min = $minPrice ?? 10;
            $max = $maxPrice ?? 300;
            $query->whereBetween('sale_price', [$min, $max]);
        }

        return $query->with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews'])
            ->get()
            ->toArray();
    }

    public function getFilteredProducts(int $siteId, array $filters, array $boostedIds = []): array
    {
        $page = (int)($filters['page'] ?? 1);
        $perPage = (int)($filters['per_page'] ?? 12);

        // Parse sort parameters
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // If sort is in format "field:direction", split it
        if (isset($filters['sort']) && str_contains($filters['sort'], ':')) {
            [$sortBy, $sortOrder] = explode(':', $filters['sort']);
        }

        $query = Product::where('site_id', $siteId)
            ->where('is_active', true)
            ->with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews', 'availableMerchants', 'availableMerchants.merchant']);

        // Search filter
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Category filter
        if (!empty($filters['category_ids'])) {
            $categoryIds = is_array($filters['category_ids'])
                ? $filters['category_ids']
                : explode(',', $filters['category_ids']);
            $categoryIds = array_filter($categoryIds);
            if (!empty($categoryIds)) {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Brand filter
        if (!empty($filters['brand_ids'])) {
            $brandIds = is_array($filters['brand_ids'])
                ? $filters['brand_ids']
                : explode(',', $filters['brand_ids']);
            $brandIds = array_filter($brandIds);
            if (!empty($brandIds)) {
                $query->whereIn('brand_id', $brandIds);
            }
        }

        // Specification filters
        if (!empty($filters['spec_ids'])) {
            $specIds = is_array($filters['spec_ids'])
                ? $filters['spec_ids']
                : explode(',', $filters['spec_ids']);
            $specIds = array_filter($specIds);

            if (!empty($specIds)) {
                $query->whereHas('specifications', function ($q) use ($specIds) {
                    $q->whereIn('value', $specIds);
                });
            }
        }

        // Price range filter
        if (!empty($filters['min_price'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('sale_price', '>=', $filters['min_price'])
                    ->orWhere(function ($q2) use ($filters) {
                        $q2->whereNull('sale_price')
                            ->where('price', '>=', $filters['min_price']);
                    });
            });
        }

        if (!empty($filters['max_price'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('sale_price', '<=', $filters['max_price'])
                    ->where('sale_price', '>', 0)
                    ->orWhere(function ($q2) use ($filters) {
                        $q2->where(function ($q3) use ($filters) {
                            $q3->whereNull('sale_price')
                                ->orWhere('sale_price', '=', 0);
                        })->where('price', '<=', $filters['max_price']);
                    });
            });
        }

        if (!empty($filters['on_sale']) && $filters['on_sale'] !== '0') {
            $query->where('sale_price', '>', 0)
                ->whereRaw('sale_price < price');
        }

        // Voucher filter
        if (!empty($filters['hasVoucher'])) {
            $query->whereHas('activeVouchers');
        }

        // Voucher filter
        if (!empty($filters['hasVoucher'])) {
            $query->whereHas('activeVouchers');
        }

        if (!empty($filters['region_set_ids'])) {
            $regionSetIds = is_array($filters['region_set_ids'])
                ? $filters['region_set_ids']
                : explode(',', $filters['region_set_ids']);
            $regionSetIds = array_filter(array_map('intval', $regionSetIds));

            if (!empty($regionSetIds)) {
                $query->whereHas('regionSets', function ($q) use ($regionSetIds) {
                    $q->whereIn('region_sets.id', $regionSetIds);
                });
            }
        }

        // Apply sorting
        $validSortFields = ['created_at', 'price', 'sale_price', 'title', 'name'];
        $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'created_at';
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        // Handle price sorting (use sale_price if available, otherwise price)
        if ($sortBy === 'price') {
            $query->orderByRaw("COALESCE(NULLIF(sale_price, 0), price) {$sortOrder}");
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        if (!empty($boostedIds)) {
            $ids = implode(',', array_map('intval', $boostedIds));
            $query->orderByRaw("CASE WHEN id IN ({$ids}) THEN 0 ELSE 1 END");
        }

        // Get total count
        $total = $query->count();

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $products = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $products,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];

    }

    public function findProductById(int $productId): ?Product
    {
        return Product::with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews'])
            ->find($productId);
    }
}