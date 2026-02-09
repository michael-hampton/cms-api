<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class CardGroupBlockData extends BaseBlockData
{
    public function __construct(
        public readonly int    $itemsPerRow,
        public readonly string $gap,
        public readonly array  $cards
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            itemsPerRow: (int)($data['itemsPerRow'] ?? 3),
            gap: $data['gap'] ?? 'medium',
            cards: $data['cards'] ?? []
        );
    }
}