<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class PageGridResource extends JsonResource
{

    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'title' => $this->getAttribute('title'),
            'slug' => $this->getAttribute('slug'),
            'subtitle' => $this->getAttribute('subtitle'),
            'layout' => $this->getAttribute('layout'),
            'columns' => $this->getAttribute('columns'),
            'show_excerpt' => $this->getAttribute('show_excerpt'),
            'show_image' => $this->getAttribute('show_image'),
            'show_features' => $this->getAttribute('show_features'),
            'show_actions' => $this->getAttribute('show_actions'),
            'is_active' => $this->getAttribute('is_active'),
            'created_by' => $this->getAttribute('created_by'),
            'created_at' => $this->getAttribute('created_at'),
            'items' => is_string($this->getAttribute('items')) ? json_decode($this->getAttribute('items'), true) : $this->getAttribute('items'),
            'territories' => $this->getAttribute('territories'),
            'pages' => $this->getAttribute('pages'),
            'updated_at' => $this->getAttribute('updated_at'),
            'start_date' => $this->getAttribute('start_date')?->format('Y-m-d H:i:s') ?? null,
            'end_date' => $this->getAttribute('end_date')?->format('Y-m-d H:i:s') ?? null,
            'use_hero' => $this->getAttribute('use_hero'),
        ];
    }
}