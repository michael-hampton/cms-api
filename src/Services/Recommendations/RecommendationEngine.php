<?php
// src/Services/Recommendations/RecommendationEngine.php

namespace App\Services\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Repositories\Product\ProductRepository;
use App\Services\Recommendations\Signals\PurchaseSignalProvider;
use App\Services\Recommendations\Signals\ViewSignalProvider;
use App\Services\Recommendations\Signals\PopularProductProvider;

class RecommendationEngine
{
    private const PURCHASE_WEIGHT = 3.0;
    private const VIEW_WEIGHT = 1.0;

    public function __construct(
        private readonly ProductRepository      $productRepository,
        private readonly PurchaseSignalProvider $purchaseSignals,
        private readonly ViewSignalProvider     $viewSignals,
        private readonly PopularProductProvider $popularProducts
    )
    {
    }

    public function getRecommendations(
        Member $member,
        int    $siteId,
        int    $limit = 6
    ): Collection
    {
        $purchasedIds = $this->purchaseSignals->getProductIds($member->id);
        $viewedIds = $this->viewSignals->getProductIds($member->id);

        // Batch fetch all base products
        $allBaseIds = array_unique(array_merge($purchasedIds, $viewedIds));
        $baseProducts = $this->productRepository->findMany($allBaseIds);

        $scoredProducts = collect();

        // Get related products for purchased items (higher weight)
        foreach ($baseProducts->whereIn('id', $purchasedIds) as $product) {
            $related = $this->productRepository->findRelated($product, 3);
            foreach ($related as $relatedProduct) {
                $this->addOrUpdateScore(
                    $scoredProducts,
                    $relatedProduct,
                    self::PURCHASE_WEIGHT
                );
            }
        }

        // Get related products for viewed items (lower weight)
        foreach ($baseProducts->whereIn('id', $viewedIds) as $product) {
            $related = $this->productRepository->findRelated($product, 2);
            foreach ($related as $relatedProduct) {
                $this->addOrUpdateScore(
                    $scoredProducts,
                    $relatedProduct,
                    self::VIEW_WEIGHT
                );
            }
        }

        // Remove already purchased products
        $scoredProducts = $scoredProducts->filter(function ($item) use ($purchasedIds) {
            return !in_array($item['product']->id, $purchasedIds);
        });

        // Sort by score descending
        $scoredProducts = $scoredProducts->sortByDesc('score')->values();

        // If not enough recommendations, fill with popular products
        if ($scoredProducts->count() < $limit) {
            $excludeIds = array_merge(
                $purchasedIds,
                $scoredProducts->pluck('product.id')->toArray()
            );

            $popular = $this->popularProducts->getProducts($siteId, $limit, $excludeIds);

            foreach ($popular as $product) {
                $this->addOrUpdateScore($scoredProducts, $product, 0.5);
            }

            $scoredProducts = $scoredProducts->sortByDesc('score')->values();
        }

        return $scoredProducts->take($limit)->pluck('product');
    }

    private function addOrUpdateScore(Collection $collection, $product, float $weight): void
    {
        $existing = $collection->firstWhere('product.id', $product->id);

        if ($existing) {
            $existing['score'] += $weight;
        } else {
            $collection->push([
                'product' => $product,
                'score' => $weight
            ]);
        }
    }
}