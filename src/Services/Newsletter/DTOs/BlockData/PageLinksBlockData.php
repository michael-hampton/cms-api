<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class PageLinksBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $title,
        public readonly string  $layout,
        public readonly int     $columns,
        public readonly bool    $showImages,
        public readonly bool    $showDescriptions,
        public readonly array   $links
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            layout: $data['layout'] ?? 'grid',
            columns: (int)($data['columns'] ?? 3),
            showImages: (bool)($data['showImages'] ?? true),
            showDescriptions: (bool)($data['showDescriptions'] ?? true),
            links: $data['links'] ?? []
        );
    }
}