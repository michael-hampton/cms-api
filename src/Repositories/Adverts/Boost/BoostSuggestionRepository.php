<?php

namespace App\Repositories\Adverts\Boost;

use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\ProductImpression;

class BoostSuggestionRepository
{
    /**
     * Products belonging to this merchant that are active and in stock.
     */
    public function getActiveMerchantProducts(int $merchantId): Collection
    {
        return Product::whereHas('merchants', fn($q) => $q->where('merchant_id', $merchantId))
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with(['approvedReviews', 'activeOffers'])
            ->get();
    }

    /**
     * Impression count per product over the last N days.
     * Returns assoc array: [product_id => count]
     */
    public function getImpressionCountsForProducts(array $productIds, int $days = 30): array
    {
        if (empty($productIds)) {
            return [];
        }

        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s');

        $rows = ProductImpression::whereIn('product_id', $productIds)
            ->where('viewed_at', '>=', $cutoff)
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->get();

        return $rows->pluck('total', 'product_id')->toArray();
    }

    /**
     * Units sold per product over the last N days via completed orders.
     * Returns assoc array: [product_id => units_sold]
     */
    public function getUnitsSoldForProducts(array $productIds, int $days = 30): array
    {
        if (empty($productIds)) {
            return [];
        }

        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s');

        $rows = \App\Models\OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('order_items.product_id', $productIds)
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', $cutoff)
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units_sold')
            ->groupBy('order_items.product_id')
            ->get();

        return $rows->pluck('units_sold', 'product_id')->toArray();
    }

    /**
     * Active offer for each product, if any.
     * Returns assoc array: [product_id => ProductOffer]
     */
    public function getActiveOffersForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $now = now_datetime()->format('Y-m-d H:i:s');

        $offers = \App\Models\ProductOffer::whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->get();

        return $offers->keyBy('product_id')->toArray();
    }

    /**
     * Average rating per product.
     * Returns assoc array: [product_id => avg_rating]
     */
    public function getAverageRatingsForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = \App\Models\Review::whereIn('product_id', $productIds)
            ->where('is_approved', true)
            ->selectRaw('product_id, AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->groupBy('product_id')
            ->get();

        return $rows->pluck('avg_rating', 'product_id')->toArray();
    }

    /**
     * Active boosts for this merchant, optionally filtered to specific products.
     */
    public function getActiveBoostsForMerchant(int $merchantId, array $productIds = []): Collection
    {
        $query = \App\Models\Boost::where('merchant_id', $merchantId)
            ->where('status', 'active');

        if (!empty($productIds)) {
            $query->whereIn('boostable_id', $productIds)
                ->where('boostable_type', 'product');
        }

        return $query->get();
    }
}