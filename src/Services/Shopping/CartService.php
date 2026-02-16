<?php

namespace App\Services\Shopping;

use App\Exceptions\Cart\InsufficientStockException;
use App\Framework\Database\Database;
use App\Framework\Session\Session;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductVariantRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shipping\ShippingService;
use App\Services\Shopping\Factories\CartItemFactory;
use App\Services\Shopping\Resolvers\CartPriceResolver;
use App\Services\Shopping\Resolvers\CartStockResolver;
use App\Services\Vouchers\VoucherService;

class CartService
{
    public function __construct(
        private readonly CartRepository           $cartRepository,
        private readonly ProductRepository        $productRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly ProductOfferRepository   $offerRepository,
        private readonly ProductOfferBundleRepository $bundleRepository,
        private readonly VoucherService           $voucherService,
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly Database                 $database,
        private readonly CartStockResolver        $stockResolver,
        private readonly CartPriceResolver        $priceResolver,
        private readonly CartItemFactory          $itemFactory,
        private readonly ShippingService          $shippingService
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

    private function getUserId(): ?int
    {
        return Session::get('user_id');
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
                'variant_id' => $item->variant_id,
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

            if ($item->variant_id) {
                $variant = $this->productRepository->getVariantById($item->variant_id);

                $itemData['variant_options'] = $item->variant->options;
                $itemData['sku'] = $variant->sku;
            }

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

    public function addItem(
        int   $productId,
        int   $quantity = 1,
        array $options = [],
        ?int  $variantId = null
    ): array
    {
        $product = $this->productRepository->find($productId, ['availableMerchants', 'variants']);

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Product not found or inactive'];
        }

        $variant = null;
        if ($variantId) {
            $variant = $this->productRepository->getVariantById($variantId);

            if (!$variant) {
                return ['success' => false, 'message' => 'Variant not found'];
            }
        }

        // Check for conflicting promotions BEFORE stock check
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();
        $existingItems = $this->cartRepository->findBySessionOrUser($userId, $sessionId);

        foreach ($existingItems as $existingItem) {
            // Check if same product+variant combination with promotion
            if ($existingItem->product_id === $productId &&
                $existingItem->variant_id === $variantId &&
                ($existingItem->isOffer() || $existingItem->isBundle())) {
                return ['success' => false, 'message' => 'Product already in cart with a promotion'];
            }
        }

        // Validate stock BEFORE any DB operations
        try {
            $this->stockResolver->assertCanAdd($product, $variant, $quantity);
        } catch (InsufficientStockException $e) {
            return ['success' => false, 'message' => $e->getUserMessage()];
        }

        $price = $this->priceResolver->resolve($product, $variant);

        $existingItem = $this->cartRepository->findItemByProduct($productId, $userId, $sessionId);

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;

            // Validate updated quantity against stock
            try {
                $this->stockResolver->assertCanAdd($product, $variant, $newQuantity);
            } catch (InsufficientStockException $e) {
                return ['success' => false, 'message' => $e->getUserMessage()];
            }

            $existingItem->update([
                'quantity' => $newQuantity,
                'subtotal' => $price * $newQuantity
            ]);

        } else {
            $merchantId = $product->availableMerchants?->count() > 0
                ? $product->availableMerchants->first()->merchant_id
                : null;

            $cartItemData = $this->itemFactory->fromProduct(
                $sessionId,
                $userId,
                $product,
                $quantity,
                $price,
                $options,
                $variantId,
                $merchantId
            );

            $this->cartRepository->create($cartItemData->toArray());
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
        $variant = $cartItem->variant_id ? $cartItem->variant : null;

        // Use stock resolver to check variant or product stock
        try {
            $this->stockResolver->assertCanUpdate($product, $variant, $quantity);
        } catch (InsufficientStockException $e) {
            return ['success' => false, 'message' => $e->getUserMessage()];
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

    /**
     * Get cart total by summing all item subtotals.
     */
    public function getTotal(): float
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $items = $this->cartRepository->findBySessionOrUser($userId, $sessionId);

        return (float)$items->sum('subtotal');
    }

    /**
     * Get total item count (sum of all quantities).
     */
    public function getCount(): int
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        return $this->cartRepository->getCountBySessionOrUser($userId, $sessionId);
    }

    /**
     * Add subscription to cart.
     *
     * For one-time subscriptions: validates delivery type.
     * For recurring subscriptions: requires associated product.
     */
    public function addSubscriptionToCart(int $subscriptionPlanId, string $deliveryType = 'print', array $data = []): array
    {
        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        $subscriptionPlan = $this->subscriptionPlanRepository->find($subscriptionPlanId, ['pricingTiers']);

        if (!$subscriptionPlan) {
            return ['success' => false, 'message' => 'Subscription plan not found or inactive'];
        }

        // Check if one-time subscription
        if ($subscriptionPlan->isOneTime()) {
            // Validate delivery type
            $allowedDeliveryTypes = $subscriptionPlan->getDeliveryOptions();
            if (!in_array($deliveryType, $allowedDeliveryTypes)) {
                return ['success' => false, 'message' => 'Invalid delivery type'];
            }

            // Check if already in cart
            $existingItem = $this->cartRepository->findBySubscriptionPlan($subscriptionPlanId, $userId, $sessionId);

            if ($existingItem) {
                return ['success' => false, 'message' => 'Subscription plan already in cart'];
            }

            $price = $subscriptionPlan->price;

            $options =

            // One-time subscriptions don't need a product
            $cartData = [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'product_id' => null, // No product for one-time
                'quantity' => 1,
                'price' => $price,
                'subtotal' => $price,
                'subscription_plan_id' => $subscriptionPlanId,
                'options' => json_encode(array_merge([
                    'delivery_type' => $deliveryType,
                    'pricing_tier_id' => $pricingTierId ?? null,
                ], $data ?? [])),
                'site_id' => $subscriptionPlan->site_id,
                'merchant_id' => null,
                'variant_id' => null,
            ];

            $this->cartRepository->create($cartData);

            return ['success' => true, 'message' => 'Subscription added to cart'];
        }

        // Regular subscription - needs product
        $product = $subscriptionPlan->product;

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Associated product not found or inactive'];
        }

        // Check if subscription already in cart
        $existingItem = $this->cartRepository->findBySubscriptionPlan($subscriptionPlanId, $userId, $sessionId);

        if ($existingItem) {
            return ['success' => false, 'message' => 'Subscription already in cart'];
        }

        $price = $subscriptionPlan->price;

        $cartItemData = $this->itemFactory->fromSubscription(
            $sessionId,
            $userId,
            $product,
            1,
            $price,
            $subscriptionPlanId,
            $deliveryType
        );

        $this->cartRepository->create($cartItemData->toArray());

        return ['success' => true, 'message' => 'Subscription added to cart'];
    }

