<?php

namespace App\Services\Shop;

use App\Framework\Session\Session;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shop\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

class CartService
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly ProductRepository          $productRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
    ) {}

    protected function getSessionId(): string
    {
        if (empty(Session::get('cart_session_id'))) {
            Session::put('cart_session_id', uniqid('cart_', true));
        }
        return Session::get('cart_session_id');
    }

    public function getItems(): array
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $items = $this->cartRepository->findBySessionOrUser($userId, $sessionId);

        return $items->map(function($item) {
            $product = $item->product;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product->name ?? 'Unknown',
                'product_slug' => $product->slug ?? '',
                'product_image' => $product->image ?? '',
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
                'options' => $item->options,
                'subscription_plan_id' => $item->subscription_plan_id,
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
        $userId = $this->getUserId();

        $price = $product->sale_price > 0 ? $product->sale_price : $product->price;

        $existingItem = $this->cartRepository->findItemByProduct($productId, $userId, $sessionId);

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;

            if ($product->stock_quantity !== null && $product->stock_quantity < $newQuantity) {
                return ['success' => false, 'message' => 'Cannot add more items. Stock limit reached.'];
            }

            $existingItem->update([
                'quantity' => $newQuantity,
                'subtotal' => $price * $newQuantity
            ]);
        } else {
            $this->cartRepository->create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $product->sale_price ?? $product->price,
                'subtotal' => $price * $quantity,
                'options' => json_encode($options),
                'site_id' => $product->site_id,
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
        $userId = $this->getUserId();

        $cartItem = $this->cartRepository->findById($cartItemId, $userId, $sessionId);

        if (!$cartItem) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }

        $product = $cartItem->product;

        if ($product && $product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }

        $this->cartRepository->update($cartItemId, [
            'quantity' => $quantity,
            'subtotal' => $cartItem->price * $quantity
        ]);

        return ['success' => true, 'message' => 'Cart updated'];
    }

    public function removeItem(int $cartItemId): array
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $cartItem = $this->cartRepository->findById($cartItemId, $userId, $sessionId);

        if (!$cartItem) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }

        $this->cartRepository->delete($cartItemId);

        return ['success' => true, 'message' => 'Item removed from cart'];
    }

    public function clear(): void
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $this->cartRepository->deleteBySessionOrUser($userId, $sessionId);
    }

    public function getTotal(): float
    {
        $items = $this->getItems();

        return array_sum(array_column($items, 'subtotal'));
    }

    public function getCount(): int
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        return $this->cartRepository->getCountBySessionOrUser($userId, $sessionId);
    }

    protected function getUserId(): ?int
    {
        $authId = auth()->id();
        // Return null for guest users (don't use default value of 1)
        return $authId ?: null;
    }

    public function addOneTimeSubscription(
        int    $planId,
        string $deliveryType,
        array  $options = []
    ): array
    {
        $plan = $this->subscriptionPlanRepository->find($planId);

        if (!$plan || !$plan->isOneTime()) {
            return ['success' => false, 'message' => 'Invalid subscription plan'];
        }

        // Validate delivery type
        $validDeliveryTypes = $plan->getDeliveryOptions();

        if (!in_array($deliveryType, $validDeliveryTypes)) {
            return ['success' => false, 'message' => 'Invalid delivery type'];
        }

        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        // Check if subscription plan already in cart
        $existingItem = $this->cartRepository->findBySubscriptionPlan($planId, $userId, $sessionId);

        if ($existingItem) {
            return ['success' => false, 'message' => 'Subscription plan already in cart'];
        }

        $price = $plan->price;

        $cartData = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => null, // No product for subscriptions
            'subscription_plan_id' => $planId,
            'quantity' => 1,
            'price' => $price,
            'subtotal' => $price,
            'options' => json_encode(array_merge($options, [
                'delivery_type' => $deliveryType,
                'plan_name' => $plan->name,
                'billing_period' => $plan->billing_period,
            ])),
            'site_id' => $plan->site_id,
        ];

        $this->cartRepository->create($cartData);

        return [
            'success' => true,
            'message' => 'Subscription added to cart'
        ];
    }
}