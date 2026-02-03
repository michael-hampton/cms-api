<?php

namespace App\Services\Recommendations\Signals;

use App\Repositories\Product\ProductViewRepository;

class ViewSignalProvider
{
    public function __construct(
        private readonly ProductViewRepository $productViewRepository
    )
    {
    }

    public function getProductIds(
        int $memberId,
        int $limit = 20,
        int $daysBack = 30
    ): array
    {
        return $this->productViewRepository->getViewedProductIdsByMember(
            $memberId,
            $limit,
            $daysBack
        );
    }

    public function getFrequentlyViewedWith(int $productId, int $limit = 3): array
    {
        return $this->productViewRepository->getFrequentlyViewedWith($productId, $limit);
    }
}