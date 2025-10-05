<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'logo' => $this->getAttribute('logo'),
            'website' => $this->getAttribute('website'),
            'is_active' => $this->getAttribute('is_active', true),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'products' => $this->whenLoaded('products'),
        ];
    }
}