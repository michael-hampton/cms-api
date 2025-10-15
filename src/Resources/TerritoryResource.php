<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class TerritoryResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'region_set_id' => $this->resource->region_set_id,
            'is_active' => $this->resource->is_active,
            'sort_order' => $this->resource->sort_order,
            'page_count' => $this->resource->getPageCount(),
            'region_set' => $this->when(
                $this->resource->relationLoaded('regionSet'),
                fn() => new RegionSetResource($this->resource->regionSet)
            ),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at
        ];
    }
}