<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'title' => $this->getAttribute('title'),
            'slug' => $this->getAttribute('slug'),
            'status' => $this->getAttribute('status'),
            'author_id' => $this->getAttribute('author_id'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'blocks' => $this->getAttribute('blocks', []),
            'categories' => $this->whenLoaded('categories'),
            'tags' => $this->whenLoaded('tags'),
            'metadata' => $this->whenLoaded('metadata'),
            'seo' => $this->whenLoaded('seo'),
            'social' => $this->whenLoaded('seo'),
            'settings' => $this->whenLoaded('seo'),
            'customFields' => $this->whenLoaded('customFields'),
            'author' => $this->whenLoaded('author'),
        ];
    }
}