<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class DividerBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $style = 'solid'
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            style: $data['style'] ?? 'solid'
        );
    }
}