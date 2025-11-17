<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'color' => $this->getAttribute('color'),
            'icon' => $this->getAttribute('icon'),
            'parent_id' => $this->getAttribute('parent_id'),
            'sort_order' => $this->getAttribute('sort_order'),
            'is_active' => $this->getAttribute('is_active'),
            'meta' => $this->getAttribute('meta'),
            'site_id' => $this->getAttribute('site_id'),
            'seo_title' => $this->getAttribute('seo_title'),
            'seo_description' => $this->getAttribute('seo_description'),
            'no_index' => $this->getAttribute('no_index'),
            'canonical_url' => $this->getAttribute('canonical_url'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),

            // Counts
            'page_count' => $this->getPageCount(),
            'product_count' => $this->getProductCount(),

            // Relationships
            'parent' => $this->whenLoaded('parent', function () {
                return $this->parent ? [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                    'slug' => $this->parent->slug
                ] : null;
            }),
            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(fn($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'sort_order' => $child->sort_order,
                    'is_active' => $child->is_active
                ])->toArray();
            }),
            'pages' => $this->whenLoaded('pages', function () {
                return $this->pages->map(fn($page) => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug
                ])->toArray();
            }),
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

    private function getProductCount(): int
    {
        if (is_array($this->resource) && isset($this->resource['products_count'])) {
            return (int)$this->resource['products_count'];
        }

        if (is_object($this->resource) && method_exists($this->resource, 'products')) {
            return $this->resource->products()->count();
        }

        return 0;
    }
}