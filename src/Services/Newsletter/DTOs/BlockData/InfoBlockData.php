<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class InfoBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $infoType,
        public readonly string $description,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            infoType: $data['infoType'] ?? 'info',
            description: $data['description'] ?? '',
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}