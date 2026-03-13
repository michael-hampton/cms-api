<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class MerchantProductFeedResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'merchant_id' => $this->getAttribute('merchant_id'),
            'feed_url' => $this->getAttribute('feed_url'),
            'feed_type' => $this->getAttribute('feed_type'),
            'is_active' => $this->getAttribute('is_active'),
            'fetch_frequency' => $this->getAttribute('fetch_frequency'),
            'status' => $this->getAttribute('status'),
            'last_error' => $this->getAttribute('last_error'),
            'last_fetched_at' => $this->getAttribute('last_fetched_at')?->format('Y-m-d H:i:s'),
            'next_fetch_at' => $this->getAttribute('next_fetch_at')?->format('Y-m-d H:i:s'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),

            'merchant' => $this->whenLoaded('merchant', fn() => [
                'id' => $this->resource->merchant->id,
                'name' => $this->resource->merchant->name,
            ]),
        ];
    }
}