<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class EmailTemplateResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'site_id' => $this->getAttribute('site_id'),
            'theme_id' => $this->getAttribute('theme_id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'category' => $this->getAttribute('category'),
            'blocks' => $this->getAttribute('layout_definition_json')['blocks'] ?? [],
            'is_active' => (bool)$this->getAttribute('is_active'),
            'thumbnail_url' => $this->getAttribute('thumbnail_url'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }
}