<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class ListBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $listType,
        public readonly array $items,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            listType: $data['listType'] ?? 'ul',
            items: $data['items'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}