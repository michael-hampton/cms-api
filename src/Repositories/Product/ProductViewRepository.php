<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Product;
use App\Models\ProductView;
use App\Repositories\Repository;

class ProductViewRepository extends Repository
{
    protected function getModelClass(): string
    {
        return ProductView::class;
    }

    /**
     * Track a product view
     */
    public function trackView(Product $product, ?int $userId, string $sessionId, ?string $ipAddress): Model
    {
        return $this->create([
            'product_id' => $product->id,
            'site_id' => $product->site_id,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Get viewed product IDs for a member
     *
     * @param int $memberId
     * @param int $limit Maximum number of product IDs to return
     * @param int $daysBack Number of days to look back (default 30)
     * @return array Array of product IDs ordered by most recently viewed
     */
    public function getViewedProductIdsByMember(int $memberId, int $limit = 20, int $daysBack = 30): array
    {
        $cutoffDate = now_datetime()->subDays($daysBack);

        return ProductView::where('user_id', $memberId)
            ->where('viewed_at', '>=', $cutoffDate->format('Y-m-d H:i:s'))
            ->orderBy('viewed_at', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('product_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get viewed products with details for a member
     *
     * @param int $memberId
     * @param int $limit
     * @param int $daysBack
     * @return Collection Collection of Product models
     */
    public function getViewedProductsByMember(int $memberId, int $limit = 20, int $daysBack = 30): Collection
    {
        $cutoffDate = now_datetime()->subDays($daysBack);

        $productIds = ProductView::where('user_id', $memberId)
            ->where('viewed_at', '>=', $cutoffDate->format('Y-m-d H:i:s'))
            ->orderBy('viewed_at', 'desc')
            ->get()
            ->pluck('product_id')
            ->unique()
            ->take($limit)
            ->toArray();

        if (empty($productIds)) {
            return collect([]);
        }

        // Preserve the order from the viewed_at sorting
        $products = Product::whereIn('id', $productIds)->get();

        // Re-order products based on the original order
        $orderedProducts = collect([]);
        foreach ($productIds as $productId) {
            $product = $products->firstWhere('id', $productId);
            if ($product) {
                $orderedProducts->push($product);
            }
        }

        return $orderedProducts;
    }

    /**
     * Get view count for a specific product
     *
     * @param int $productId
     * @param int|null $daysBack Number of days to look back (null for all time)
     * @return int
     */
    public function getProductViewCount(int $productId, ?int $daysBack = null): int
    {
        $query = ProductView::where('product_id', $productId);

        if ($daysBack !== null) {
            $cutoffDate = now_datetime()->subDays($daysBack)->format('Y-m-d H:i:s');
            $query->where('viewed_at', '>=', $cutoffDate);
        }

        return $query->count();
    }

    /**
     * Get unique view count for a specific product (by user)
     *
     * @param int $productId
     * @param int|null $daysBack
     * @return int
     */
    public function getProductUniqueViewCount(int $productId, ?int $daysBack = null): int
    {
        $query = ProductView::where('product_id', $productId)
            ->whereNotNull('user_id');

        if ($daysBack !== null) {
            $cutoffDate = now()->subDays($daysBack);
            $query->where('viewed_at', '>=', $cutoffDate);
        }

        return $query->distinct('user_id')->count('user_id');
    }

    /**
     * Get most viewed products for a site
     *
     * @param int $siteId
     * @param int $limit
     * @param int|null $daysBack
     * @return Collection Collection of arrays with product_id and view_count
     */
    public function getMostViewedProducts(int $siteId, int $limit = 10, ?int $daysBack = null): Collection
    {
        $query = ProductView::where('site_id', $siteId)
            ->select('product_id')
            ->selectRaw('COUNT(*) as view_count')
            ->groupBy('product_id')
            ->orderByDesc('view_count')
            ->limit($limit);

        if ($daysBack !== null) {
            $cutoffDate = now_datetime()->subDays($daysBack)->format('Y-m-d H:i:s');
            $query->where('viewed_at', '>=', $cutoffDate);
        }

        return collect($query->get()->toArray());
    }

    /**
     * Check if a user has viewed a product recently
     *
     * @param int $productId
     * @param int $userId
     * @param int $withinMinutes Check within this many minutes
     * @return bool
     */
    public function hasRecentView(int $productId, int $userId, int $withinMinutes = 60): bool
    {
        $cutoffTime = now_datetime()->subMinutes($withinMinutes)->format('Y-m-d H:i:s');

        return ProductView::where('product_id', $productId)
            ->where('user_id', $userId)
            ->where('viewed_at', '>=', $cutoffTime)
            ->exists();
    }

    /**
     * Get total views for a member across all products
     *
     * @param int $memberId
     * @param int|null $daysBack
     * @return int
     */
    public function getMemberTotalViews(int $memberId, ?int $daysBack = null): int
    {
        $query = ProductView::where('user_id', $memberId);

        if ($daysBack !== null) {
            $cutoffDate = now_datetime()->subDays($daysBack)->format('Y-m-d H:i:s');
            $query->where('viewed_at', '>=', $cutoffDate);
        }

        return $query->count();
    }

    /**
     * Get viewing statistics for a product
     *
     * @param int $productId
     * @param int $daysBack
     * @return array
     */
    public function getProductViewStats(int $productId, int $daysBack = 30): array
    {
        $cutoffDate = now_datetime()->subDays($daysBack)->format('Y-m-d H:i:s');

        $views = ProductView::where('product_id', $productId)
            ->where('viewed_at', '>=', $cutoffDate)
            ->get();

        return [
            'total_views' => $views->count(),
            'unique_users' => $views->whereNotNull('user_id')->unique('user_id')->count(),
            'unique_sessions' => $views->unique('session_id')->count(),
            'anonymous_views' => $views->whereNull('user_id')->count(),
        ];
    }

    /**
     * Delete old product views (cleanup)
     *
     * @param int $daysToKeep Keep views from the last X days
     * @return int Number of deleted records
     */
    public function deleteOldViews(int $daysToKeep = 365): int
    {
        $cutoffDate = now_datetime()->subDays($daysToKeep)->format('Y-m-d H:i:s');

        return ProductView::where('viewed_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get products viewed together (frequently viewed with)
     *
     * @param int $productId
     * @param int $limit
     * @param int $daysBack
     * @return array Array of product IDs
     */
    public function getFrequentlyViewedWith(int $productId, int $limit = 5, int $daysBack = 90): array
    {
        $cutoffDate = now_datetime()->subDays($daysBack)->format('Y-m-d H:i:s');

        // Get users who viewed this product
        $userIds = ProductView::where('product_id', $productId)
            ->where('viewed_at', '>=', $cutoffDate)
            ->whereNotNull('user_id')
            ->get()
            ->pluck('user_id')
            ->unique()
            ->toArray();

        if (empty($userIds)) {
            return [];
        }

        // Get other products these users viewed
        return ProductView::whereIn('user_id', $userIds)
            ->where('product_id', '!=', $productId)
            ->where('viewed_at', '>=', $cutoffDate)
            ->select('product_id') // only the columns you want
            ->selectRaw('COUNT(*) as view_count')
            ->groupBy('product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->get()
            ->pluck('product_id') // already returns a collection of IDs
            ->toArray();
    }
}