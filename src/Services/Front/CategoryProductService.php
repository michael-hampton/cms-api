<?php

namespace App\Services\Front;

use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\ProductOffer;

class CategoryProductService
{
    /**
     * Get active offers for products in a category
     *
     * @param int $categoryId
     * @param int $limit
     * @return array
     */
    public function getCategoryOffers(int $categoryId, int $limit = 6): array
    {
        $offers = ProductOffer::forCategory($categoryId)
            ->where('is_active', true)
            ->where('status', 'published')
            ->with([
                'product',
                'product.approvedReviews',
                'product.images',
                'product.brand',
                'merchant'
            ])
            ->orderBy('end_date', 'asc')
            ->limit($limit)
            ->get();

        return $this->formatOffersForDisplay($offers);
    }

    /**
     * Format offers for display
     *
     * @param Collection $offers
     * @return array
     */
    private function formatOffersForDisplay($offers): array
    {
        return $offers->map(function ($offer) {
            $product = $offer->product;

            return [
                'id' => $offer->id,
                'sale_price' => $offer->sale_price,
                'start_date' => $offer->start_date->format('Y-m-d H:i:s'),
                'end_date' => $offer->end_date->format('Y-m-d H:i:s'),
                'discount_percentage' => $offer->discount_percentage,
                'is_active' => $offer->isCurrentlyActive(),
                'merchant' => $offer->merchant ? [
                    'id' => $offer->merchant->id,
                    'name' => $offer->merchant->name,
                    'slug' => $offer->merchant->slug ?? null,
                ] : null,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'image' => $product->image ?? $product->main_image_url,
                    'price' => $product->price,
                    'average_rating' => $product->average_rating,
                    'review_count' => $product->review_count,
                    'stock_quantity' => $product->stock_quantity ?? 0,
                    'brand' => $product->brand ? [
                        'id' => $product->brand->id,
                        'name' => $product->brand->name,
                    ] : null,
                ],
            ];
        })->toArray();
    }

    /**
     * Get featured products for a category (highest rated, most reviewed)
     *
     * @param int $categoryId
     * @param int $limit
     * @return array
     */
    public function getFeaturedProducts(int $categoryId, int $limit = 4): array
    {
        $products = Product::where('category_id', $categoryId)
            ->where('is_active', true)
            ->with([
                'approvedReviews',
                'availableMerchants',
                'availableMerchants.merchant',
                'images',
                'brand'
            ])
            ->get()
            ->sortByDesc(function ($product) {
                // Sort by average rating, then by review count
                return ($product->average_rating * 1000) + $product->review_count;
            })
            ->take($limit);

        return $this->formatProductsForDisplay($products);
    }

    /**
     * Format products for display
     *
     * @param Collection $products
     * @return array
     */
    private function formatProductsForDisplay($products): array
    {
        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image' => $product->image ?? $product->main_image_url,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'discount_percentage' => $product->discount_percentage,
                'average_rating' => $product->average_rating,
                'review_count' => $product->review_count,
                'stock_quantity' => $product->stock_quantity ?? 0,
                'is_in_stock' => ($product->stock_quantity ?? 0) > 0,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => $product->brand->slug ?? null,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'availableMerchants' => $product->availableMerchants->map(function ($pm) {
                    return [
                        'id' => $pm->id,
                        'name' => $pm->merchant->name ?? 'Unknown',
                        'price' => $pm->price,
                        'sale_price' => $pm->sale_price,
                        'discount_percentage' => $pm->discount_percentage,
                        'url' => $pm->url,
                        'is_available' => $pm->is_available,
                    ];
                })->toArray(),
                'images' => $product->images->map(function ($img) {
                    return [
                        'url' => $img->url,
                        'alt' => $img->alt,
                        'is_primary' => $img->is_primary,
                    ];
                })->toArray(),
            ];
        })->toArray();
    }

    /**
     * Get products on sale in a category
     *
     * @param int $categoryId
     * @param int $limit
     * @return array
     */
    public function getSaleProducts(int $categoryId, int $limit = 8): array
    {
        return $this->getCategoryProducts($categoryId, $limit, ['on_sale_only' => true]);
    }

    /**
     * Get products for a category with related data
     *
     * @param int $categoryId
     * @param int $limit
     * @param array $options Additional options (orderBy, filters, etc.)
     * @return array
     */
    public function getCategoryProducts(int $categoryId, int $limit = 8, array $options = []): array
    {
        $orderBy = $options['order_by'] ?? 'created_at';
        $orderDirection = $options['order_direction'] ?? 'desc';
        $onSaleOnly = $options['on_sale_only'] ?? false;

        $query = Product::where('category_id', $categoryId)
            ->where('is_active', true)
            ->with([
                'approvedReviews',
                'availableMerchants',
                'availableMerchants.merchant',
                'images',
                'brand',
                'category'
            ]);

        // Filter for sale items only
        if ($onSaleOnly) {
            $query->whereColumn('sale_price', '<', 'price')
                ->where('sale_price', '>', 0);
        }

        $products = $query->orderBy($orderBy, $orderDirection)
            ->limit($limit)
            ->get();

        return $this->formatProductsForDisplay($products);
    }

    /**
     * Get newly added products in a category
     *
     * @param int $categoryId
     * @param int $limit
     * @param int $daysBack Number of days to look back
     * @return array
     */
    public function getNewProducts(int $categoryId, int $limit = 8, int $daysBack = 30): array
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysBack} days"));

        $products = Product::where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('created_at', '>=', $cutoffDate)
            ->with([
                'approvedReviews',
                'availableMerchants',
                'availableMerchants.merchant',
                'images',
                'brand'
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->formatProductsForDisplay($products);
    }

    /**
     * Get category statistics
     *
     * @param int $categoryId
     * @return array
     */
    public function getCategoryStats(int $categoryId): array
    {
        $totalProducts = Product::where('category_id', $categoryId)
            ->where('is_active', true)
            ->count();

        $onSaleProducts = Product::where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('sale_price', '>', 0)
            ->get();

        $onSaleProducts = $onSaleProducts->filter(function ($product) {
            return $product->sale_price > 0 && $product->sale_price < $product->price;
        });

        $activeOffers = ProductOffer::forCategory($categoryId)
            ->where('is_active', true)
            ->count();

        $avgPrice = Product::where('category_id', $categoryId)
            ->where('is_active', true)
            ->avg('price');

        return [
            'total_products' => $totalProducts,
            'on_sale_products' => $onSaleProducts,
            'active_offers' => $activeOffers,
            'average_price' => round($avgPrice, 2),
        ];
    }

    public function getCategoryReviews(int $categoryId, int $limit = 5): array
    {
        // Fetch products in category to get their reviews
        $products = \App\Models\Product::where('category_id', $categoryId)
            ->pluck('id');

        $reviews = \App\Models\Review::whereIn('product_id', $products)
            ->where('is_approved', true)
            ->with(['product', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $reviews->map(function ($review) {

            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'user_name' => $review->user->full_name ?? 'Verified Buyer',
                'created_at' => $review->created_at->format('M j, Y'),
                'product' => [
                    'name' => $review->product->name,
                    'slug' => $review->product->slug,
                    'image' => $review->product->image ?? $review->product->main_image_url
                ]
            ];
        })->toArray();
    }
}