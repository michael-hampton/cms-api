<?php

declare(strict_types=1);

namespace App\Factories\PublicContent;

use App\Data\PublicContent\PublicDirectoryPageData;
use App\Data\PublicContent\PublicDirectoryPageImageData;
use App\Data\PublicContent\PublicDirectoryRelationData;
use App\Framework\Support\Collection;
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
            categories: $this->relations($this->relation($page, 'categories')),
            tags: $this->relations($this->relation($page, 'tags')),
            authors: $this->relations($this->relation($page, 'authors')),
        );
    }

    private function relation(object $page, string $name): Collection
    {
        if (method_exists($page, $name)) {
            $relation = $page->{$name}();

            if ($relation instanceof Collection) {
                return $relation;
            }

            if (is_object($relation) && method_exists($relation, 'get')) {
                $result = $relation->get();

                if ($result instanceof Collection) {
                    return $result;
                }
            }
        }

        $relation = $page->{$name} ?? null;

        return $relation instanceof Collection ? $relation : new Collection([]);
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
    private function relations(Collection $relations): array
    {
        return $relations
            ->map(static fn(object $relation): PublicDirectoryRelationData => PublicDirectoryRelationData::fromEntity($relation))
            ->values()
            ->all();
    }
}
