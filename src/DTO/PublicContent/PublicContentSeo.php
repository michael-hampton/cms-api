<?php

namespace App\DTO\PublicContent;

final readonly class PublicContentSeo
{
    /**
     * @param list<array{hreflang: string, href: string}> $hreflangAlternates
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $keywords,
        public string $canonical,
        public string $robots,
        public string $ogType,
        public string $ogTitle,
        public string $ogDescription,
        public string $ogImage,
        public string $twitterCard,
        public ?array $schema,
        public array $hreflangAlternates = [],
        public ?string $locale = null,
        public ?string $region = null,
    ) {
    }
}
