<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class SectionBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $title,
        public readonly string $headingType,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            headingType: $data['headingType'] ?? 'h2',
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}