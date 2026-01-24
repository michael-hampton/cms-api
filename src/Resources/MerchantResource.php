<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class MerchantResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'slug' => $this->resource['slug'],
            'description' => $this->resource['description'] ?? '',
            'logo' => $this->resource['logo'] ?? '',
            'is_active' => $this->resource['is_active'],
            'contact_id' => $this->resource['contact_id'] ?? 0,
            'contact' => $this->resource['contact'] ? [
                'id' => $this->resource['contact']['id'],
                'name' => $this->resource['contact']['name'],
                'email' => $this->resource['contact']['email'],
            ] : null,
            'urls' => collect($this->resource['urls'])?->map(fn($url) => [
                'id' => $url['id'],
                'url' => $url['url'],
                'label' => $url['label'],
                'is_primary' => $url['is_primary'],
            ])->toArray(),
            'primary_url' => $this->resource['primary_url']['url'] ?? '',
            'sites' => collect($this->resource['sites'])?->map(fn($site) => [
                'id' => $site['id'],
                'name' => $site['name'],
            ])->toArray(),
            'product_count' => $this->resource['products_count'] ?? $this->resource['products']?->count() ?? 0,
            'created_by' => $this->resource['created_by'] ?? null,
            'updated_by' => $this->resource['updated_by'] ?? null,
            'created_at' => $this->resource['created_at']?->format('Y-m-d H:i:s') ?? null,
            'updated_at' => $this->resource['updated_at']?->format('Y-m-d H:i:s') ?? null,
        ];
    }
}