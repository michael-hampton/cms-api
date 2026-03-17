<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class GalleryBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $layout,
        public readonly array $slides,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            layout: $data['layout'] ?? 'carousel',
            slides: $data['slides'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}