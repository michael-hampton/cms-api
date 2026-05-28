<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class HeroBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $variant,
        public readonly ?string $theme,
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly ?string $subheading,
        public readonly ?string $ctaText,
        public readonly ?string $ctaUrl,
        public readonly ?string $secondaryCtaText,
        public readonly ?string $secondaryCtaUrl,
        public readonly ?string $backgroundImage,
        public readonly bool $showSearch,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            variant: $data['variant'] ?? null,
            theme: $data['theme'] ?? null,
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? ($data['subheading'] ?? null),
            subheading: $data['subheading'] ?? ($data['subtitle'] ?? null),
            ctaText: $data['ctaText'] ?? null,
            ctaUrl: $data['ctaUrl'] ?? null,
            secondaryCtaText: $data['secondaryCtaText'] ?? null,
            secondaryCtaUrl: $data['secondaryCtaUrl'] ?? null,
            backgroundImage: $data['backgroundImage'] ?? null,
            showSearch: (bool)($data['showSearch'] ?? false),
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
