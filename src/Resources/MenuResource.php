<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'layout_config' => !is_array($this->getAttribute('layout_config')) && !empty($this->getAttribute('layout_config')) ? json_decode($this->getAttribute('layout_config'), true) : $this->getAttribute('layout_config'),
            'is_active' => $this->getAttribute('is_active'),
            'menu_type' => $this->getAttribute('menu_type'),
            'site_id' => $this->getAttribute('site_id'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'items' => $this->getAttribute('items'),
            'territories' => $this->getAttribute('territories'),
        ];
    }
}