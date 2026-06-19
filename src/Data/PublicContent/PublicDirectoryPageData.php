<?php

declare(strict_types=1);

namespace App\Data\PublicContent;

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
}
