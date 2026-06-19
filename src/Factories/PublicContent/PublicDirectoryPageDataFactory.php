<?php

declare(strict_types=1);

namespace App\Factories\PublicContent;

use App\Data\PublicContent\PublicDirectoryPageData;
use App\Data\PublicContent\PublicDirectoryPageImageData;
use App\Data\PublicContent\PublicDirectoryRelationData;
use App\Services\Cms\Pages\PageCardImageResolver;
use DateTimeInterface;

final readonly class PublicDirectoryPageDataFactory
{
    public function __construct(
        private PageCardImageResolver $imageResolver,
    ) {
    }

    public function make(object $page): PublicDirectoryPageData
    {
        $resolvedImage = $this->imageResolver->resolve($page);
        $publishedAt = $page->published_at ?: $page->created_at;

        return new PublicDirectoryPageData(
            id: (int) $page->id,
            title: (string) $page->title,
            slug: (string) $page->slug,
            summary: $page->meta_description ?: null,
            image: is_array($resolvedImage) && !empty($resolvedImage['url'])
                ? new PublicDirectoryPageImageData(
                    url: (string) $resolvedImage['url'],
                    width: isset($resolvedImage['width']) ? (int) $resolvedImage['width'] : null,
                    height: isset($resolvedImage['height']) ? (int) $resolvedImage['height'] : null,
                    alt: (string) $page->title,
                )
                : null,
            publishedAt: $this->publishedAt($publishedAt),
            categories: $this->relations($this->resolveRelation($page, 'categories')),
            tags: $this->relations($this->resolveRelation($page, 'tags')),
            authors: $this->relations($this->resolveRelation($page, 'authors')),
        );
    }

    private function resolveRelation(object $page, string $relation): mixed
    {
        if (!method_exists($page, $relation)) {
            return $page->{$relation} ?? null;
        }

        $resolved = $page->{$relation}(true);

        if (is_object($resolved) && method_exists($resolved, 'loadForSingleModel')) {
            return $resolved->loadForSingleModel($page);
        }

        return $resolved;
    }

    private function publishedAt(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }

    /**
     * @return list<PublicDirectoryRelationData>
     */
    private function relations(mixed $relations): array
    {
        if ($relations === null) {
            return [];
        }

        return $relations
            ->map(static fn(object $relation): PublicDirectoryRelationData => PublicDirectoryRelationData::fromEntity($relation))
            ->values()
            ->all();
    }
}
