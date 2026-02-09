<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class BannerBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly ?string $ctaText,
        public readonly ?string $ctaUrl,
        public readonly string  $backgroundColor,
        public readonly string  $textColor
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
            backgroundColor: $data['backgroundColor'] ?? '#007bff',
            textColor: $data['textColor'] ?? '#ffffff'
        );
    }
}