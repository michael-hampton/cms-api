<?php

namespace App\DTO\PublicContent;

use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;

final readonly class PublicContentDocument
{
    public array $designTokens;

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
        public array $access = ['can_view' => true, 'reason' => null],
        public string $schemaVersion = '1.2',
    ) {
        $this->designTokens = (app(PublicContentDesignTokenProvider::class))->forSite($siteId);
    }
}
