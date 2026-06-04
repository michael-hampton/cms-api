<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;
use App\Services\Subscriptions\SubscriptionEntitlementResolver;

class SubscriptionPlanPricingResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'plan_id' => $this->getAttribute('plan_id'),
            'site_id' => $this->getAttribute('site_id'),
            'entitlement_type' => $this->getAttribute('entitlement_type'),
            'effective_entitlement_type' => $this->getEffectiveEntitlementType(),
            'duration_months' => $this->getAttribute('duration_months'),
            'issue_count' => $this->getAttribute('issue_count'),
            'price' => $this->getAttribute('price'),
            'original_price' => $this->getAttribute('original_price'),
            'digital_price' => $this->getAttribute('digital_price'),
            'discount_percentage' => $this->getAttribute('discount_percentage'),
            'label' => $this->getAttribute('label'),
            'period_description' => $this->getAttribute('period_description'),
            'currency' => $this->getAttribute('currency'),
            'is_default' => (bool)$this->getAttribute('is_default'),
            'is_active' => (bool)$this->getAttribute('is_active'),
            'sort_order' => $this->getAttribute('sort_order'),
            'stripe_price_id' => $this->getAttribute('stripe_price_id'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
            'trial_days'            => $this->getAttribute('trial_days'),
            'intro_price'           => $this->getAttribute('intro_price'),
            'intro_cycles'          => $this->getAttribute('intro_cycles'),
            'stripe_intro_price_id' => $this->getAttribute('stripe_intro_price_id'),
        ];
    }

    private function getEffectiveEntitlementType(): ?string
    {
        if (
            !is_object($this->resource)
            || !method_exists($this->resource, 'plan')
        ) {
            return $this->getAttribute('entitlement_type');
        }

        $plan = $this->resource->relationLoaded('plan')
            ? $this->resource->plan
            : $this->resource->plan()->first();

        if (!$plan) {
            return $this->getAttribute('entitlement_type');
        }

        return (new SubscriptionEntitlementResolver())
            ->resolve($plan, $this->resource)
            ->value;
    }
}
