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
            'subtitle' => $this->getAttribute('subtitle'),
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
            'social' => $this->whenLoaded('social'),
            'settings' => $this->whenLoaded('settings'),
            'customFields' => $this->whenLoaded('customFields'),
            'authors' => $this->whenLoaded('authors'),
            'pageAuthors' => $this->whenLoaded('pageAuthors'),
            'primaryAuthors' => $this->whenLoaded('primaryAuthors'),
            'contributors' => $this->whenLoaded('contributors'),
            'regionSets' => $this->whenLoaded('regionSets'),
            'territories' => $this->whenLoaded('territories'),
        ];
    }
}