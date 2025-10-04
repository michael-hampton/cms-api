<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
            'title' => $this->resource['title'],
            'slug' => $this->resource['slug'],
            'status' => $this->resource['status'],
            'meta_title' => $this->resource['meta_title'],
            'meta_description' => $this->resource['meta_description'],
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at'],
            'blocks' => $this->whenLoaded('blocks', BlockResource::collection($this->resource['blocks'] ?? [])),
        ];
    }
}