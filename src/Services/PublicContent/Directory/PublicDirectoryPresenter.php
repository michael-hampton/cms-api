<?php

namespace App\Services\PublicContent\Directory;

use App\Framework\Support\Collection;

final class PublicDirectoryPresenter
{
    public function entity(string $type, object $entity, string $siteSlug): array
    {
        return [
            'id' => (int)$entity->id,
            'type' => $type,
            'name' => (string)$entity->name,
            'slug' => (string)$entity->slug,
            'description' => $entity->description ?? $entity->bio ?? null,
            'image' => $entity->avatar ?? null,
            'icon' => $entity->icon ?? null,
            'color' => $entity->color ?? null,
            'url' => '/' . rawurlencode($siteSlug) . '/' . $this->plural($type) . '/' . rawurlencode((string)$entity->slug),
            'meta' => $this->meta($type, $entity),
        ];
    }

    public function entities(string $type, Collection $entities, string $siteSlug): array
    {
        return $entities
            ->map(fn($entity) => $this->entity($type, $entity, $siteSlug))
            ->toArray();
    }

    public function pages(Collection $pages, string $siteSlug): array
    {
        return $pages->map(static fn($page) => [
            'id' => (int)$page->id,
            'title' => (string)$page->title,
            'slug' => (string)$page->slug,
            'summary' => $page->meta_description ?? null,
            'image' => $page->metadata->featured_image ?? null,
            'published_at' => $page->published_at ?? $page->created_at,
            'url' => '/' . rawurlencode($siteSlug) . '/' . rawurlencode((string)$page->slug),
            'categories' => $page->categories?->map(static fn($category) => [
                'name' => (string)$category->name,
                'slug' => (string)$category->slug,
                'url' => '/' . rawurlencode($siteSlug) . '/categories/' . rawurlencode((string)$category->slug),
            ])->toArray() ?? [],
            'tags' => $page->tags?->map(static fn($tag) => [
                'name' => (string)$tag->name,
                'slug' => (string)$tag->slug,
                'url' => '/' . rawurlencode($siteSlug) . '/tags/' . rawurlencode((string)$tag->slug),
            ])->toArray() ?? [],
            'authors' => $page->authors?->map(static fn($author) => [
                'name' => (string)$author->name,
                'slug' => (string)$author->slug,
                'url' => '/' . rawurlencode($siteSlug) . '/authors/' . rawurlencode((string)$author->slug),
            ])->toArray() ?? [],
        ])->toArray();
    }

    private function meta(string $type, object $entity): array
    {
        return match ($type) {
            'author' => [
                'bio' => $entity->bio ?? null,
                'expertise' => $entity->expertise ?? null,
                'location' => $entity->location ?? [],
                'education' => $entity->education ?? [],
                'awards' => $entity->awards ?? [],
                'website' => $entity->website ?? null,
                'twitter' => $entity->twitter ?? null,
                'linkedin' => $entity->linkedin ?? null,
                'facebook' => $entity->facebook ?? null,
                'joined_at' => $entity->created_at ?? null,
                'years_of_experience' => $entity->years_of_experience ?? null,
            ],
            'category' => [
                'parent_id' => $entity->parent_id ?? null,
            ],
            'tag' => [
                'usage_count' => (int)($entity->usage_count ?? 0),
                'featured' => (bool)($entity->is_featured ?? false),
            ],
            default => [],
        };
    }

    private function plural(string $type): string
    {
        return match ($type) {
            'category' => 'categories',
            'author' => 'authors',
            'tag' => 'tags',
            default => $type,
        };
    }
}
