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
            'minimum_order_value' => $this->getAttribute('minimum_order_value'),
            'maximum_discount' => $this->getAttribute('maximum_discount'),
            'usage_limit' => $this->getAttribute('usage_limit'),
            'usage_count' => $this->getAttribute('usage_count', 0),
            'per_user_limit' => $this->getAttribute('per_user_limit'),
            'starts_at' => $this->getAttribute('starts_at'),
            'expires_at' => $this->getAttribute('expires_at'),
            'status' => $this->getAttribute('status'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'products' => $this->whenLoaded('products'),
            'product_ids' => array_column($this->getAttribute('products'), 'id') ?? [],
        ];
    }
}