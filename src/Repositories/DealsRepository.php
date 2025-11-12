<?php

namespace App\Repositories;

use App\Models\FeaturedDeal;
use App\Models\Product;
use App\Framework\Support\SiteContext;

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

    public function getFilteredProducts(int $siteId, array $filters): array
    {
        $query = Product::where('site_id', $siteId)
            ->where('is_active', true)
            ->with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews']);

        // Category filter
        if (!empty($filters['category'])) {
            $query->whereIn('category_id', $filters['category']);
        }

        // Brand filter
        if (!empty($filters['brand'])) {
            $query->whereIn('brand_id', $filters['brand']);
        }

        // Price range filter
        if (isset($filters['minPrice'])) {
            $query->where('sale_price', '>=', $filters['minPrice']);
        }
        if (isset($filters['maxPrice'])) {
            $query->where('sale_price', '<=', $filters['maxPrice']);
        }

        // Voucher filter
        if (!empty($filters['hasVoucher'])) {
            $query->whereHas('activeVouchers');
        }

        return $query->get()->toArray();
    }

    public function findProductById(int $productId): ?Product
    {
        return Product::with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews'])
            ->find($productId);
    }
}