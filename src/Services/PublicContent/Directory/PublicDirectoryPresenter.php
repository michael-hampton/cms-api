<?php

declare(strict_types=1);

namespace App\Services\PublicContent\Directory;

use App\Data\PublicContent\PublicDirectoryEntityData;
use App\Data\PublicContent\PublicDirectoryPageCardConfigData;
use App\Data\PublicContent\PublicDirectoryPageData;
use App\Data\PublicContent\PublicDirectoryRelationData;
use App\Framework\Support\Collection;

final class PublicDirectoryPresenter
{
    public function entity(PublicDirectoryEntityData $entity, string $siteSlug): array
    {
        return [
            'id' => $entity->id,
            'type' => $entity->type->value,
            'name' => $entity->name,
            'slug' => $entity->slug,
            'description' => $entity->description,
            'image' => $entity->image,
            'icon' => $entity->icon,
            'color' => $entity->color,
            'url' => '/' . rawurlencode($siteSlug) . '/' . $entity->type->plural() . '/' . rawurlencode($entity->slug),
            'meta' => $entity->meta,
        ];
    }

    public function entities(Collection $entities, string $siteSlug): array
    {
        return $entities
            ->map(fn(PublicDirectoryEntityData $entity): array => $this->entity($entity, $siteSlug))
            ->toArray();
    }

    public function pages(Collection $pages, string $siteSlug): array
    {
        return $pages
            ->map(fn(PublicDirectoryPageData $page): array => $this->page($page, $siteSlug))
            ->toArray();
    }

    public function pageCardConfig(PublicDirectoryPageCardConfigData $config): array
    {
        return [
            'show_image' => $config->showImage,
            'show_summary' => $config->showSummary,
            'show_categories' => $config->showCategories,
            'show_tags' => $config->showTags,
            'show_authors' => $config->showAuthors,
            'show_published_date' => $config->showPublishedDate,
            'category_limit' => $config->categoryLimit,
            'tag_limit' => $config->tagLimit,
            'author_limit' => $config->authorLimit,
            'summary_length' => $config->summaryLength,
        ];
    }

    private function page(PublicDirectoryPageData $page, string $siteSlug): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'summary' => $page->summary,
            'image' => $page->image === null ? null : [
                'url' => $page->image->url,
                'width' => $page->image->width,
                'height' => $page->image->height,
                'alt' => $page->image->alt,
            ],
            'published_at' => $page->publishedAt,
            'url' => '/' . rawurlencode($siteSlug) . '/' . rawurlencode($page->slug),
            'categories' => $this->relations($page->categories, $siteSlug, 'categories'),
            'tags' => $this->relations($page->tags, $siteSlug, 'tags'),
            'authors' => $this->relations($page->authors, $siteSlug, 'authors'),
        ];
    }

    /**
     * @param list<PublicDirectoryRelationData> $relations
     */
    private function relations(array $relations, string $siteSlug, string $path): array
    {
        return array_map(static fn(PublicDirectoryRelationData $relation): array => [
            'name' => $relation->name,
            'slug' => $relation->slug,
            'url' => '/' . rawurlencode($siteSlug) . '/' . $path . '/' . rawurlencode($relation->slug),
        ], $relations);
    }
}
