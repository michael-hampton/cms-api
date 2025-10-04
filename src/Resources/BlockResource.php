<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class BlockResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
            'type' => $this->resource['type'],
            'data' => $this->resource['data'],
            'order' => $this->resource['order'],
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at'],
            'page' => $this->when(
                isset($this->resource['page']),
                new PageResource($this->resource['page'])
            ),
        ];
    }
}