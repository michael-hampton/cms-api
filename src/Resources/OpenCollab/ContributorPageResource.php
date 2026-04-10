<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

class ContributorPageResource extends JsonResource
{

    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'title' => $this->getAttribute('title'),
            'slug' => $this->getAttribute('slug'),
            'status' => $this->getAttribute('status'),
            'is_paid' => (bool)$this->getAttribute('is_paid'),
            'price' => (int)($this->getAttribute('price') ?? 0),
            'is_public_contribution' => (bool)$this->getAttribute('is_public_contribution'),
            'published_at' => $this->getAttribute('published_at'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
        ];
    }
}