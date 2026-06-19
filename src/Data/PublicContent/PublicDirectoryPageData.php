<?php

declare(strict_types=1);

namespace App\Data\PublicContent;

use DateTimeInterface;

final readonly class PublicDirectoryPageData
{
    /**
     * @param list<PublicDirectoryRelationData> $categories
     * @param list<PublicDirectoryRelationData> $tags
     * @param list<PublicDirectoryRelationData> $authors
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?string $summary,
        public ?PublicDirectoryPageImageData $image,
        public ?string $publishedAt,
        public array $categories,
        public array $tags,
        public array $authors,
    ) {
    }

    public static function fromPage(object $page): self
    {
        $imageUrl = $page->metadata?->featured_image ?? null;
        $publishedAt = $page->published_at ?: $page->created_at;

        return new self(
            id: (int) $page->id,
            title: (string) $page->title,
            slug: (string) $page->slug,
            summary: $page->meta_description ?: null,
            image: is_string($imageUrl) && trim($imageUrl) !== ''
                ? new PublicDirectoryPageImageData(
                    url: trim($imageUrl),
                    width: null,
                    height: null,
                    alt: (string) $page->title,
                )
                : null,
            publishedAt: self::normaliseDate($publishedAt),
            categories: self::relations($page->categories),
            tags: self::relations($page->tags),
            authors: self::relations($page->authors),
        );
    }

    private static function normaliseDate(mixed $value): ?string
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
    private static function relations(mixed $relations): array
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
