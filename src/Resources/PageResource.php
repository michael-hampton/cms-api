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
            'created_at' => is_string($this->getAttribute('created_at')) ? $this->getAttribute('created_at') : $this->getAttribute('created_at')->format('Y-m-d H:i:s'),
            'updated_at' => is_string($this->getAttribute('updated_at')) ? $this->getAttribute('updated_at') : $this->getAttribute('updated_at')->format('Y-m-d H:i:s'),
            'published_at' => $this->getAttribute('published_at')?->format('Y-m-d H:i:s'),

            // Hero fields
            'hero_type' => $this->getAttribute('hero_type'),
            'hero_image_id' => $this->getAttribute('hero_image_id'),
            'hero_video_url' => $this->getAttribute('hero_video_url'),

            // Listing fields
            'listing_synopsis' => $this->getAttribute('listing_synopsis'),
            'listing_title' => $this->getAttribute('listing_title'),
            'listing_dek_label' => $this->getAttribute('listing_dek_label'),
            'listing_image_id' => $this->getAttribute('listing_image_id'),
            'listing_use_as_hero' => $this->getAttribute('listing_use_as_hero'),

            // JSON fields
            'crop_overrides' => $this->getAttribute('crop_overrides'),
            'resolved_images' => $this->getAttribute('resolved_images'),

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
            'products' => $this->whenLoaded('products'),
        ];
    }
}