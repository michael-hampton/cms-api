<?php

namespace App\Services\Shopping;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Session\Session;
use App\Models\Product;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\WishlistRepository;

class WishlistService
{
    public function __construct(
        private readonly WishlistRepository $wishlistRepository,
        private readonly ProductRepository            $productRepository,
        private readonly ProductOfferBundleRepository $productOfferBundleRepository,
        private readonly ProductOfferRepository       $productOfferRepository
    ) {}

    protected function getSessionId(): string
    {
        if (empty(Session::get('wishlist_session_id'))) {
            Session::put('wishlist_session_id', uniqid('cart_', true));
        }
        return Session::get('wishlist_session_id');
    }

    public function getItems(): array
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $items = $this->wishlistRepository->findBySessionOrUser($userId, $sessionId);

        return $items->map(function($item) {
            $product = $item->product;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product->name ?? 'Unknown',
                'product_slug' => $product->slug ?? '',
                'product_image' => $product->image ?? '',
                'price' => $product->sale_price ?? $product->price,
                'original_price' => $product->price ?? 0,
                'discount_percentage' => $product->discount_percentage ?? 0,
                'in_stock' => $product->in_stock ?? false,
            ];
        })->toArray();
    }

    public function addItem(int $productId): array
    {
        $product = $this->productRepository->find($productId);

        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $exists = $this->wishlistRepository->existsByProduct($productId, $userId, $sessionId);

        if ($exists) {
            return ['success' => false, 'message' => 'Product already in wishlist'];
        }

        $this->wishlistRepository->create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => $productId,
            'site_id' => $product->site_id,
        ]);

        return ['success' => true, 'message' => 'Product added to wishlist'];
    }

    public function removeItem(int $productId): array
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $deleted = $this->wishlistRepository->deleteByProduct($productId, $userId, $sessionId);

        if ($deleted) {
            return ['success' => true, 'message' => 'Item removed from wishlist'];
        }

        return ['success' => false, 'message' => 'Item not found in wishlist'];
    }

    public function isInWishlist($user, Product $product): bool
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        return $this->wishlistRepository->existsByProduct($product->id, $userId, $sessionId);
    }

    public function getCount(): int
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        return $this->wishlistRepository->getCountBySessionOrUser($userId, $sessionId);
    }

    protected function getUserId(): ?int
    {
        $authId = MemberAuth::id();
        // Return null for guest users (don't use default value of 1)
        return $authId ?: null;
    }

    public function addOffer(int $offerId): array
    {
        $offer = $this->productOfferRepository->find($offerId);

        if (!$offer || !$offer->isCurrentlyActive()) {
            return ['success' => false, 'message' => 'Offer not available'];
        }

        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $exists = $this->wishlistRepository->getOffers($offerId, $userId, $sessionId)->isNotEmpty();

        if ($exists) {
            return ['success' => false, 'message' => 'Offer already in wishlist'];
        }

        $this->wishlistRepository->create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => $offer->product_id,
            'item_type' => 'offer',
            'item_id' => $offerId,
            'site_id' => $offer->product->site_id,
        ]);

        return ['success' => true, 'message' => 'Offer added to wishlist'];
    }

    public function addBundle(int $bundleId): array
    {
        $bundle = $this->productOfferBundleRepository->find($bundleId);

        if (!$bundle || !$bundle->isCurrentlyActive()) {
            return ['success' => false, 'message' => 'Bundle not available'];
        }

        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $exists = $this->wishlistRepository->getBundles($bundleId, $userId, $sessionId)->isNotEmpty();

        if ($exists) {
            return ['success' => false, 'message' => 'Bundle already in wishlist'];
        }

        $this->wishlistRepository->create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => null,
            'item_type' => 'bundle',
            'item_id' => $bundleId,
            'site_id' => $bundle->items->first()?->getEffectiveProduct()->site_id ?? 1,
        ]);

        return ['success' => true, 'message' => 'Bundle added to wishlist'];
    }
}