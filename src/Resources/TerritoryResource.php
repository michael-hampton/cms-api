<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class TerritoryResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'code' => $this->getAttribute('code'),
            'region_set_id' => $this->getAttribute('region_set_id'),
            'is_active' => $this->getAttribute('is_active'),
            'sort_order' => $this->getAttribute('sort_order'),
            'page_count' => $this->getPageCount(),
            'region_set' => $this->whenLoaded('regionSet',
                fn() => new RegionSetResource($this->resource->regionSet)
            ),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
        ];
    }

    private function getPageCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['pages_count'])) {
            return (int)$this->resource['pages_count'];
        }

        return $this->getAttribute('pages_count') ?? 0;
    }
}