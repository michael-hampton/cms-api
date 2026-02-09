<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class HeroBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly ?string $ctaText,
        public readonly ?string $ctaUrl,
        public readonly ?string $secondaryCtaText,
        public readonly ?string $secondaryCtaUrl,
        public readonly ?string $backgroundImage,
        public readonly bool    $showSearch
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            ctaText: $data['ctaText'] ?? null,
            ctaUrl: $data['ctaUrl'] ?? null,
            secondaryCtaText: $data['secondaryCtaText'] ?? null,
            secondaryCtaUrl: $data['secondaryCtaUrl'] ?? null,
            backgroundImage: $data['backgroundImage'] ?? null,
            showSearch: (bool)($data['showSearch'] ?? false)
        );
    }
}