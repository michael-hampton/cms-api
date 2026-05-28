<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class SectionBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $title,
        public readonly string $headingType,
        public readonly ?string $navigationText,
        public readonly bool $excludeFromNav,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            headingType: $data['headingType'] ?? 'h2',
            navigationText: $data['navigationText'] ?? null,
            excludeFromNav: (bool)($data['excludeFromNav'] ?? false),
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
