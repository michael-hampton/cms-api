<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class MapLocationBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $address,
        public readonly ?string $description,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            address: $data['address'] ?? '',
            description: $data['description'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}