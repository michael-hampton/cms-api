<?php

namespace App\Services\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Product;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Resources\ProductRecommendationResource;

class ProductRecommendationService
{
    public function __construct(
        private readonly ProductRepository             $productRepository,
        private readonly RecommendationEngine          $engine,
        private readonly ProductRecommendationResource $resource
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
     *
     * @deprecated Use ProductRecommendationResource directly
     */
    public function getFormattedRecommendations(Member $member, int $siteId, int $limit = 6): array
    {
        $products = $this->getRecommendedProducts($member, $siteId, $limit);
        return $this->resource->collection($products);
    }

    /**
     * Get recommended products for a member
     */
    public function getRecommendedProducts(Member $member, int $siteId, int $limit = 6): Collection
    {
        return $this->engine->getRecommendations($member, $siteId, $limit);
    }
}