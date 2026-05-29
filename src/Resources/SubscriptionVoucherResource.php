<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

/**
 * Resource for subscription-voucher API responses.
 *
 * Extends the base voucher shape with subscription-specific fields that the
 * Angular SubscriptionVoucherModalComponent relies on:
 *   - subscription_plan_ids   (array of plan IDs, not just product/category/brand IDs)
 *   - stripe_coupon_synced_at (displayed in the modal's Stripe Info section)
 *
 * Intentionally excludes order-only fields (minimum_order_value, applies_to_orders)
 * to keep the payload focused.
 */
class SubscriptionVoucherResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id'                             => $this->getAttribute('id'),
            'site_id'                        => $this->getAttribute('site_id'),
            'code'                           => $this->getAttribute('code'),
            'name'                           => $this->getAttribute('name'),
            'description'                    => $this->getAttribute('description'),
            'terms_and_conditions'           => $this->getAttribute('terms_and_conditions'),
            'status'                         => $this->getAttribute('status'),

            // Discount
            'type'                           => $this->getAttribute('type'),
            'value'                          => $this->getAttribute('value'),
            'discount_type'                  => $this->getAttribute('discount_type'),
            'discount_amount'                => $this->getAttribute('discount_amount'),
            'discount_percentage'            => $this->getAttribute('discount_percentage'),
            'maximum_discount'               => $this->getAttribute('maximum_discount'),

            // Subscription-specific
            'applies_to_subscriptions'       => $this->getAttribute('applies_to_subscriptions', true),
            'subscription_plan_ids' => is_array($this->resource)
                ? array_column($this->resource['subscriptionPlans'] ?? [], 'id')
                : ($this->resource->subscriptionPlans?->pluck('id')->toArray() ?? []),            'subscription_discount_duration' => $this->getAttribute('subscription_discount_duration'),
            'subscription_duration_months'   => $this->getAttribute('subscription_duration_months'),
            'duration_in_months'             => $this->getAttribute('duration_in_months'),

            // Usage
            'usage_limit'                    => $this->getAttribute('usage_limit'),
            'usage_count'                    => $this->getAttribute('usage_count', 0),
            'per_user_limit'                 => $this->getAttribute('per_user_limit'),
            'is_stackable'                   => $this->getAttribute('is_stackable', false),

            // Dates
            'starts_at'                      => $this->getAttribute('starts_at')?->format('Y-m-d H:i:s') ?? null,
            'expires_at'                     => $this->getAttribute('expires_at')?->format('Y-m-d H:i:s') ?? null,
            'created_at'                     => $this->getAttribute('created_at'),
            'updated_at'                     => $this->getAttribute('updated_at'),

            // Stripe sync — displayed in the modal's read-only Stripe Info section
            'stripe_coupon_id'               => $this->getAttribute('stripe_coupon_id'),
            'stripe_coupon_synced_at'        => $this->getAttribute('stripe_coupon_synced_at')?->format('Y-m-d H:i:s') ?? null,
        ];
    }
}