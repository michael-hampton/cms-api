<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class NewsletterLayoutResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'layout_definition_json' => $this->getAttribute('layout_definition_json') ?? [],
            'is_system_layout' => $this->getAttribute('is_system_layout') ?? true,
            'site_id' => $this->getAttribute('site_id'),
            'created_by' => $this->getAttribute('created_by'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }
}