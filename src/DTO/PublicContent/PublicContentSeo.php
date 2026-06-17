<?php

namespace App\DTO\PublicContent;

final readonly class PublicContentSeo
{
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
    ) {
    }
}
