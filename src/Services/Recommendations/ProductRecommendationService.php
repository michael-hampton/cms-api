<?php

namespace App\Services\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Resources\ProductRecommendationResource;
use App\Services\Recommendations\Signals\PopularProductProvider;

class ProductRecommendationService
{
    public function __construct(
        private readonly ProductRepository             $productRepository,
        private readonly RecommendationEngine          $engine,
        private readonly PopularProductProvider $popularProductProvider,
        private readonly ProductRecommendationResource $resource
    )
    {
    }

    /**
     * Get cross-sell products for a specific product.
     */
    public function getCrossSellProducts(Product $product, int $limit = 4): Collection
    {
        return $this->productRepository->findRelated($product, $limit);
    }

    /**
     * Get personalised recommendations for an authenticated member.
     */
    public function getRecommendedProducts(Member $member, int $siteId, int $limit = 6): Collection
    {
        return $this->engine->getRecommendations($member, $siteId, $limit);
    }

    /**
     * Get popular products for guest users (or as a fallback).
     *
     * @param int $siteId
     * @param int $limit
     * @param int[] $excludeIds Product IDs to omit (already visible on page)
     */
    public function getPopularProducts(int $siteId, int $limit = 8, array $excludeIds = []): Collection
    {
        return $this->popularProductProvider->getProducts($siteId, $limit, $excludeIds);
    }

    /**
     * Get products formatted for account display.
     *
     * @deprecated Use ProductRecommendationResource directly
     */
    public function getFormattedRecommendations(Member $member, int $siteId, int $limit = 6): array
    {
        $products = $this->getRecommendedProducts($member, $siteId, $limit);
        return $this->resource->collection($products);
    }
}