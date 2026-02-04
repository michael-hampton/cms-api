<?php

namespace App\Services\Product;

use App\Framework\Support\Collection;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Shared\SessionStore;

class RecentlyViewedService
{
    private const MAX_RECENT_ITEMS = 20;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly SessionStore      $sessionStore
    )
    {
    }

    public function addProduct(Product $product): void
    {
        $viewedIds = $this->sessionStore->get('recently_viewed', []);

        // Remove product if already in list
        $viewedIds = array_filter($viewedIds, fn($id) => $id !== $product->id);

        // Add to beginning
        array_unshift($viewedIds, $product->id);

        // Keep only last MAX_RECENT_ITEMS
        $viewedIds = array_slice($viewedIds, 0, self::MAX_RECENT_ITEMS);

        $this->sessionStore->put('recently_viewed', $viewedIds);
    }

    public function getProducts(int $limit = 6): Collection
    {
        $viewedIds = $this->sessionStore->get('recently_viewed', []);

        if (empty($viewedIds)) {
            return new Collection([]);
        }

        return $this->productRepository->getActiveProducts(
            array_slice($viewedIds, 0, $limit),
            $limit
        );
    }
}