<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'site_id' => $this->getAttribute('site_id'),
            'code' => $this->getAttribute('code'),
            'name' => $this->getAttribute('name'),
            'description' => $this->getAttribute('description'),
            'type' => $this->getAttribute('type'),
            'value' => $this->getAttribute('value'),
            'discount_type' => $this->getAttribute('discount_type'),
            'discount_amount' => $this->getAttribute('discount_amount'),
            'discount_percentage' => $this->getAttribute('discount_percentage'),
            'minimum_order_value' => $this->getAttribute('minimum_order_value'),
            'maximum_discount' => $this->getAttribute('maximum_discount'),
            'usage_limit' => $this->getAttribute('usage_limit'),
            'usage_count' => $this->getAttribute('usage_count', 0),
            'per_user_limit' => $this->getAttribute('per_user_limit'),
            'applies_to_orders' => $this->getAttribute('applies_to_orders', true),
            'applies_to_subscriptions' => $this->getAttribute('applies_to_subscriptions', false),
            'subscription_discount_duration' => $this->getAttribute('subscription_discount_duration'),
            'subscription_duration_months' => $this->getAttribute('subscription_duration_months'),
            'stripe_coupon_id' => $this->getAttribute('stripe_coupon_id'),
            'stripe_coupon_synced_at' => $this->getAttribute('stripe_coupon_synced_at')?->format('Y-m-d H:i:s') ?? null,
            'starts_at' => $this->getAttribute('starts_at')?->format('Y-m-d H:i:s') ?? null,
            'expires_at' => $this->getAttribute('expires_at')?->format('Y-m-d H:i:s') ?? null,
            'status' => $this->getAttribute('status'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'products' => $this->whenLoaded('products'),
            'product_ids' => array_column($this->getAttribute('products'), 'id') ?? [],
            'category_ids' => array_column($this->getAttribute('categories'), 'id') ?? [],
            'brand_ids' => array_column($this->getAttribute('brands'), 'id') ?? [],
        ];
    }
}
