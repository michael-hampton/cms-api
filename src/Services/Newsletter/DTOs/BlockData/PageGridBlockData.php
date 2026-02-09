<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class PageGridBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly string  $layout,
        public readonly int     $columns,
        public readonly bool    $showExcerpt,
        public readonly bool    $showImage,
        public readonly array   $pages
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            layout: $data['layout'] ?? 'grid',
            columns: (int)($data['columns'] ?? 3),
            showExcerpt: (bool)($data['showExcerpt'] ?? true),
            showImage: (bool)($data['showImage'] ?? true),
            pages: $data['pages'] ?? []
        );
    }
}