<?php
// App/Services/WishlistService.php

namespace App\Services;

use App\Models\Wishlist;
use App\Models\Product;
use App\Repositories\ProductRepository;

class WishlistService
{
    protected ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    protected function getSessionId(): string
    {
        if (!isset($_SESSION['wishlist_session_id'])) {
            $_SESSION['wishlist_session_id'] = uniqid('wishlist_', true);
        }
        return $_SESSION['wishlist_session_id'];
    }

    public function getItems(): array
    {
        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        $query = Wishlist::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $items = $query->with('product')->get();

        return $items->map(function($item) {
            $product = $item->product;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product->name ?? 'Unknown',
                'product_image' => $product->image_url ?? '',
                'price' => $product->sale_price ?? $product->price,
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
        $userId = auth()->id();

        $exists = Wishlist::query()
            ->where('product_id', $productId)
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->exists();

        if ($exists) {
            return ['success' => false, 'message' => 'Product already in wishlist'];
        }

        Wishlist::create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => $productId,
        ]);

        return ['success' => true, 'message' => 'Product added to wishlist'];
    }

    public function removeItem(int $productId): array
    {
        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        $deleted = Wishlist::query()
            ->where('product_id', $productId)
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->delete();

        if ($deleted) {
            return ['success' => true, 'message' => 'Item removed from wishlist'];
        }

        return ['success' => false, 'message' => 'Item not found in wishlist'];
    }

    public function isInWishlist($user, Product $product): bool
    {
        $sessionId = $this->getSessionId();
        $userId = $user ? $user->id : null;

        return Wishlist::query()
            ->where('product_id', $product->id)
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->exists();
    }

    public function getCount(): int
    {
        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        return Wishlist::query()
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->count();
    }
}