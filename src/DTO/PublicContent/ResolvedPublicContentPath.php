<?php

namespace App\DTO\PublicContent;

final readonly class ResolvedPublicContentPath
{
    public function __construct(
        public string $path,
        public string $slug,
        public ?string $categorySlug,
        public ?string $subcategorySlug,
        public ?string $pageType,
        public string $matchedPattern
    ) {}
}
