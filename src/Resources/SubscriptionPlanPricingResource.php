<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class SubscriptionPlanPricingResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'plan_id' => $this->getAttribute('plan_id'),
            'site_id' => $this->getAttribute('site_id'),
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
        ];
    }
}