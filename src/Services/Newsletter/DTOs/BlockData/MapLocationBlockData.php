<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class MapLocationBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $address,
        public readonly ?string $description
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            address: $data['address'] ?? '',
            description: $data['description'] ?? null
        );
    }
}