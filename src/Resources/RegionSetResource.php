<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class RegionSetResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'is_active' => $this->getAttribute('is_active'),
            'sort_order' => $this->getAttribute('sort_order'),
            'territory_count' => $this->getTerritoryCount(),
            'page_count' => $this->getPageCount(),
            'territories' => $this->whenLoaded('territories',
                fn() => TerritoryResource::collection($this->getAttribute('territories'))
            ),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
        ];
    }

    private function getTerritoryCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['territories_count'])) {
            return (int)$this->resource['territories_count'];
        }

        return $this->getAttribute('territories_count') ?? 0;
    }

    private function getPageCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['pages_count'])) {
            return (int)$this->resource['pages_count'];
        }

        return $this->getAttribute('page_count') ?? 0;
    }
}