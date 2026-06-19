<?php

namespace App\DTO\PublicContent;

final readonly class PublicContentDocument
{
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
        public array $components = [],
        public array $authors = [],
        public array $landingSections = [],
        public array $links = [],
        public array $widgets = [],
        public array $designTokens = [],
        public array $access = ['can_view' => true, 'reason' => null],
        public string $schemaVersion = '1.1',
    ) {
    }
}
