<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class GalleryBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $layout,
        public readonly array  $slides
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            layout: $data['layout'] ?? 'carousel',
            slides: $data['slides'] ?? []
        );
    }
}