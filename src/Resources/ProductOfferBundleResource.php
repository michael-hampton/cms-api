<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class ProductOfferBundleResource extends JsonResource
{

    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'description' => $this->getAttribute('description'),
            'slug' => $this->getAttribute('slug'),
            'total_price' => $this->getAttribute('total_price'),
            'bundle_price' => $this->getAttribute('bundle_price'),
            'start_date' => $this->getAttribute('start_date'),
            'end_date' => $this->getAttribute('end_date'),
            'is_active' => $this->getAttribute('is_active'),
            'status' => $this->getAttribute('status'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
            'published_at' => $this->getAttribute('published_at')?->format('Y-m-d H:i:s'),
            'site_id' => $this->getAttribute('site_id'),
            'published_by' => $this->getAttribute('published_by'),
            'rejection_reason' => $this->getAttribute('rejection_reason'),
            'rejected_at' => $this->getAttribute('rejected_at')?->format('Y-m-d H:i:s'),
            'rejected_by' => $this->getAttribute('rejected_by'),
            'discount_percentage' => $this->getAttribute('discount_percentage'),
            //'items' => ProductOfferBundleItemResource::collection($this->items),
        ];
    }
}