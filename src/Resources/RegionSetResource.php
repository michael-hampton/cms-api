<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class RegionSetResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'is_active' => $this->resource->is_active,
            'sort_order' => $this->resource->sort_order,
            'territory_count' => count($this->resource->territories),
            'page_count' => $this->resource->getPageCount(),
            'territories' => $this->when(
                $this->resource->relationLoaded('territories'),
                fn() => TerritoryResource::collection($this->resource->territories)
            ),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at
        ];
    }
}