<?php

namespace App\DTO\Cart;

/**
 * Data Transfer Object for cart item creation.
 *
 * Immutable value object representing cart item data.
 *
 * Design decisions:
 * - Options stored as array internally
 * - Serialization happens in toArray() for repository
 * - Readonly to prevent mutation
 */
readonly class CartItemData
{
    public function __construct(
        public string $session_id,
        public ?int   $user_id,
        public int    $product_id,
        public int    $quantity,
        public float  $price,
        public float  $subtotal,
        public array  $options, // Array, not string - repository serializes
        public int    $site_id,
        public ?int   $merchant_id,
        public ?int   $variant_id,
        public ?int   $subscription_plan_id = null,
    )
    {
    }

    /**
     * Convert DTO to array for repository persistence.
     *
     * This is where serialization happens - NOT in the factory.
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'subtotal' => $this->subtotal,
            'options' => json_encode($this->options), // Serialize here
            'site_id' => $this->site_id,
            'merchant_id' => $this->merchant_id,
            'variant_id' => $this->variant_id,
            'subscription_plan_id' => $this->subscription_plan_id,
        ];
    }

    /**
     * Check if this is an offer item.
     */
    public function isOffer(): bool
    {
        return $this->getType() === 'offer';
    }

    /**
     * Get cart item type from options.
     */
    public function getType(): ?string
    {
        return $this->options['type'] ?? null;
    }

    /**
     * Check if this is a bundle item.
     */
    public function isBundle(): bool
    {
        return $this->getType() === 'bundle';
    }

    /**
     * Check if this is a subscription item.
     */
    public function isSubscription(): bool
    {
        return $this->subscription_plan_id !== null;
    }
}