<?php

namespace App\Data\PublicContent;

final readonly class PublicDirectoryPageData
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?string $summary,
        public ?string $image,
        public mixed $publishedAt,
        public array $categories,
        public array $tags,
        public array $authors,
    ) {
    }

    public static function fromPage(object $page): self
    {
        return new self(
            id: (int) $page->id,
            title: (string) $page->title,
            slug: (string) $page->slug,
            summary: $page->meta_description ?? null,
            image: $page->metadata->featured_image ?? null,
            publishedAt: $page->published_at ?? $page->created_at,
            categories: self::relations($page->categories ?? null),
            tags: self::relations($page->tags ?? null),
            authors: self::relations($page->authors ?? null),
        );
    }

    private static function relations(mixed $relations): array
    {
        if ($relations === null) {
            return [];
        }

        return $relations
            ->map(static fn(object $relation): array => [
                'name' => (string) $relation->name,
                'slug' => (string) $relation->slug,
            ])
            ->toArray();
    }
}
