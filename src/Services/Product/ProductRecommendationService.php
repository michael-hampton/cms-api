<?php

namespace App\Services\Product;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Product;
use App\Repositories\Members\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductViewRepository;

class ProductRecommendationService
{
    public function __construct(
        private readonly ProductRepository     $productRepository,
        private readonly OrderRepository       $orderRepository,
        private readonly ProductViewRepository $productViewRepository
    )
    {
    }

    /**
     * Get cross-sell products for a specific product
     */
    public function getCrossSellProducts(Product $product, int $limit = 4): Collection
    {
        return $this->productRepository->findRelated($product, $limit);
    }

    /**
     * Get products formatted for account display
     */
    public function getFormattedRecommendations(Member $member, int $siteId, int $limit = 6): array
    {
        $products = $this->getRecommendedProducts($member, $siteId, $limit);

        return $products->map(function ($product) {
            $description = $product->description;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $this->truncateDescription($description ?? '', 150),
                'image' => $product->main_image_url ?? $product->image,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'currency' => 'USD', // Could be from product or site
                'discount_percentage' => $product->discount_percentage,
                'has_discount' => $product->sale_price && $product->sale_price < $product->price,
            ];
        })->toArray();
    }

    /**
     * Get recommended products for a member based on their activity
     */
    public function getRecommendedProducts(Member $member, int $siteId, int $limit = 6): Collection
    {
        // Get products from member's order history
        $orderHistory = $this->orderRepository->getByUser($member->id);
        $purchasedProductIds = $this->extractProductIds($orderHistory);

        // Get member's browsing history
        $viewedProductIds = $this->getViewedProductIds($member->id);

        // Combine signals to find related products
        $recommendedProducts = collect([]);

        // 1. Products related to purchased items
        if (!empty($purchasedProductIds)) {
            foreach ($purchasedProductIds as $productId) {
                $product = $this->productRepository->find($productId);
                if ($product) {
                    $related = $this->productRepository->findRelated($product, 3);
                    $recommendedProducts = $recommendedProducts->merge($related);
                }
            }
        }

        // 2. Products related to viewed items
        if (!empty($viewedProductIds)) {
            foreach (array_slice($viewedProductIds, 0, 3) as $productId) {
                $product = $this->productRepository->find($productId);
                if ($product) {
                    $related = $this->productRepository->findRelated($product, 2);
                    $recommendedProducts = $recommendedProducts->merge($related);
                }
            }
        }

        // 3. Fallback to popular/featured products if not enough recommendations
        if ($recommendedProducts->count() < $limit) {
            $foundProductIds = array_merge($purchasedProductIds, $recommendedProducts->pluck('id')->toArray());

            $popular = $this->productRepository->getRecommendationProducts($siteId, $limit, $foundProductIds);

            $recommendedProducts = $recommendedProducts->merge($popular);
        }

        // Remove duplicates and already purchased items
        $recommendedProducts = $recommendedProducts
            ->unique('id')
            ->filter(function ($product) use ($purchasedProductIds) {
                return !in_array($product->id, $purchasedProductIds);
            })
            ->take($limit);

        return $recommendedProducts;
    }

    /**
     * Extract product IDs from order history
     */
    private function extractProductIds(Collection $orders): array
    {
        $productIds = [];

        foreach ($orders as $order) {
            $items = $order->items ?? [];

            foreach ($items as $item) {
                $productId = $item->product_id;
                if (!empty($productId)) {
                    $productIds[] = $item->product_id;
                }
            }
        }

        return array_unique($productIds);
    }

    /**
     * Get product IDs from member's view history
     *
     * @param int $memberId
     * @param int $limit Maximum number of product IDs to return
     * @param int $daysBack Number of days to look back
     * @return array Array of product IDs
     */
    private function getViewedProductIds(int $memberId, int $limit = 20, int $daysBack = 30): array
    {
        return $this->productViewRepository->getViewedProductIdsByMember($memberId, $limit, $daysBack);
    }

    /**
     * Truncate description to specified length
     */
    private function truncateDescription(string $description, int $length): string
    {
        if (strlen($description) <= $length) {
            return $description;
        }

        return substr($description, 0, $length) . '...';
    }

    /**
     * Get personalized recommendations based on viewing history
     *
     * @param Member $member
     * @param int $siteId
     * @param int $limit
     * @return Collection
     */
    public function getViewingBasedRecommendations(Member $member, int $siteId, int $limit = 6): Collection
    {
        $viewedProducts = $this->productViewRepository->getViewedProductsByMember($member->id, 10);

        if ($viewedProducts->isEmpty()) {
            return collect([]);
        }

        $recommendations = collect([]);

        foreach ($viewedProducts->take(3) as $viewedProduct) {
            $related = $this->productRepository->findRelated($viewedProduct, 3);
            $recommendations = $recommendations->merge($related);
        }

        // Get frequently viewed together products
        foreach ($viewedProducts->take(2) as $viewedProduct) {
            $frequentlyWith = $this->productViewRepository->getFrequentlyViewedWith($viewedProduct->id, 3);

            if (!empty($frequentlyWith)) {
                $products = $this->productRepository->getActiveProducts($frequentlyWith);
                $recommendations = $recommendations->merge($products);
            }
        }

        return $recommendations->unique('id')->take($limit);
    }

    /**
     * Get products related to member's subscriptions
     */
    private function getProductsFromSubscriptions(Member $member, int $limit): Collection
    {
        // Get active subscriptions
        $subscriptions = \App\Models\Subscription::where('member_id', $member->id)
            ->where('status', 'active')
            ->get();

        if ($subscriptions->isEmpty()) {
            return new Collection([]);
        }

        // Get products tagged with subscription types or categories
        return Product::where('is_active', true)
            ->where('site_id', $member->site_id)
            ->limit($limit)
            ->latest()
            ->get();
    }
}