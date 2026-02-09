<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class HeadingBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $text,
        public readonly ?string $subtitle = null,
        public readonly int     $level = 2
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            level: $data['level'] ?? 2
        );
    }
}