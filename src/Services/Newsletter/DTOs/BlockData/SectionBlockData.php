<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class SectionBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $title,
        public readonly string $headingType
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            headingType: $data['headingType'] ?? 'h2'
        );
    }
}