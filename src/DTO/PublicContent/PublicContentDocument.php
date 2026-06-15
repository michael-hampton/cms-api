<?php

namespace App\DTO\PublicContent;

final readonly class PublicContentDocument
{
    /**
     * @param array<string, mixed> $seo
     * @param array<string, mixed> $taxonomy
     * @param array<string, ContentRegion> $regions
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
        public string $schemaVersion = '1.0',
    ) {
    }
}
