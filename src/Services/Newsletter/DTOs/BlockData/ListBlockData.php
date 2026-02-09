<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class ListBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $listType,
        public readonly array  $items
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            listType: $data['listType'] ?? 'ul',
            items: $data['items'] ?? []
        );
    }
}