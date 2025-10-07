<?php
// App/Services/CartService.php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Repositories\ProductRepository;

class CartService
{
    protected ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    protected function getSessionId(): string
    {
        if (!isset($_SESSION['cart_session_id'])) {
            $_SESSION['cart_session_id'] = uniqid('cart_', true);
        }
        return $_SESSION['cart_session_id'];
    }

    public function getItems(): array
    {
        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        $query = CartItem::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $items = $query->with('product')->get();

        return $items->map(function($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Unknown',
                'product_image' => $item->product->image_url ?? '',
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->price * $item->quantity,
                'options' => $item->options,
            ];
        })->toArray();
    }

    public function addItem(int $productId, int $quantity = 1, array $options = []): array
    {
        $product = $this->productRepository->find($productId);

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Product not found or inactive'];
        }

        if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }

        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        $existingItem = CartItem::query()
            ->where('product_id', $productId)
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;

            if ($product->stock_quantity !== null && $product->stock_quantity < $newQuantity) {
                return ['success' => false, 'message' => 'Cannot add more items. Stock limit reached.'];
            }

            $existingItem->update(['quantity' => $newQuantity]);
        } else {
            CartItem::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $product->sale_price ?? $product->price,
                'options' => json_encode($options),
            ]);
        }

        return ['success' => true, 'message' => 'Product added to cart'];
    }

    public function updateQuantity(int $cartItemId, int $quantity): array
    {
        if ($quantity < 1) {
            return $this->removeItem($cartItemId);
        }

        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        $cartItem = CartItem::query()
            ->where('id', $cartItemId)
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->first();

        if (!$cartItem) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }

        $product = $cartItem->product;

        if ($product && $product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }

        $cartItem->update(['quantity' => $quantity]);

        return ['success' => true, 'message' => 'Cart updated'];
    }

    public function removeItem(int $cartItemId): array
    {
        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        $deleted = CartItem::query()
            ->where('id', $cartItemId)
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->delete();

        if ($deleted) {
            return ['success' => true, 'message' => 'Item removed from cart'];
        }

        return ['success' => false, 'message' => 'Cart item not found'];
    }

    public function clear(): void
    {
        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        CartItem::query()
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->delete();
    }

    public function getTotal(): float
    {
        $items = $this->getItems();
        return array_sum(array_column($items, 'subtotal'));
    }

    public function getCount(): int
    {
        $sessionId = $this->getSessionId();
        $userId = auth()->id();

        return CartItem::query()
            ->where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->sum('quantity');
    }
}