    /**
     * Alias for addSubscriptionToCart for one-time subscriptions.
     *
     * This method name is more explicit for one-time use cases.
     */
    public function addOneTimeSubscription(int $subscriptionPlanId, string $deliveryType = 'print'): array
    {
        return $this->addSubscriptionToCart($subscriptionPlanId, $deliveryType);
    }

    public function addOfferToCart(int $offerId): array
    {
        $offer = $this->offerRepository->find($offerId);

        if (!$offer || !$offer->is_active) {
            return ['success' => false, 'message' => 'Offer not available'];
        }

        $product = $offer->product;

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Product not available'];
        }

        $sessionId = $this->getSessionId();
        $userId = $this->getUserId();

        // Check if product already in cart
        $existingItem = $this->cartRepository->findItemByProduct($product->id, $userId, $sessionId);

        if ($existingItem) {
            return ['success' => false, 'message' => 'Product already in cart'];
        }

        $price = $offer->sale_price ?? $product->price;
        $merchantId = $offer->merchant?->id;

        $cartItemData = $this->itemFactory->fromOffer(
            $sessionId,
            $userId,
            $product,
            1,
            $price,
            $offerId,
            $merchantId
        );

        $this->cartRepository->create($cartItemData->toArray());

        return ['success' => true, 'message' => 'Offer added to cart'];
    }

    public function addBundleToCart(int $bundleId): array
    {
        return $this->database->transaction(function () use ($bundleId) {
            $bundle = $this->bundleRepository->find($bundleId);

            if (!$bundle || !$bundle->is_active) {
                return ['success' => false, 'message' => 'Bundle not available'];
            }

            $sessionId = $this->getSessionId();
            $userId = $this->getUserId();

            $cartItems = [];

            foreach ($bundle->items as $bundleItem) {
                $product = $bundleItem->getEffectiveProduct();
                $merchant = $bundleItem->getEffectiveMerchant();
                $price = $bundleItem->getEffectivePrice();

                $cartItemData = $this->itemFactory->fromBundle(
                    $sessionId,
                    $userId,
                    $product,
                    $bundleItem->quantity,
                    $price,
                    $bundleId,
                    $merchant?->id
                );

                $cartItem = $this->cartRepository->create($cartItemData->toArray());
                $cartItems[] = $cartItem;
            }

            return [
                'success' => true,
                'message' => 'Bundle added to cart',
                'cart_items' => $cartItems
            ];
        });
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
                $product = $item->product;
                if ($item->isOffer()) {
                    $offer = $this->offerRepository->find($item->getOfferId());
                    $merchantName = $offer->merchant?->name ?? 'Merchant ' . $merchantId;
                } elseif ($item->isBundle()) {
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

    public function getShipmentBreakdown(array $shippingData = []): array
    {
        $merchantGroups = $this->getItemsGroupedByMerchant();
        $requiresShipping = $this->requiresShipping();

        $shipments = [];

        foreach ($merchantGroups as $group) {
            $shipping = $this->shippingService->calculateShipping(
                $group['subtotal'],
                $shippingData,
                $requiresShipping
            );

            $shipments[] = [
                'merchant_id' => $group['merchant_id'],
                'merchant_name' => $group['merchant_name'],
                'item_count' => count($group['items']),
                'subtotal' => $group['subtotal'],
                'shipping' => $shipping,
                'items' => $group['items'],
            ];
        }

        return $shipments;
    }

    public function hasOnlyDigitalItems(): bool
    {
        $items = $this->cartRepository->findBySessionOrUser(
            $this->getUserId(),
            $this->getSessionId()
        );

        foreach ($items as $item) {
            if (!empty($item->subscription_plan_id)) {
                $options = is_string($item->options) ? json_decode($item->options, true) : $item->options;

                if (($options['delivery_type'] ?? '') === 'digital') {
                    continue;
                }

                return false;
            }

            if ($item->variant_id && $item->variant) {
                if ($item->variant->is_digital) {
                    continue;
                }

                return false;
            }

            if ($item->product && isset($item->product->is_digital) && $item->product->is_digital) {
                continue;
            }

            return false;
        }

        return true;
    }

    public function updateStartDate(int $cartItemId, string $startDate, ?int $userId = null, ?string $sessionId = null): array
    {
        $item = $this->cartRepository->find($cartItemId);

        if (!$item) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }

        $startDateTime = new \DateTime($startDate);
        $now = new \DateTime();

        $options = $item->options ?? [];
        $options['start_date'] = $startDate;

        $this->cartRepository->update($cartItemId, [
            'options' => $options
        ]);

        return ['success' => true, 'message' => 'Start date updated'];
    }

    public function requiresShipping(): bool
    {
        return !$this->hasOnlyDigitalItems();
    }
}