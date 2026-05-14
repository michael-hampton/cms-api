<?php

namespace App\Services\Shopping\Factories;

use App\DTO\Cart\CartItemData;
use App\Enums\CartItemType;
use App\Models\Product;
use App\Models\SubscriptionPlan;

/**
 * Builds consistent cart item DTOs.
 *
 * Single responsibility: Assemble cart item data structures.
 *
 * Design decisions:
 * - Accepts arrays for options (repository serializes later)
 * - Uses CartItemType enum (no magic strings)
 * - Bundle items have NO merchant_id at row level (derive from bundle items)
 * - Gift items are always price = 0.0, merchant_id = null
 * - Gift subscriptions use product_id = null; identity lives in subscription_plan_id
 * - Gift products use the Product model directly; no cross-contamination with plan IDs
 */
class CartItemFactory
{
    /**
     * Build cart item DTO for a regular product.
     */
    public function fromProduct(
        string  $sessionId,
        ?int    $userId,
        Product $product,
        int     $quantity,
        float   $price,
        array   $options = [],
        ?int    $variantId = null,
        ?int    $merchantId = null
    ): CartItemData
    {
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: $product->id,
            quantity: $quantity,
            price: $price,
            subtotal: $price * $quantity,
            options: $options,
            site_id: $product->site_id,
            merchant_id: $merchantId,
            variant_id: $variantId,
        );
    }

    /**
     * Build cart item DTO for an offer.
     */
    public function fromOffer(
        string  $sessionId,
        ?int    $userId,
        Product $product,
        int     $quantity,
        float   $price,
        int     $offerId,
        ?int    $merchantId = null
    ): CartItemData
    {
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: $product->id,
            quantity: $quantity,
            price: $price,
            subtotal: $price * $quantity,
            options: [
                'type' => CartItemType::OFFER->value,
                'offer_id' => $offerId,
            ],
            site_id: $product->site_id,
            merchant_id: $merchantId,
            variant_id: null,
        );
    }

    /**
     * Build cart item DTO for a bundle item.
     *
     * IMPORTANT: Bundle items do NOT store merchant_id at row level.
     * Merchant is derived from bundle item configuration.
     * This prevents two sources of truth.
     */
    public function fromBundle(
        string  $sessionId,
        ?int    $userId,
        Product $product,
        int     $quantity,
        float   $price,
        int     $bundleId,
        ?int $merchantId = null
    ): CartItemData
    {
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: $product->id,
            quantity: $quantity,
            price: $price,
            subtotal: $price * $quantity,
            options: [
                'type' => CartItemType::BUNDLE->value,
                'bundle_id' => $bundleId,
                'merchant_id' => $merchantId,
            ],
            site_id: $product->site_id,
            merchant_id: null,
            variant_id: null,
        );
    }

    /**
     * Build cart item DTO for a subscription.
     */
    public function fromSubscription(
        string  $sessionId,
        ?int    $userId,
        SubscriptionPlan $product,
        int     $quantity,
        float   $price,
        int     $subscriptionPlanId,
        string $deliveryType,
        ?int   $pricingTierId = null
    ): CartItemData
    {
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: null,
            quantity: $quantity,
            price: $price,
            subtotal: $price * $quantity,
            options: [
                'type' => CartItemType::SUBSCRIPTION->value,
                'delivery_type' => $deliveryType,
                'pricing_tier_id' => $pricingTierId,
            ],
            site_id: $product->site_id,
            merchant_id: null,
            variant_id: null,
            subscription_plan_id: $subscriptionPlanId,
        );
    }

    /**
     * Build a cart item DTO for a single plan within a subscription bundle.
     *
     * Each plan in the bundle produces its own cart row. The bundle_id is stored
     * in options so the items can be grouped on the cart/checkout UI and so the
     * checkout service can identify them as a bundle during order creation.
     *
     * The price passed here is the allocated share of the bundle_price for this
     * plan (computed by SubscriptionBundlePriceAllocator), not the plan's list price.
     */
    public function fromSubscriptionBundleItem(
        string  $sessionId,
        ?int    $userId,
        Product $product,
        int     $quantity,
        float   $allocatedPrice,
        int     $subscriptionPlanId,
        string  $deliveryType,
        int     $bundleId
    ): CartItemData
    {
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: $product->id,
            quantity: $quantity,
            price: $allocatedPrice,
            subtotal: $allocatedPrice * $quantity,
            options: [
                'type' => CartItemType::SUBSCRIPTION_BUNDLE->value,
                'delivery_type' => $deliveryType,
                'bundle_id' => $bundleId,
                'subscription_plan_id' => $subscriptionPlanId,
            ],
            site_id: $product->site_id,
            merchant_id: null,
            variant_id: null,
            subscription_plan_id: $subscriptionPlanId,
        );
    }

    /**
     * Build a zero-price cart item DTO for a free gift product.
     *
     * Rules:
     * - price and subtotal are always 0.0
     * - merchant_id is always null (gifts are platform-level, not merchant revenue)
     * - variant_id is not supported for gifts (gifts are always the base product)
     * - options always includes type = FREE_GIFT and is_gift = true
     * - Caller passes additional gift metadata (label, source_promotion_id, etc.)
     *   via $giftMetadata; it is merged into options
     *
     * Column responsibility:
     *   product_id → physical merchandise identity (Product::id)
     *   site_id    → derived from Product::site_id
     *
     * @param array $giftMetadata Additional metadata from GiftChecklistItem::toMetadata()
     *                             e.g. ['label' => 'Free Mug', 'source_promotion_id' => 1]
     */
    public function fromGiftProduct(
        string  $sessionId,
        ?int    $userId,
        Product $product,
        int     $quantity,
        array   $giftMetadata = []
    ): CartItemData
    {
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: $product->id,
            quantity: $quantity,
            price: 0.0,
            subtotal: 0.0,
            options: array_merge($giftMetadata, [
                'type' => CartItemType::FREE_GIFT->value,
                'is_gift' => true,
                'product_id' => $product->id,
                'label' => $giftMetadata['label'] ?? null,
            ]),
            site_id: $product->site_id,
            merchant_id: null,
            variant_id: null,
        );
    }

    /**
     * Build a zero-price cart item DTO for a free gift subscription plan.
     *
     * Rules:
     * - price and subtotal are always 0.0
     * - product_id is always null — subscription plans are not products.
     *   Identity lives entirely in subscription_plan_id.
     *   Using plan ID in product_id would corrupt reporting and checkout resolution.
     * - site_id is derived from SubscriptionPlan::site_id (the plan is the source of truth)
     * - merchant_id is always null
     *
     * Column responsibility:
     *   product_id           → NULL (no physical merchandise)
     *   subscription_plan_id → SubscriptionPlan::id
     *   site_id              → SubscriptionPlan::site_id
     *
     * @param array $giftMetadata Additional metadata from GiftChecklistItem::toMetadata()
     *                             e.g. ['label' => 'Free Digital Sub', 'source_promotion_id' => 1]
     */
    public function fromGiftSubscription(
        string           $sessionId,
        ?int             $userId,
        SubscriptionPlan $plan,
        int              $quantity,
        array            $giftMetadata = []
    ): CartItemData
    {
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: null,
            quantity: $quantity,
            price: 0.0,
            subtotal: 0.0,
            options: array_merge($giftMetadata, [
                'type' => CartItemType::FREE_GIFT->value,
                'is_gift' => true,
                'subscription_plan_id' => $plan->id,
                'delivery_type' => $giftMetadata['delivery_type'] ?? null,
            ]),
            site_id: $plan->site_id,
            merchant_id: null,
            variant_id: null,
            subscription_plan_id: $plan->id,
        );
    }
}