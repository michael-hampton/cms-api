<?php

namespace App\Services\Shopping\Factories;

use App\DTO\Cart\CartItemData;
use App\Enums\CartItemType;
use App\Models\Product;

/**
 * Builds consistent cart item DTOs.
 *
 * Single responsibility: Assemble cart item data structures.
 *
 * Design decisions:
 * - Accepts arrays for options (repository serializes later)
 * - Uses CartItemType enum (no magic strings)
 * - Bundle items have NO merchant_id at row level (derive from bundle items)
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
        // Product items don't have type in options (default)
        return new CartItemData(
            session_id: $sessionId,
            user_id: $userId,
            product_id: $product->id,
            quantity: $quantity,
            price: $price,
            subtotal: $price * $quantity,
            options: $options, // Keep as array - repository handles serialization
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
            merchant_id: $merchantId, // Offer merchant stored at row level
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
        ?int    $merchantId = null // Kept for bundle item metadata, but not stored at row level
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
                'merchant_id' => $merchantId, // Stored in options for reference
            ],
            site_id: $product->site_id,
            merchant_id: null, // Never stored at row level for bundles
            variant_id: null,
        );
    }

    /**
     * Build cart item DTO for a subscription.
     */
    public function fromSubscription(
        string  $sessionId,
        ?int    $userId,
        Product $product,
        int     $quantity,
        float   $price,
        int     $subscriptionPlanId,
        string  $deliveryType
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
                'type' => CartItemType::SUBSCRIPTION->value,
                'delivery_type' => $deliveryType,
            ],
            site_id: $product->site_id,
            merchant_id: null,
            variant_id: null,
            subscription_plan_id: $subscriptionPlanId,
        );
    }
}