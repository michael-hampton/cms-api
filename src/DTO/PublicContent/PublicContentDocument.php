<?php

namespace App\DTO\PublicContent;

final readonly class PublicContentDocument
{
    /**
     * @param array<string, mixed> $seo
     * @param array<string, mixed> $taxonomy
     * @param array<string, ContentRegion> $regions
     * @param list<array<string, mixed>> $authors
     * @param list<array<string, mixed>> $landingSections
     * @param array<string, string> $links
     */
    public function __construct(
        public int $id,
        public int $siteId,
        public string $slug,
        public string $type,
        public string $title,
        public ?string $summary,
        public array $seo,
        public array $taxonomy,
        public array $regions,
        public array $authors = [],
        public array $landingSections = [],
        public array $links = [],
        public string $schemaVersion = '1.0',
    ) {
    }
}
