<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'color' => $this->getAttribute('color'),
            'is_featured' => $this->getAttribute('is_featured'),
            'usage_count' => $this->getAttribute('usage_count'),
            'site_id' => $this->getAttribute('site_id'),
            'seo_title' => $this->getAttribute('seo_title'),
            'seo_description' => $this->getAttribute('seo_description'),
            'no_index' => $this->getAttribute('no_index'),
            'canonical_url' => $this->getAttribute('canonical_url'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'page_count' => $this->getPageCount(),
        ];
    }

    private function getPageCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['pages_count'])) {
            return (int)$this->resource['pages_count'];
        }

        if (is_object($this->resource) && method_exists($this->resource, 'pages')) {
            return $this->resource->pages()->count();
        }

        return $this->getAttribute('usage_count') ?? 0;
    }
}