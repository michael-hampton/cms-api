<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class StatsBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $title,
        public readonly array   $stats
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            stats: $data['stats'] ?? []
        );
    }
}