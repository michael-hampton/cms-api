<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class AuthorResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'email' => $this->getAttribute('email'),
            'bio' => $this->getAttribute('bio'),
            'avatar' => $this->getAttribute('avatar'),
            'website' => $this->getAttribute('website'),
            'twitter' => $this->getAttribute('twitter'),
            'linkedin' => $this->getAttribute('linkedin'),
            'facebook' => $this->getAttribute('facebook'),
            'status' => $this->getAttribute('status'),
            'site_id' => $this->getAttribute('site_id'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'page_count' => $this->getPageCount(),
            'url' => '/authors/' . $this->getAttribute('slug'),
            'expertise' => $this->getAttribute('expertise'),
            'awards' => $this->getAttribute('awards'),
            'location' => $this->getAttribute('location'),
            'education' => $this->getAttribute('education'),
            'seniority_date' => $this->getAttribute('seniority_date')?->format('Y-m-d'),

        ];
    }

    private function getPageCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['pages_count'])) {
            return (int)$this->resource['pages_count'];
        }

        if (is_object($this->resource) && method_exists($this->resource, 'pages')) {
            return $this->resource->pages()->count();
        }

        return 0;
    }
}