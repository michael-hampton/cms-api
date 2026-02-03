<?php

namespace App\Services\Recommendations\Signals;

use App\Framework\Support\Collection;
use App\Repositories\Product\ProductRepository;

class PopularProductProvider
{
    public function __construct(
        private readonly ProductRepository $productRepository
    )
    {
    }

    public function getProducts(int $siteId, int $limit, array $excludeIds = []): Collection
    {
        return $this->productRepository->getRecommendationProducts($siteId, $limit, $excludeIds);
    }
}