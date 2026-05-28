<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class StatsBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $title,
        public readonly array $stats,
        public readonly ?string $layout,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? null,
            stats: $data['stats'] ?? [],
            layout: $data['layout'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
