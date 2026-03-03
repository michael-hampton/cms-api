<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class NewsletterResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'title' => $this->getAttribute('title'),
            'slug' => $this->getAttribute('slug'),
            'content' => $this->getAttribute('content'),
            'interval' => $this->getAttribute('interval'),
            'last_sent' => $this->getAttribute('last_sent')?->format('Y-m-d H:i:s'),
            'active' => $this->getAttribute('active'),
            'site_id' => $this->getAttribute('site_id'),
            'content_type' => $this->getAttribute('content_type'),
            'page_filters' => $this->getAttribute('page_filters'),
            'max_pages' => $this->getAttribute('max_pages'),
            'sort_by' => $this->getAttribute('sort_by'),
            'sort_order' => $this->getAttribute('sort_order'),
            'template' => $this->getAttribute('template'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'is_default' => $this->getAttribute('is_default'),
            'is_premium' => $this->getAttribute('is_premium'),
            'allows_single_purchase' => $this->getAttribute('allows_single_purchase'),
            'paused' => $this->getAttribute('paused'),

            // Statistics
            'statistics' => $this->whenLoaded('statistics', function () {
                return $this->statistics;
            }),
        ];
    }
}