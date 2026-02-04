<?php

namespace App\Services\Shopping;

use App\Framework\Session\Session;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

class CartService
{
    public function __construct(
        private readonly CartRepository             $cartRepository,
        private readonly ProductRepository          $productRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly ProductOfferRepository       $offerRepository,
        private readonly ProductOfferBundleRepository $bundleRepository
    )
    {
    }

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

        return $items->map(function ($item) {
            $product = $item->product;

            $itemData = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product->name ?? 'Unknown',
                'product_slug' => $product->slug ?? '',
                'product_image' => $product->image ?? '',
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
                'options' => $item->options,
                'item_type' => $item->getItemType(),
                'merchant_id' => $item->getMerchantId(),
                'subscription_plan_id' => $item->subscription_plan_id,
            ];

            if ($item->isOffer()) {
                $itemData['offer_id'] = $item->getOfferId();
                $itemData['badge'] = 'Limited-time offer';
            }

            if ($item->isBundle()) {
                $itemData['bundle_id'] = $item->getBundleId();
                $itemData['badge'] = 'Bundle deal';
            }

            return $itemData;
        })->toArray();
    }

    public function addItem(int $productId, int $quantity = 1, array $options = []): array
    {
        $product = $this->productRepository->find($productId);

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Product not found or inactive'];
        }

        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        // Check for conflicting promotions
        $existingItems = $this->cartRepository->findBySessionOrUser($userId, $sessionId);

        foreach ($existingItems as $existingItem) {
            if ($existingItem->product_id === $productId &&
                ($existingItem->isOffer() || $existingItem->isBundle())) {
                return ['success' => false, 'message' => 'Product already in cart with a promotion'];
            }
        }

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
        array $options = [],
        ?int  $pricingId = null
    ): array
    {
        $plan = $this->subscriptionPlanRepository->find($planId, ['pricingTiers']);

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

        // Get pricing tier

        $pricing = $pricingId
            ? $plan->pricingTiers->where('id', $pricingId)->first()
            : $plan->getDefaultPricing();

        $cartData = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => null, // No product for subscriptions
            'subscription_plan_id' => $planId,
            'quantity' => 1,
            'price' => !empty($pricing) && $deliveryType === 'digital' && $pricing->digital_price !== null && $pricing->digital_price > 0
                ? $pricing->digital_price
                : ($pricing->price ?? $plan->price),
            'subtotal' => !empty($pricing) && $deliveryType === 'digital' && $pricing->digital_price !== null && $pricing->digital_price > 0
                ? $pricing->digital_price
                : ($pricing->price ?? $plan->price),
            'options' => json_encode(array_merge($options, [
                'delivery_type' => $deliveryType,
                'plan_name' => $plan->name,
                'billing_period' => $plan->billing_period,
                'subscription_plan_id' => $planId,
            ])),
            'site_id' => $plan->site_id,
        ];

        $this->cartRepository->create($cartData);

        return [
            'success' => true,
            'message' => 'Subscription added to cart'
        ];
    }

    public function addOfferToCart(int $offerId, int $quantity = 1): array
    {
        $offer = $this->offerRepository->find($offerId);

        if (!$offer || !$offer->isCurrentlyActive()) {
            return ['success' => false, 'message' => 'Offer not available'];
        }

        $product = $offer->product;

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Product not available'];
        }

        // Check if product already has an offer in cart
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $existingItem = $this->cartRepository->findItemByProduct($product->id, $userId, $sessionId);

        if ($existingItem) {
            return ['success' => false, 'message' => 'Product already in cart'];
        }

        $cartItem = $this->cartRepository->create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $offer->sale_price,
            'subtotal' => $offer->sale_price * $quantity,
            'options' => json_encode([
                'type' => 'offer',
                'offer_id' => $offerId,
                'merchant_id' => $offer->merchant_id,
            ]),
            'site_id' => $product->site_id,
        ]);

        return [
            'success' => true,
            'message' => 'Offer added to cart',
            'cart_item' => $cartItem
        ];
    }

    public function addBundleToCart(int $bundleId): array
    {
        $bundle = $this->bundleRepository->find($bundleId);

        if (!$bundle || !$bundle->isCurrentlyActive()) {
            return ['success' => false, 'message' => 'Bundle not available'];
        }

        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $cartItems = [];

        foreach ($bundle->items as $bundleItem) {
            $product = $bundleItem->getEffectiveProduct();
            $price = $bundleItem->getEffectivePrice();
            $merchant = $bundleItem->getEffectiveMerchant();

            if (!$product || !$product->is_active) {
                continue;
            }

            $cartItem = $this->cartRepository->create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'product_id' => $product->id,
                'quantity' => $bundleItem->quantity,
                'price' => $price,
                'subtotal' => $price * $bundleItem->quantity,
                'options' => json_encode([
                    'type' => 'bundle',
                    'bundle_id' => $bundleId,
                    'merchant_id' => $merchant?->id,
                ]),
                'site_id' => $product->site_id,
            ]);

            $cartItems[] = $cartItem;
        }

        return [
            'success' => true,
            'message' => 'Bundle added to cart',
            'cart_items' => $cartItems
        ];
    }

    public function getItemsGroupedByMerchant(): array
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();
        $items = $this->cartRepository->findBySessionOrUser($userId, $sessionId);

        $grouped = [];

        foreach ($items as $item) {
            $merchantId = $item->getMerchantId() ?? 0;
            $merchantName = 'Direct';

            if ($merchantId > 0) {
                // Get merchant name from product or offer/bundle
                $product = $item->product;
                if ($item->isOffer()) {
                    $offer = $this->offerRepository->find($item->getOfferId());
                    $merchantName = $offer->merchant?->name ?? 'Merchant ' . $merchantId;
                } elseif ($item->isBundle()) {
                    // For bundles, we'll split items by their respective merchants
                    // This is handled separately
                    continue;
                }
            }

            if (!isset($grouped[$merchantId])) {
                $grouped[$merchantId] = [
                    'merchant_id' => $merchantId,
                    'merchant_name' => $merchantName,
                    'items' => [],
                    'subtotal' => 0,
                ];
            }

            $itemData = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Unknown',
                'product_slug' => $item->product->slug ?? '',
                'product_image' => $item->product->image ?? '',
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
                'item_type' => $item->getItemType(),
            ];

            $grouped[$merchantId]['items'][] = $itemData;
            $grouped[$merchantId]['subtotal'] += $item->subtotal;
        }

        // Handle bundle items - they need special grouping
        foreach ($items as $item) {
            if ($item->isBundle()) {
                $bundleId = $item->getBundleId();
                $bundle = $this->bundleRepository->find($bundleId);

                if ($bundle) {
                    foreach ($bundle->items as $bundleItem) {
                        $merchant = $bundleItem->getEffectiveMerchant();
                        $merchantId = $merchant?->id ?? 0;
                        $merchantName = $merchant?->name ?? 'Direct';

                        if (!isset($grouped[$merchantId])) {
                            $grouped[$merchantId] = [
                                'merchant_id' => $merchantId,
                                'merchant_name' => $merchantName,
                                'items' => [],
                                'subtotal' => 0,
                            ];
                        }

                        $product = $bundleItem->getEffectiveProduct();
                        $price = $bundleItem->getEffectivePrice();

                        $itemData = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'product_slug' => $product->slug,
                            'product_image' => $product->image,
                            'price' => $price,
                            'quantity' => $bundleItem->quantity,
                            'subtotal' => $price * $bundleItem->quantity,
                            'item_type' => 'bundle_item',
                            'bundle_id' => $bundleId,
                            'bundle_name' => $bundle->name,
                        ];

                        $grouped[$merchantId]['items'][] = $itemData;
                        $grouped[$merchantId]['subtotal'] += $price * $bundleItem->quantity;
                    }
                }
            }
        }

        return array_values($grouped);
    }

    public function getShipmentBreakdown(): array
    {
        $merchantGroups = $this->getItemsGroupedByMerchant();

        $shipments = [];

        foreach ($merchantGroups as $group) {
            $shipments[] = [
                'merchant_id' => $group['merchant_id'],
                'merchant_name' => $group['merchant_name'],
                'item_count' => count($group['items']),
                'subtotal' => $group['subtotal'],
                'shipping' => $this->calculateShippingForMerchant($group['merchant_id'], $group['subtotal']),
                'items' => $group['items'],
            ];
        }

        return $shipments;
    }

    private function calculateShippingForMerchant(int $merchantId, float $subtotal): float
    {
        // Simple shipping calculation - can be enhanced
        if ($subtotal >= 100) {
            return 0.00; // Free shipping over $100
        }

        return 10.00; // Flat rate per merchant
    }

    public function hasOnlyDigitalItems(): bool
    {
        $items = $this->getItems();

        foreach ($items as $item) {

            if (!empty($item['subscription_plan_id']) && ($item['options']['delivery_type'] ?? '') === 'digital') {
                continue;
            }

            // Check if item is a digital subscription
            if (isset($item['item_type']) && $item['item_type'] === 'subscription') {
                continue; // Digital item, check next
            }

            // Check if item is a digital product
            if (isset($item['product']) && isset($item['product']['is_digital']) && $item['product']['is_digital']) {
                continue; // Digital item, check next
            }

            // Found a physical item
            return false;
        }

        return true;
    }

    public function setSubscriptionStartIssue(string $itemId, int $issueId): array
    {
        $cart = $_SESSION['cart'] ?? [];
        $found = false;

        foreach ($cart as &$item) {
            if ($item['id'] === $itemId && $item['type'] === 'subscription') {
                $item['start_issue_id'] = $issueId;
                $found = true;
                break;
            }
        }

        if ($found) {
            $_SESSION['cart'] = $cart;
            return ['success' => true, 'message' => 'Start issue set'];
        }

        return ['success' => false, 'message' => 'Item not found'];
    }

    public function requiresShipping(): bool
    {
        return !$this->hasOnlyDigitalItems();
    }
}